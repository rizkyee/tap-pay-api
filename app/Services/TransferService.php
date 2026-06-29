<?php

namespace App\Services;

use App\Exceptions\TapPayException;
use App\Models\NfcSession;
use App\Models\Transaction;
use App\Models\TransactionApproval;
use App\Models\User;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Flow transfer customer-to-customer berbasis NFC + approval penerima
 * (dokumentasi section 8 & 3.6-3.8). Ini juga menjadi inti Open Payment API.
 */
class TransferService
{
    /**
     * Masa berlaku transaksi pending menunggu approval penerima (detik).
     */
    private const APPROVAL_TTL_SECONDS = 300;

    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly NfcSessionService $nfcSessions,
        private readonly WalletService $wallet,
        private readonly ReceiptService $receipts,
        private readonly AuditLogService $audit,
    ) {
    }

    /**
     * Open Payment Initiation: sender membuat transaksi transfer pending
     * berdasarkan token NFC milik receiver.
     */
    public function initiate(User $sender, string $receiverNfcToken, float $amount, ?string $notes, ?string $idempotencyKey, ?string $deviceId): Transaction
    {
        if ($idempotencyKey) {
            $existing = $this->transactions->findByIdempotencyKey($idempotencyKey, $sender->id);
            if ($existing) {
                return $existing;
            }
        }

        if ($amount <= 0) {
            throw TapPayException::validation('Nominal transfer harus lebih dari 0.');
        }

        $session = $this->nfcSessions->resolveUsableToken($receiverNfcToken);

        if ($session->mode !== NfcSession::MODE_RECEIVE) {
            throw TapPayException::validation('Token NFC bukan untuk menerima pembayaran.');
        }

        $senderWallet = $sender->wallet;
        if (! $senderWallet || ! $senderWallet->isActive()) {
            throw new TapPayException('WALLET_INACTIVE', 'Wallet pengirim tidak aktif.', 422);
        }

        $receiver = $session->user;
        $receiverWallet = $session->wallet;

        if ((int) $receiverWallet->id === (int) $senderWallet->id) {
            throw TapPayException::validation('Tidak dapat mengirim saldo ke wallet sendiri.');
        }

        if (! $receiverWallet->isActive()) {
            throw new TapPayException('WALLET_INACTIVE', 'Wallet penerima tidak aktif.', 422);
        }

        // Soft check saldo (dicek ulang saat approval).
        if ((float) $senderWallet->balance < $amount) {
            throw TapPayException::insufficientBalance();
        }

        $transaction = DB::transaction(function () use ($sender, $senderWallet, $receiverWallet, $receiver, $amount, $notes, $idempotencyKey, $session) {
            $trx = $this->transactions->create([
                'transaction_code' => Transaction::generateCode(Transaction::TYPE_TRANSFER),
                'user_id' => $sender->id,
                'wallet_id' => $senderWallet->id,
                'sender_wallet_id' => $senderWallet->id,
                'receiver_wallet_id' => $receiverWallet->id,
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => $amount,
                'fee' => 0,
                'total_amount' => $amount,
                'balance_before' => $senderWallet->balance,
                'balance_after' => $senderWallet->balance,
                'reference' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'status' => Transaction::STATUS_PENDING,
                'description' => 'Transfer NFC menunggu approval penerima',
                'notes' => $notes,
                'expires_at' => now()->addSeconds(self::APPROVAL_TTL_SECONDS),
            ]);

            TransactionApproval::query()->create([
                'transaction_id' => $trx->id,
                'receiver_user_id' => $receiver->id,
                'status' => TransactionApproval::STATUS_PENDING,
            ]);

            // Token NFC bersifat single-use.
            $this->nfcSessions->markUsed($session);

            return $trx;
        });

        $this->audit->log($sender->id, 'transfer.initiated', 'transaction', $transaction->id, [
            'amount' => $amount,
            'receiver_id' => $receiver->id,
        ], $deviceId);

        return $transaction->load(['receiverWallet.user', 'approval']);
    }

    /**
     * Open Payment Approval: penerima approve transfer dengan PIN.
     */
    public function approve(User $receiver, int $transactionId, string $pin, ?string $deviceId): Transaction
    {
        $transaction = $this->resolvePending($transactionId);

        if ((int) $transaction->receiver_wallet_id !== (int) $receiver->wallet?->id) {
            throw TapPayException::roleNotAllowed('Anda bukan penerima transaksi ini.');
        }

        if (! $receiver->hasTransactionPin()) {
            throw new TapPayException('PIN_NOT_SET', 'PIN transaksi belum diatur. Silakan set PIN terlebih dahulu.', 422);
        }

        if (! Hash::check($pin, $receiver->transaction_pin)) {
            throw TapPayException::invalidPin();
        }

        // Re-check saldo pengirim sebelum pemindahan (di luar transaksi atomic
        // agar penandaan failed tidak ikut ter-rollback).
        $senderBalance = $this->wallet->currentBalance($transaction->sender_wallet_id);

        if ($senderBalance < (float) $transaction->amount) {
            $transaction->update([
                'status' => Transaction::STATUS_FAILED,
                'description' => 'Saldo pengirim tidak cukup saat approval',
            ]);

            $this->audit->log($receiver->id, 'transfer.failed', 'transaction', $transaction->id, [
                'reason' => 'insufficient_balance',
            ], $deviceId);

            throw TapPayException::insufficientBalance();
        }

        $completed = DB::transaction(function () use ($transaction, $deviceId) {
            $this->wallet->debit(
                $transaction->sender_wallet_id,
                (float) $transaction->amount,
                $transaction,
                'Transfer keluar ' . $transaction->transaction_code,
            );

            $this->wallet->credit(
                $transaction->receiver_wallet_id,
                (float) $transaction->amount,
                $transaction,
                'Transfer masuk ' . $transaction->transaction_code,
            );

            $transaction->forceFill([
                'status' => Transaction::STATUS_COMPLETED,
                'description' => 'Transfer NFC berhasil',
                'approved_at' => $transaction->approved_at ?? now(),
                'completed_at' => $transaction->completed_at ?? now(),
            ])->save();

            $transaction->approval?->update([
                'status' => TransactionApproval::STATUS_APPROVED,
                'approved_at' => now(),
                'device_id' => $deviceId,
            ]);

            $this->receipts->generateFor($transaction);

            return $transaction;
        });

        $this->audit->log($receiver->id, 'transfer.approved', 'transaction', $transaction->id, [], $deviceId);

        return $completed->fresh(['receipt', 'receiverWallet.user', 'senderWallet.user']);
    }

    /**
     * Open Payment Reject: penerima menolak transfer.
     */
    public function reject(User $receiver, int $transactionId, ?string $reason): Transaction
    {
        $transaction = $this->resolvePending($transactionId);

        if ((int) $transaction->receiver_wallet_id !== (int) $receiver->wallet?->id) {
            throw TapPayException::roleNotAllowed('Anda bukan penerima transaksi ini.');
        }

$transaction->forceFill([
    'status' => Transaction::STATUS_REJECTED,
    'description' => 'Transfer ditolak penerima',
])->save();

        $transaction->approval?->update([
            'status' => TransactionApproval::STATUS_REJECTED,
            'rejected_at' => now(),
            'reason' => $reason,
        ]);

        $this->audit->log($receiver->id, 'transfer.rejected', 'transaction', $transaction->id, [
            'reason' => $reason,
        ]);

        return $transaction->fresh(['approval']);
    }

    /**
     * Pengirim membatalkan transaksi yang masih pending (belum diproses penerima).
     */
    public function cancel(User $sender, int $transactionId): Transaction
    {
        $transaction = $this->transactions->findById($transactionId);

        if (! $transaction || (int) $transaction->sender_wallet_id !== (int) $sender->wallet?->id) {
            throw TapPayException::notFound('Transaksi tidak ditemukan.');
        }

        if (! $transaction->isPending()) {
            throw TapPayException::validation('Hanya transaksi pending yang bisa dibatalkan.');
        }

     $transaction->forceFill([
    'status' => Transaction::STATUS_CANCELLED,
    'description' => 'Transfer dibatalkan pengirim',
])->save();

        $transaction->approval?->update(['status' => TransactionApproval::STATUS_EXPIRED]);

        $this->audit->log($sender->id, 'transfer.cancelled', 'transaction', $transaction->id);

        return $transaction->fresh(['approval']);
    }

    private function resolvePending(int $transactionId): Transaction
    {
        $transaction = $this->transactions->findById($transactionId);

        if (! $transaction || $transaction->type !== Transaction::TYPE_TRANSFER) {
            throw TapPayException::notFound('Transaksi tidak ditemukan.');
        }

        $transaction->loadMissing('approval');

        if ($transaction->status === Transaction::STATUS_REJECTED) {
            throw TapPayException::transferRejected();
        }

        // Auto-expire bila sudah lewat batas waktu.
        if ($transaction->isPending() && $transaction->isExpired()) {
            $transaction->update([
                'status' => Transaction::STATUS_EXPIRED,
                'description' => 'Transfer kedaluwarsa tanpa approval',
            ]);
            $transaction->approval?->update(['status' => TransactionApproval::STATUS_EXPIRED]);

            throw TapPayException::transferExpired();
        }

        if (! $transaction->isPending()) {
            throw TapPayException::validation('Transaksi sudah tidak dalam status pending.');
        }

        return $transaction;
    }
}
