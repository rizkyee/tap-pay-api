<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\Transaction;

/**
 * Membuat record receipt untuk transaksi yang sudah completed beserta hash
 * verifikasi. PDF/Image asli dibuat on-demand (lihat dokumentasi section 5.10);
 * di sini kita simpan metadata + hash yang bisa diverifikasi.
 */
class ReceiptService
{
    /**
     * Buat receipt untuk transaksi (hanya untuk transaksi completed).
     */
    public function generateFor(Transaction $transaction): Receipt
    {
        $existing = $transaction->receipt()->first();

        if ($existing) {
            return $existing;
        }

        $receiptNumber = Receipt::generateReceiptNumber();

        return $transaction->receipt()->create([
            'receipt_number' => $receiptNumber,
            'receipt_hash' => $this->buildHash($transaction, $receiptNumber),
        ]);
    }

    public function buildHash(Transaction $transaction, string $receiptNumber): string
    {
        return hash('sha256', implode('|', [
            $transaction->transaction_code,
            $receiptNumber,
            $transaction->amount,
            $transaction->total_amount,
            $transaction->completed_at?->toISOString(),
            config('app.key'),
        ]));
    }

    /**
     * Susun data receipt siap tampil (untuk JSON, image, atau print HTML).
     */
    public function buildReceiptData(Transaction $transaction): array
    {
        $transaction->loadMissing([
            'receipt',
            'senderWallet.user',
            'receiverWallet.user',
            'merchant',
        ]);

        $receipt = $transaction->receipt;

        return [
            'merchant_logo' => 'TapPay',
            'title' => $transaction->type === Transaction::TYPE_TOPUP
                ? 'Bukti Top Up'
                : 'Bukti Transfer',
            'receipt_number' => $receipt?->receipt_number,
            'transaction_code' => $transaction->transaction_code,
            'reference_number' => $transaction->reference,
            'type' => $transaction->type,
            'status' => $transaction->status,
            'date' => $transaction->created_at?->toISOString(),
            'completed_at' => $transaction->completed_at?->toISOString(),
            'sender_name' => $transaction->type === Transaction::TYPE_TOPUP
                ? ($transaction->merchant?->name ?? 'TapPay Merchant')
                : ($transaction->senderWallet?->user?->name),
            'receiver_name' => $transaction->receiverWallet?->user?->name,
            'amount' => (float) $transaction->amount,
            'fee' => (float) $transaction->fee,
            'total' => (float) ($transaction->total_amount ?? $transaction->amount),
            'notes' => $transaction->notes,
            'receipt_hash' => $receipt?->receipt_hash,
        ];
    }
}
