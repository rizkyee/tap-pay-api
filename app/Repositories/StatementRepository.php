<?php

namespace App\Repositories;

use App\Models\Statement;
use App\Models\WalletLedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class StatementRepository
{
    public function create(array $data): Statement
    {
        return Statement::query()->create($data);
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Statement::query()
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }

    public function findForUser(int $id, int $userId): ?Statement
    {
        return Statement::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Saldo awal periode = saldo_after entri ledger terakhir sebelum start date.
     */
    public function openingBalance(int $walletId, Carbon $startDate): float
    {
        $entry = WalletLedgerEntry::query()
            ->where('wallet_id', $walletId)
            ->where('created_at', '<', $startDate)
            ->latest()
            ->first();

        return $entry ? (float) $entry->balance_after : 0.0;
    }

    public function ledgerInPeriod(int $walletId, Carbon $startDate, Carbon $endDate)
    {
        return WalletLedgerEntry::query()
            ->where('wallet_id', $walletId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('transaction')
            ->orderBy('created_at');
    }
}
