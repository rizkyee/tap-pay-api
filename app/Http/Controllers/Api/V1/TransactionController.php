<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Traits\ApiResponse;

class TransactionController extends Controller
{
    use ApiResponse;

    // =========================
    // 1. TRANSFER
    // =========================
    public function transfer(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1000',
            'fee' => 'nullable|numeric|min:0'
        ]);

        $sender = $request->user();
        $receiver = User::find($request->receiver_id);

        if (!$receiver) {
            return $this->errorResponse('Receiver tidak ditemukan');
        }

        $fee = $request->fee ?? 0;
        $total = $request->amount + $fee;

        if ($sender->wallet->balance < $total) {
            return $this->errorResponse('Saldo tidak cukup');
        }

        return DB::transaction(function () use ($sender, $receiver, $request, $fee, $total) {

            // deduct sender
            $senderWallet = $sender->wallet;
            $receiverWallet = $receiver->wallet;

            $senderBefore = $senderWallet->balance;
            $receiverBefore = $receiverWallet->balance;

            $senderWallet->balance -= $total;
            $receiverWallet->balance += $request->amount;

            $senderWallet->save();
            $receiverWallet->save();

            $trx = Transaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $request->amount,
                'fee' => $fee,
                'type' => 'transfer',
                'status' => 'success',
                'reference' => Str::uuid(),
                'description' => 'Transfer antar user'
            ]);

            return $this->successResponse('Transfer berhasil', [
                'transaction' => $trx
            ]);
        });
    }

    // =========================
    // 2. LIST TRANSACTIONS
    // =========================
    public function index(Request $request)
    {
        $data = Transaction::where('sender_id', $request->user()->id)
            ->orWhere('receiver_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return $this->successResponse('List transaksi', $data);
    }

    // =========================
    // 3. DETAIL TRANSAKSI
    // =========================
    public function show($id)
    {
        $trx = Transaction::find($id);

        if (!$trx) {
            return $this->errorResponse('Transaksi tidak ditemukan');
        }

        return $this->successResponse('Detail transaksi', $trx);
    }

    // =========================
    // 4. REFUND / REVERSAL
    // =========================
    public function refund(Request $request, $id)
    {
        $trx = Transaction::find($id);

        if (!$trx) {
            return $this->errorResponse('Transaksi tidak ditemukan');
        }

        if ($trx->type !== 'transfer') {
            return $this->errorResponse('Hanya transfer yang bisa direfund');
        }

        return DB::transaction(function () use ($trx) {

            $sender = User::find($trx->sender_id);
            $receiver = User::find($trx->receiver_id);

            // rollback wallet
            $sender->wallet->balance += $trx->amount;
            $receiver->wallet->balance -= $trx->amount;

            $sender->wallet->save();
            $receiver->wallet->save();

            $trx->update([
                'status' => 'refunded',
                'description' => 'Refund transaction'
            ]);

            return $this->successResponse('Refund berhasil', $trx);
        });
    }
}