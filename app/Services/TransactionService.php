<?php

namespace App\Services;

use App\Exceptions\TapPayException;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Repositories\WalletRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TransactionService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly WalletRepository $wallets,
        private readonly ReceiptService $receipts,
    ) {
    }

    public function paginateForUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->transactions->paginateForWallet($user->wallet->id, $filters, $perPage);
    }

    public function recentForUser(User $user, int $limit = 10): Collection
    {
        return $this->transactions->recentForWallet($user->wallet->id, $limit);
    }

    public function pendingIncomingForUser(User $user): Collection
    {
        return $this->transactions->pendingIncomingForWallet($user->wallet->id);
    }

    /**
     * Ambil detail transaksi dan pastikan user terlibat di dalamnya.
     */
    public function findForUser(User $user, int $id): Transaction
    {
        $transaction = $this->transactions->findById($id);

        if (! $transaction || ! $this->userInvolved($user, $transaction)) {
            throw TapPayException::notFound('Transaksi tidak ditemukan.');
        }

        return $transaction->load([
            'senderWallet.user',
            'receiverWallet.user',
            'merchant',
            'approval',
            'receipt',
            'ledgerEntries',
        ]);
    }

    public function findByCodeForUser(User $user, string $code): Transaction
    {
        $transaction = $this->transactions->findByCode($code);

        if (! $transaction || ! $this->userInvolved($user, $transaction)) {
            throw TapPayException::notFound('Transaksi tidak ditemukan.');
        }

        return $transaction->load(['senderWallet.user', 'receiverWallet.user', 'merchant', 'approval', 'receipt']);
    }

    public function receiptData(User $user, int $id): array
    {
        $transaction = $this->findForUser($user, $id);

        if ($transaction->status !== Transaction::STATUS_COMPLETED) {
            throw TapPayException::validation('Receipt hanya tersedia untuk transaksi yang selesai.');
        }

        if (! $transaction->receipt) {
            $this->receipts->generateFor($transaction);
            $transaction->refresh();
        }

        return $this->receipts->buildReceiptData($transaction);
    }

    public function verifyReceipt(User $user, int $id): array
    {
        $transaction = $this->findForUser($user, $id);
        $receipt = $transaction->receipt;

        if (! $receipt) {
            throw TapPayException::notFound('Receipt belum dibuat untuk transaksi ini.');
        }

        $expectedHash = $this->receipts->buildHash($transaction, $receipt->receipt_number);

        return [
            'transaction_code' => $transaction->transaction_code,
            'receipt_number' => $receipt->receipt_number,
            'receipt_hash' => $receipt->receipt_hash,
            'valid' => hash_equals($expectedHash, $receipt->receipt_hash),
        ];
    }

    private function userInvolved(User $user, Transaction $transaction): bool
    {
        $walletId = $user->wallet?->id;

        return $transaction->wallet_id === $walletId
            || $transaction->sender_wallet_id === $walletId
            || $transaction->receiver_wallet_id === $walletId
            || $transaction->merchant_id === $user->id;
    }
}
