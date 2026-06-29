<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Repositories\WalletRepository;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    use ApiResponse;

    // 1. GET WALLET
    public function index(Request $request)
    {
        return $this->successResponse('Wallet berhasil diambil', [
            'wallet' => $request->user()->wallet
        ]);
    }

    // 2. BALANCE DETAIL
    public function balance(Request $request)
    {
        return $this->successResponse('Balance berhasil diambil', [
            'balance' => $request->user()->wallet->balance
        ]);
    }

    // 3. TOP UP
    public function topup(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000'
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        return DB::transaction(function () use ($request, $wallet, $user) {

            $before = $wallet->balance;
            $wallet->balance += $request->amount;
            $wallet->save();

            $trx = Transaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => 'topup',
                'amount' => $request->amount,
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'reference' => Str::uuid(),
                'status' => 'success',
                'description' => 'Top up wallet',
            ]);

            return $this->successResponse('Top up berhasil', [
                'transaction' => $trx,
                'wallet' => $wallet
            ]);
        });
    }

    // 4. WITHDRAW
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000'
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        if ($wallet->balance < $request->amount) {
            return $this->errorResponse('Saldo tidak cukup');
        }

        return DB::transaction(function () use ($request, $wallet, $user) {

            $before = $wallet->balance;
            $wallet->balance -= $request->amount;
            $wallet->save();

            $trx = Transaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => 'withdraw',
                'amount' => $request->amount,
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'reference' => Str::uuid(),
                'status' => 'success',
                'description' => 'Withdraw wallet',
            ]);

            return $this->successResponse('Withdraw berhasil', [
                'transaction' => $trx,
                'wallet' => $wallet
            ]);
        });
    }

    // 5. HISTORY
    public function history(Request $request): JsonResponse
    {
        $filters = $request->only([
            'type',
            'status',
            'direction',
            'code',
            'start_date',
            'end_date',
        ]);

        $perPage = (int) $request->input('per_page', 15);

        if ($perPage < 1) {
            $perPage = 15;
        }

        if ($perPage > 50) {
            $perPage = 50;
        }

        $paginated = app(\App\Services\TransactionService::class)
            ->paginateForUser($request->user(), $filters, $perPage);

        return $this->successResponse(
            'Riwayat transaksi.',
            TransactionResource::collection($paginated)->response()->getData(true)
        );
    }

    // 6. LEDGER (audit debit/credit per wallet)
    public function ledger(Request $request, WalletRepository $wallets)
    {
        $entries = $wallets->paginateLedger($request->user()->wallet->id);

        return $this->successResponse(
            'Ledger wallet',
            LedgerEntryResource::collection($entries)->response()->getData(true),
        );
    }
}
