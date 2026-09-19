<?php

namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TransactionRepository
{
    public function findByPaymentCode(string $code): ?Transaction
    {
        $normalized = preg_replace('/\D+/', '', $code) ?: $code;

        return Transaction::query()->where('payment_code', $normalized)->first();
    }

    public function create(array $data): Transaction
    {
        return Transaction::query()->create($data);
    }

    public function findById(int $id): ?Transaction
    {
        return Transaction::query()->find($id);
    }

    public function findByCode(string $code): ?Transaction
    {
        return Transaction::query()->where('transaction_code', $code)->first();
    }

    public function findByIdempotencyKey(string $key, int $userId): ?Transaction
    {
        return Transaction::query()
            ->where('idempotency_key', $key)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('merchant_id', $userId);
            })
            ->first();
    }

    /**
     * Transaksi yang melibatkan wallet user (sebagai pengirim atau penerima).
     */
    public function queryForWallet(int $walletId)
    {
        return Transaction::query()
            ->with(['senderWallet.user', 'receiverWallet.user'])
            ->where(function ($query) use ($walletId) {
                $query->where('wallet_id', $walletId)
                    ->orWhere('sender_wallet_id', $walletId)
                    ->orWhere('receiver_wallet_id', $walletId);
            });
    }

    public function paginateForWallet(int $walletId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryForWallet($walletId);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['code'])) {
            $query->where('transaction_code', 'like', "%{$filters['code']}%");
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (($filters['direction'] ?? null) === 'in') {
            $query->where('receiver_wallet_id', $walletId);
        }

        if (($filters['direction'] ?? null) === 'out') {
            $query->where('sender_wallet_id', $walletId);
        }

        return $query->latest()->paginate($perPage);
    }

    public function recentForWallet(int $walletId, int $limit = 10): Collection
    {
        return $this->queryForWallet($walletId)->latest()->limit($limit)->get();
    }

    /**
     * Daftar transfer masuk yang masih pending untuk receiver.
     */
    public function pendingIncomingForWallet(int $walletId): Collection
    {
        return Transaction::query()
            ->where('type', Transaction::TYPE_TRANSFER)
            ->where('status', Transaction::STATUS_PENDING)
            ->where('receiver_wallet_id', $walletId)
            ->with(['senderWallet.user', 'receiverWallet.user', 'approval'])
            ->latest()
            ->get();
    }

    public function recentTopupsForMerchant(int $merchantUserId, int $limit = 10): Collection
    {
        return Transaction::query()
            ->where('merchant_id', $merchantUserId)
            ->where('type', Transaction::TYPE_TOPUP)
            ->with(['receiverWallet.user'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function paginateTopupsForMerchant(int $merchantUserId, int $perPage = 15): LengthAwarePaginator
    {
        return Transaction::query()
            ->where('merchant_id', $merchantUserId)
            ->where('type', Transaction::TYPE_TOPUP)
            ->with(['receiverWallet.user'])
            ->latest()
            ->paginate($perPage);
    }
}
