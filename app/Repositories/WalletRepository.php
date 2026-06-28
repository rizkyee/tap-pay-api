<?php

namespace App\Repositories;

use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WalletRepository
{
    /**
     * Ambil wallet dengan pessimistic lock untuk operasi saldo yang atomic.
     */
    public function lockById(int $walletId): Wallet
    {
        return Wallet::query()->lockForUpdate()->findOrFail($walletId);
    }

    public function recordLedgerEntry(array $data): WalletLedgerEntry
    {
        return WalletLedgerEntry::query()->create($data);
    }

    public function paginateLedger(int $walletId, int $perPage = 15): LengthAwarePaginator
    {
        return WalletLedgerEntry::query()
            ->where('wallet_id', $walletId)
            ->latest()
            ->paginate($perPage);
    }
}
