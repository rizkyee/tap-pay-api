<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OpenPayment\InitiatePaymentRequest;
use App\Http\Requests\Api\Transfer\ApproveTransferRequest;
use App\Http\Requests\Api\Transfer\RejectTransferRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use App\Services\TransferService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TransferService $transfers,
        private readonly TransactionService $transactions,
    ) {
    }

    // POST /api/transfers/nfc  (alias dari open-payment/initiate)
    public function nfc(InitiatePaymentRequest $request): JsonResponse
    {
        $transaction = $this->transfers->initiate(
            $request->user(),
            (string) $request->input('receiver_nfc_token'),
            (float) $request->input('amount'),
            $request->input('notes'),
            $request->header('Idempotency-Key'),
            $request->header('X-Device-ID'),
        );

        return $this->successResponse('Transfer NFC dibuat dan menunggu approval penerima.', [
            'transaction' => new TransactionResource($transaction),
        ], 201);
    }

    // GET /api/transfers/pending-incoming
    public function pendingIncoming(Request $request): JsonResponse
    {
        $items = $this->transactions->pendingIncomingForUser($request->user());

        return $this->successResponse('Daftar transfer masuk yang menunggu approval.', [
            'transfers' => TransactionResource::collection($items),
        ]);
    }

    // GET /api/transfers/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = $this->transactions->findForUser($request->user(), $id);

        return $this->successResponse('Detail transfer.', [
            'transaction' => new TransactionResource($transaction),
        ]);
    }

    // POST /api/transfers/{id}/approve
    public function approve(ApproveTransferRequest $request, int $id): JsonResponse
    {
        $transaction = $this->transfers->approve(
            $request->user(),
            $id,
            (string) $request->input('pin'),
            $request->header('X-Device-ID') ?? $request->input('device_id'),
        );

        return $this->successResponse('Transfer berhasil diterima.', [
            'transaction' => new TransactionResource($transaction),
            'receipt_url' => "/api/transactions/{$transaction->id}/receipt",
        ]);
    }

    // POST /api/transfers/{id}/reject
    public function reject(RejectTransferRequest $request, int $id): JsonResponse
    {
        $transaction = $this->transfers->reject($request->user(), $id, $request->input('reason'));

        return $this->successResponse('Transfer ditolak.', [
            'transaction' => new TransactionResource($transaction),
        ]);
    }

    // POST /api/transfers/{id}/cancel
    public function cancel(Request $request, int $id): JsonResponse
    {
        $transaction = $this->transfers->cancel($request->user(), $id);

        return $this->successResponse('Transfer dibatalkan.', [
            'transaction' => new TransactionResource($transaction),
        ]);
    }
}
