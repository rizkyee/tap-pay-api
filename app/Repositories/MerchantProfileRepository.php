<?php

namespace App\Repositories;

use App\Models\MerchantProfile;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class MerchantProfileRepository
{
    public function findByUserId(int $userId): ?MerchantProfile
    {
        return MerchantProfile::query()->where('user_id', $userId)->first();
    }

    public function firstOrCreateForUser(int $userId, array $attributes): MerchantProfile
    {
        return MerchantProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            $attributes,
        );
    }

    public function sumTopupToday(int $merchantUserId): float
    {
        return (float) Transaction::query()
            ->where('merchant_id', $merchantUserId)
            ->where('type', Transaction::TYPE_TOPUP)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
    }

    public function countTopupToday(int $merchantUserId): int
    {
        return Transaction::query()
            ->where('merchant_id', $merchantUserId)
            ->where('type', Transaction::TYPE_TOPUP)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', Carbon::today())
            ->count();
    }
}
