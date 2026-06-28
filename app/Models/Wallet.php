<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_FROZEN = 'frozen';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'wallet_code',
        'balance',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateWalletCode(string $prefix = 'TP'): string
    {
        do {
            $code = strtoupper($prefix)
                . now()->format('ymd')
                . random_int(100000, 999999);
        } while (self::query()->where('wallet_code', $code)->exists());

        return $code;
    }
}
