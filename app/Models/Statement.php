<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Statement extends Model
{
    use HasFactory;

    public const TYPE_ALL = 'all';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_TOPUP = 'topup';
    public const TYPE_INCOMING = 'incoming';
    public const TYPE_OUTGOING = 'outgoing';

    protected $fillable = [
        'user_id',
        'statement_number',
        'type',
        'start_date',
        'end_date',
        'opening_balance',
        'total_debit',
        'total_credit',
        'closing_balance',
        'total_transactions',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'opening_balance' => 'decimal:2',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateStatementNumber(): string
    {
        do {
            $number = 'STM-' . now()->format('Ymd') . '-' . random_int(100000, 999999);
        } while (self::query()->where('statement_number', $number)->exists());

        return $number;
    }
}
