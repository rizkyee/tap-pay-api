<?php

namespace App\Services;

use App\Exceptions\TapPayException;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Isi saldo customer via kode bayar di merchant (alur mirip DANA x Indomaret).
 */
class MerchantTopupCodeService
{
    public const ADMIN_FEE_RATE = 0.075;
    public const ADMIN_FEE_MIN = 1500;
    public const CODE_TTL_HOURS = 24;

    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly WalletService $wallet,
        private readonly ReceiptService $receipts,
        private readonly AuditLogService $audit,
        private readonly MerchantService $merchants,
    ) {
    }

    public function calculateAdminFee(float $amount): float
    {
        return (float) max(self::ADMIN_FEE_MIN, (int) round($amount * self::ADMIN_FEE_RATE));
    }

    public function createPaymentRequest(User $customer, float $amount): Transaction
    {
        if ($amount < 1000) {
            throw TapPayException::validation('Minimal isi saldo Rp 1.000.');
        }

        $wallet = $customer->wallet;
        if (! $wallet || ! $wallet->isActive()) {
            throw new TapPayException('WALLET_INACTIVE', 'Wallet tidak aktif.', 422);
        }

        $fee = $this->calculateAdminFee($amount);
        $total = $amount + $fee;
        $expiresAt = now()->addHours(self::CODE_TTL_HOURS);

        return DB::transaction(function () use ($customer, $wallet, $amount, $fee, $total, $expiresAt) {
            return $this->transactions->create([
                'transaction_code' => Transaction::generateCode(Transaction::TYPE_TOPUP),
                'payment_code' => $this->generatePaymentCode(),
                'user_id' => $customer->id,
                'wallet_id' => $wallet->id,
                'receiver_wallet_id' => $wallet->id,
                'type' => Transaction::TYPE_TOPUP,
                'amount' => $amount,
                'fee' => $fee,
                'total_amount' => $total,
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance,
                'reference' => (string) Str::uuid(),
                'status' => Transaction::STATUS_PENDING,
                'description' => 'Isi saldo via merchant (kode bayar)',
                'notes' => 'merchant_code_topup',
                'expires_at' => $expiresAt,
            ]);
        });
    }

    public function getRequestForCustomer(User $customer, int $id): Transaction
    {
        $transaction = $this->transactions->findById($id);

        if (! $transaction || (int) $transaction->user_id !== (int) $customer->id) {
            throw TapPayException::notFound('Permintaan isi saldo tidak ditemukan.');
        }

        $this->expireIfNeeded($transaction);

        return $transaction->fresh(['merchant']);
    }

    public function previewByPaymentCode(User $merchant, string $rawCode): Transaction
    {
        $profile = $this->merchants->profileFor($merchant);

        if (! $profile->isActive()) {
            throw TapPayException::roleNotAllowed('Merchant tidak aktif.');
        }

        $code = preg_replace('/\D+/', '', $rawCode) ?: '';
        if (strlen($code) < 10) {
            throw TapPayException::validation('Kode bayar tidak valid.');
        }

        $transaction = $this->transactions->findByPaymentCode($code);
        if (! $transaction) {
            throw TapPayException::notFound('Kode bayar tidak ditemukan.');
        }

        $this->expireIfNeeded($transaction);

        if ($transaction->type !== Transaction::TYPE_TOPUP) {
            throw TapPayException::validation('Kode bayar tidak valid untuk top up.');
        }

        if (! $transaction->isPending()) {
            throw TapPayException::validation('Kode bayar sudah diproses.');
        }

        if ($transaction->isExpired()) {
            throw TapPayException::validation('Kode bayar sudah kedaluwarsa.');
        }

        return $transaction->load(['user', 'receiverWallet.user']);
    }

    public function confirmByPaymentCode(User $merchant, string $rawCode, ?string $idempotencyKey): Transaction
    {
        $profile = $this->merchants->profileFor($merchant);

        if (! $profile->isActive()) {
            throw TapPayException::roleNotAllowed('Merchant tidak aktif.');
        }

        $code = preg_replace('/\D+/', '', $rawCode) ?: '';
        if (strlen($code) < 10) {
            throw TapPayException::validation('Kode bayar tidak valid.');
        }

        if ($idempotencyKey) {
            $existing = $this->transactions->findByIdempotencyKey($idempotencyKey, $merchant->id);
            if ($existing) {
                return $existing->load(['receipt', 'receiverWallet.user', 'merchant']);
            }
        }

        $transaction = $this->transactions->findByPaymentCode($code);
        if (! $transaction) {
            throw TapPayException::notFound('Kode bayar tidak ditemukan atau sudah tidak berlaku.');
        }

        $this->expireIfNeeded($transaction);

        if ($transaction->type !== Transaction::TYPE_TOPUP) {
            throw TapPayException::validation('Kode bayar tidak valid untuk top up.');
        }

        if (! $transaction->isPending()) {
            throw TapPayException::validation('Kode bayar sudah diproses.');
        }

        if ($transaction->isExpired()) {
            throw TapPayException::validation('Kode bayar sudah kedaluwarsa.');
        }

        if ((float) $transaction->amount > (float) $profile->single_topup_limit) {
            throw TapPayException::validation('Nominal melebihi limit single top up merchant.');
        }

        $customerWallet = $transaction->receiverWallet;
        if (! $customerWallet || ! $customerWallet->isActive()) {
            throw new TapPayException('WALLET_INACTIVE', 'Wallet customer tidak aktif.', 422);
        }

        return DB::transaction(function () use ($merchant, $transaction, $customerWallet, $idempotencyKey) {
            $entry = $this->wallet->credit(
                $customerWallet->id,
                (float) $transaction->amount,
                $transaction,
                'Top up via merchant ' . $merchant->name,
            );

            $transaction->update([
                'merchant_id' => $merchant->id,
                'idempotency_key' => $idempotencyKey,
                'status' => Transaction::STATUS_COMPLETED,
                'balance_before' => $entry->balance_before,
                'balance_after' => $entry->balance_after,
                'completed_at' => now(),
            ]);

            $this->receipts->generateFor($transaction->fresh());

            $this->audit->log($merchant->id, 'merchant.topup.code', 'transaction', $transaction->id, [
                'payment_code' => $transaction->payment_code,
                'amount' => $transaction->amount,
            ]);

            return $transaction->fresh(['receipt', 'receiverWallet.user', 'merchant']);
        });
    }

    private function expireIfNeeded(Transaction $transaction): void
    {
        if ($transaction->isPending() && $transaction->isExpired()) {
            $transaction->update(['status' => Transaction::STATUS_EXPIRED]);
            $transaction->refresh();
        }
    }

    private function generatePaymentCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 9999999999999), 13, '0', STR_PAD_LEFT);
        } while (Transaction::query()->where('payment_code', $code)->exists());

        return $code;
    }
}
