<?php

namespace App\Services;

use App\Exceptions\TapPayException;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Repositories\WalletRepository;

/**
 * Inti pergerakan saldo. Semua kredit/debit WAJIB lewat service ini supaya
 * saldo wallet dan ledger selalu konsisten (lihat dokumentasi section 11).
 *
 * Catatan: method debit/credit di sini diasumsikan dipanggil di dalam
 * DB::transaction() oleh pemanggil agar bersifat atomic.
 */
class WalletService
{
    public function __construct(
        private readonly WalletRepository $wallets,
    ) {
    }

    /**
     * Kredit (menambah) saldo wallet dan mencatat ledger entry.
     */
    public function credit(int $walletId, float $amount, Transaction $transaction, ?string $description = null): WalletLedgerEntry
    {
        $wallet = $this->wallets->lockById($walletId);

        $this->assertActive($wallet);

        $before = (float) $wallet->balance;
        $after = $before + $amount;

        $wallet->balance = $after;
        $wallet->save();

        return $this->wallets->recordLedgerEntry([
            'transaction_id' => $transaction->id,
            'wallet_id' => $wallet->id,
            'direction' => WalletLedgerEntry::DIRECTION_CREDIT,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'description' => $description,
        ]);
    }

    /**
     * Debit (mengurangi) saldo wallet dan mencatat ledger entry.
     * Akan melempar exception bila saldo tidak cukup.
     */
    public function debit(int $walletId, float $amount, Transaction $transaction, ?string $description = null): WalletLedgerEntry
    {
        $wallet = $this->wallets->lockById($walletId);

        $this->assertActive($wallet);

        $before = (float) $wallet->balance;

        if ($before < $amount) {
            throw TapPayException::insufficientBalance();
        }

        $after = $before - $amount;

        $wallet->balance = $after;
        $wallet->save();

        return $this->wallets->recordLedgerEntry([
            'transaction_id' => $transaction->id,
            'wallet_id' => $wallet->id,
            'direction' => WalletLedgerEntry::DIRECTION_DEBIT,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'description' => $description,
        ]);
    }

    public function currentBalance(int $walletId): float
    {
        return (float) Wallet::query()->whereKey($walletId)->value('balance');
    }

    private function assertActive(Wallet $wallet): void
    {
        if (! $wallet->isActive()) {
            throw new TapPayException(
                'WALLET_INACTIVE',
                'Wallet tidak aktif sehingga transaksi tidak dapat diproses.',
                422,
            );
        }
    }
}
