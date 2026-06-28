<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'receipt_number',
        'pdf_path',
        'image_path',
        'receipt_hash',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public static function generateReceiptNumber(): string
    {
        do {
            $number = 'RCP-' . now()->format('Ymd') . '-' . random_int(100000, 999999);
        } while (self::query()->where('receipt_number', $number)->exists());

        return $number;
    }
}
