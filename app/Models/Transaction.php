<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    public const TYPE_TOPUP = 'topup';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_WITHDRAW = 'withdraw';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    // Status legacy yang dipakai endpoint wallet lama.
    public const STATUS_SUCCESS = 'success';

    protected $fillable = [
        'transaction_code',
        'payment_code',
        'user_id',
        'wallet_id',
        'sender_wallet_id',
        'receiver_wallet_id',
        'merchant_id',
        'type',
        'amount',
        'fee',
        'total_amount',
        'balance_before',
        'balance_after',
        'reference',
        'idempotency_key',
        'status',
        'description',
        'notes',
        'expires_at',
        'approved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            // Pastikan foreign key selalu integer. Sebagian driver/konfigurasi
            // PDO (mis. emulate prepares di shared hosting) mengembalikan kolom
            // angka sebagai string, sehingga perbandingan ketat (===/!==) gagal.
            'user_id' => 'integer',
            'wallet_id' => 'integer',
            'sender_wallet_id' => 'integer',
            'receiver_wallet_id' => 'integer',
            'merchant_id' => 'integer',
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function senderWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    public function receiverWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'receiver_wallet_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    public function approval(): HasOne
    {
        return $this->hasOne(TransactionApproval::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public static function generateCode(string $type): string
    {
        $prefix = $type === self::TYPE_TOPUP ? 'TPU' : 'TPP';

        do {
            $code = $prefix . '-' . now()->format('Ymd') . '-'
                . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::query()->where('transaction_code', $code)->exists());

        return $code;
    }
}
