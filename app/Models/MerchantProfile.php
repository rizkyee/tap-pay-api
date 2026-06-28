<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantProfile extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'merchant_code',
        'business_name',
        'business_address',
        'business_id_number',
        'merchant_status',
        'daily_topup_limit',
        'single_topup_limit',
    ];

    protected function casts(): array
    {
        return [
            'daily_topup_limit' => 'decimal:2',
            'single_topup_limit' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->merchant_status === self::STATUS_ACTIVE;
    }

    public static function generateMerchantCode(): string
    {
        do {
            $code = 'MRC' . now()->format('ym') . random_int(100000, 999999);
        } while (self::query()->where('merchant_code', $code)->exists());

        return $code;
    }
}
