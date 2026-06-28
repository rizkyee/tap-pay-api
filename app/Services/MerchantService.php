<?php

namespace App\Services;

use App\Exceptions\TapPayException;
use App\Models\MerchantProfile;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\MerchantProfileRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Top up saldo customer oleh merchant (dokumentasi section 9).
 * Validasi role merchant, status merchant, customer, nominal, limit, lalu
 * proses kredit wallet customer secara atomic + buat receipt.
 */
class MerchantService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TransactionRepository $transactions,
        private readonly MerchantProfileRepository $merchantProfiles,
        private readonly WalletService $wallet,
        private readonly ReceiptService $receipts,
        private readonly AuditLogService $audit,
    ) {
    }

    public function profileFor(User $merchant): MerchantProfile
    {
        $this->assertMerchant($merchant);

        return $this->merchantProfiles->firstOrCreateForUser($merchant->id, [
            'merchant_code' => MerchantProfile::generateMerchantCode(),
            'business_name' => $merchant->name,
            'merchant_status' => MerchantProfile::STATUS_ACTIVE,
        ]);
    }

    public function summary(User $merchant): array
    {
        $profile = $this->profileFor($merchant);

        return [
            'merchant' => [
                'name' => $merchant->name,
                'merchant_code' => $profile->merchant_code,
                'business_name' => $profile->business_name,
                'status' => $profile->merchant_status,
            ],
            'wallet' => $merchant->wallet,
            'today' => [
                'total_topup' => $this->merchantProfiles->sumTopupToday($merchant->id),
                'transaction_count' => $this->merchantProfiles->countTopupToday($merchant->id),
            ],
        ];
    }

    public function limits(User $merchant): array
    {
        $profile = $this->profileFor($merchant);
        $usedToday = $this->merchantProfiles->sumTopupToday($merchant->id);

        return [
            'single_topup_limit' => (float) $profile->single_topup_limit,
            'daily_topup_limit' => (float) $profile->daily_topup_limit,
            'used_today' => $usedToday,
            'remaining_today' => max(0, (float) $profile->daily_topup_limit - $usedToday),
        ];
    }

    public function searchCustomers(string $term): \Illuminate\Database\Eloquent\Collection
    {
        return $this->users->searchCustomers($term);
    }

    /**
     * Proses top up: kredit wallet customer secara atomic.
     */
    public function topUp(User $merchant, string $customerPhone, float $amount, ?string $notes, ?string $idempotencyKey): Transaction
    {
        $this->assertMerchant($merchant);

        $profile = $this->profileFor($merchant);

        if (! $profile->isActive()) {
            throw TapPayException::roleNotAllowed('Merchant tidak aktif.');
        }

        if ($idempotencyKey) {
            $existing = $this->transactions->findByIdempotencyKey($idempotencyKey, $merchant->id);
            if ($existing) {
                return $existing->load('receipt');
            }
        }

        if ($amount <= 0) {
            throw TapPayException::validation('Nominal top up harus lebih dari 0.');
        }

        if ($amount > (float) $profile->single_topup_limit) {
            throw TapPayException::validation('Nominal melebihi limit single top up.');
        }

        $usedToday = $this->merchantProfiles->sumTopupToday($merchant->id);
        if (($usedToday + $amount) > (float) $profile->daily_topup_limit) {
            throw TapPayException::validation('Top up melebihi limit harian merchant.');
        }

        $customer = $this->users->findActiveCustomer($customerPhone);
        if (! $customer) {
            throw TapPayException::notFound('Customer tidak ditemukan atau tidak aktif.');
        }

        $customerWallet = $customer->wallet;
        if (! $customerWallet || ! $customerWallet->isActive()) {
            throw new TapPayException('WALLET_INACTIVE', 'Wallet customer tidak aktif.', 422);
        }

        $transaction = DB::transaction(function () use ($merchant, $customer, $customerWallet, $amount, $notes, $idempotencyKey) {
            $trx = $this->transactions->create([
                'transaction_code' => Transaction::generateCode(Transaction::TYPE_TOPUP),
                'user_id' => $customer->id,
                'wallet_id' => $customerWallet->id,
                'receiver_wallet_id' => $customerWallet->id,
                'merchant_id' => $merchant->id,
                'type' => Transaction::TYPE_TOPUP,
                'amount' => $amount,
                'fee' => 0,
                'total_amount' => $amount,
                'balance_before' => $customerWallet->balance,
                'balance_after' => $customerWallet->balance,
                'reference' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'status' => Transaction::STATUS_PENDING,
                'description' => 'Top up oleh merchant',
                'notes' => $notes,
            ]);

            $entry = $this->wallet->credit(
                $customerWallet->id,
                $amount,
                $trx,
                'Top up dari merchant ' . $merchant->name,
            );

            $trx->update([
                'status' => Transaction::STATUS_COMPLETED,
                'balance_before' => $entry->balance_before,
                'balance_after' => $entry->balance_after,
                'completed_at' => now(),
            ]);

            $this->receipts->generateFor($trx);

            return $trx;
        });

        $this->audit->log($merchant->id, 'merchant.topup', 'transaction', $transaction->id, [
            'customer_id' => $customer->id,
            'amount' => $amount,
        ]);

        return $transaction->fresh(['receipt', 'receiverWallet.user', 'merchant']);
    }

    private function assertMerchant(User $user): void
    {
        if (! $user->isMerchant()) {
            throw TapPayException::roleNotAllowed('Fitur ini hanya untuk merchant.');
        }
    }
}
