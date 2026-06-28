<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLedgerEntry extends Model
{
    use HasFactory;

    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'transaction_id',
        'wallet_id',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
