<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OpenPayment\ApprovePaymentRequest;
use App\Http\Requests\Api\OpenPayment\InitiatePaymentRequest;
use App\Http\Requests\Api\OpenPayment\RejectPaymentRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use App\Services\TransferService;
use App\Support\Masker;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Open Payment API: lapisan standar payment initiation, approval, status
 * inquiry, balance inquiry, dan receipt (dokumentasi section 5.12 & 6).
 */
class OpenPaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TransferService $transfers,
        private readonly TransactionService $transactions,
    ) {
    }

    // POST /api/open-payment/initiate
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $transaction = $this->transfers->initiate(
            $request->user(),
            (string) $request->input('receiver_nfc_token'),
            (float) $request->input('amount'),
            $request->input('notes'),
            $request->header('Idempotency-Key'),
            $request->header('X-Device-ID'),
        );

        $receiver = $transaction->receiverWallet?->user;

        return $this->successResponse('Transaksi berhasil dibuat dan menunggu approval penerima.', [
            'transaction_id' => $transaction->id,
            'transaction_code' => $transaction->transaction_code,
            'status' => $transaction->status,
            'amount' => (float) $transaction->amount,
            'receiver' => $receiver ? [
                'name' => $receiver->name,
                'phone_masked' => Masker::phone($receiver->phone),
            ] : null,
            'expires_at' => $transaction->expires_at?->toISOString(),
        ], 201);
    }

    // POST /api/open-payment/approve
    public function approve(ApprovePaymentRequest $request): JsonResponse
    {
        $transaction = $this->transfers->approve(
            $request->user(),
            (int) $request->input('transaction_id'),
            (string) $request->input('pin'),
            $request->header('X-Device-ID') ?? $request->input('device_id'),
        );

        return $this->successResponse('Transfer berhasil diterima.', [
            'status' => $transaction->status,
            'receipt_url' => "/api/transactions/{$transaction->id}/receipt",
            'transaction' => new TransactionResource($transaction),
        ]);
    }

    // POST /api/open-payment/reject
    public function reject(RejectPaymentRequest $request): JsonResponse
    {
        $transaction = $this->transfers->reject(
            $request->user(),
            (int) $request->input('transaction_id'),
            $request->input('reason'),
        );

        return $this->successResponse('Transfer ditolak oleh penerima.', [
            'status' => $transaction->status,
        ]);
    }

    // GET /api/open-payment/status/{transaction_code}
    public function status(Request $request, string $transactionCode): JsonResponse
    {
        $transaction = $this->transactions->findByCodeForUser($request->user(), $transactionCode);

        return $this->successResponse('Status transaksi.', [
            'transaction_code' => $transaction->transaction_code,
            'status' => $transaction->status,
            'amount' => (float) $transaction->amount,
            'expires_at' => $transaction->expires_at?->toISOString(),
            'completed_at' => $transaction->completed_at?->toISOString(),
        ]);
    }

    // GET /api/open-payment/balance
    public function balance(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet;

        return $this->successResponse('Saldo wallet.', [
            'wallet_code' => $wallet->wallet_code,
            'balance' => (float) $wallet->balance,
            'currency' => $wallet->currency,
            'status' => $wallet->status,
        ]);
    }

    // GET /api/open-payment/receipt/{transaction_code}
    public function receipt(Request $request, string $transactionCode): JsonResponse
    {
        $transaction = $this->transactions->findByCodeForUser($request->user(), $transactionCode);
        $data = $this->transactions->receiptData($request->user(), $transaction->id);

        return $this->successResponse('Receipt transaksi.', ['receipt' => $data]);
    }
}
