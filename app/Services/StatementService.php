<?php

namespace App\Services;

use App\Exceptions\TapPayException;
use App\Models\Statement;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Repositories\StatementRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Pembuatan e-statement (dokumentasi section 5.11 & 3.10).
 * Menghitung opening balance, total debit, total credit, closing balance
 * berdasarkan ledger entries pada periode tertentu.
 */
class StatementService
{
    public function __construct(
        private readonly StatementRepository $statements,
        private readonly AuditLogService $audit,
    ) {
    }

    public function generate(User $user, string $startDate, string $endDate, string $type = Statement::TYPE_ALL): Statement
    {
        $wallet = $user->wallet;

        if (! $wallet) {
            throw TapPayException::notFound('Wallet tidak ditemukan.');
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        if ($start->gt($end)) {
            throw TapPayException::validation('Tanggal mulai tidak boleh setelah tanggal akhir.');
        }

        $opening = $this->statements->openingBalance($wallet->id, $start);

        $entries = $this->statements->ledgerInPeriod($wallet->id, $start, $end)->get();

        $totalDebit = (float) $entries
            ->where('direction', WalletLedgerEntry::DIRECTION_DEBIT)
            ->sum('amount');
        $totalCredit = (float) $entries
            ->where('direction', WalletLedgerEntry::DIRECTION_CREDIT)
            ->sum('amount');

        $closing = $opening + $totalCredit - $totalDebit;

        $statement = $this->statements->create([
            'user_id' => $user->id,
            'statement_number' => Statement::generateStatementNumber(),
            'type' => $type,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'opening_balance' => $opening,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => $closing,
            'total_transactions' => $entries->count(),
        ]);

        $this->audit->log($user->id, 'statement.generated', 'statement', $statement->id, [
            'period' => "{$start->toDateString()} - {$end->toDateString()}",
        ]);

        return $statement;
    }

    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->statements->paginateForUser($user->id, $perPage);
    }

    public function findForUser(User $user, int $id): Statement
    {
        $statement = $this->statements->findForUser($id, $user->id);

        if (! $statement) {
            throw TapPayException::notFound('Statement tidak ditemukan.');
        }

        return $statement;
    }

    /**
     * Susun data lengkap statement termasuk daftar transaksi (untuk download/print).
     */
    public function buildStatementData(User $user, Statement $statement): array
    {
        $wallet = $user->wallet;

        $entries = $this->statements
            ->ledgerInPeriod(
                $wallet->id,
                Carbon::parse($statement->start_date)->startOfDay(),
                Carbon::parse($statement->end_date)->endOfDay(),
            )
            ->get()
            ->map(function (WalletLedgerEntry $entry) {
                return [
                    'date' => $entry->created_at?->toISOString(),
                    'transaction_code' => $entry->transaction?->transaction_code,
                    'type' => $entry->transaction?->type,
                    'direction' => $entry->direction,
                    'amount' => (float) $entry->amount,
                    'balance_after' => (float) $entry->balance_after,
                    'description' => $entry->description,
                ];
            });

        return [
            'statement_number' => $statement->statement_number,
            'user' => [
                'name' => $user->name,
                'wallet_code' => $wallet->wallet_code,
                'role' => $user->role,
            ],
            'period' => [
                'start_date' => $statement->start_date->toDateString(),
                'end_date' => $statement->end_date->toDateString(),
            ],
            'opening_balance' => (float) $statement->opening_balance,
            'total_credit' => (float) $statement->total_credit,
            'total_debit' => (float) $statement->total_debit,
            'closing_balance' => (float) $statement->closing_balance,
            'total_transactions' => $statement->total_transactions,
            'generated_at' => $statement->created_at?->toISOString(),
            'transactions' => $entries,
        ];
    }
}
