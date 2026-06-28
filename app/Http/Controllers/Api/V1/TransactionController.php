<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TransactionService $transactions,
    ) {
    }

    // GET /api/transactions
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'type', 'status', 'direction', 'code', 'start_date', 'end_date',
        ]);

        $paginated = $this->transactions->paginateForUser($request->user(), $filters);

        return $this->successResponse(
            'Daftar transaksi.',
            TransactionResource::collection($paginated)->response()->getData(true),
        );
    }

    // GET /api/transactions/recent
    public function recent(Request $request): JsonResponse
    {
        $items = $this->transactions->recentForUser($request->user());

        return $this->successResponse('Transaksi terbaru.', [
            'transactions' => TransactionResource::collection($items),
        ]);
    }

    // GET /api/transactions/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = $this->transactions->findForUser($request->user(), $id);

        return $this->successResponse('Detail transaksi.', [
            'transaction' => new TransactionResource($transaction),
        ]);
    }

    // GET /api/transactions/{id}/receipt
    public function receipt(Request $request, int $id): JsonResponse
    {
        $data = $this->transactions->receiptData($request->user(), $id);

        return $this->successResponse('Receipt transaksi.', ['receipt' => $data]);
    }

    // GET /api/transactions/{id}/receipt-image
    public function receiptImage(Request $request, int $id): JsonResponse
    {
        $data = $this->transactions->receiptData($request->user(), $id);

        // Payload data siap dirender menjadi image di sisi client / printer.
        return $this->successResponse('Data receipt untuk share image.', [
            'format' => 'image-payload',
            'receipt' => $data,
        ]);
    }

    // GET /api/transactions/{id}/print  (HTML printable view)
    public function print(Request $request, int $id): View
    {
        $data = $this->transactions->receiptData($request->user(), $id);

        return view('receipts.print', ['receipt' => $data]);
    }

    // GET /api/transactions/{id}/verify
    public function verify(Request $request, int $id): JsonResponse
    {
        $result = $this->transactions->verifyReceipt($request->user(), $id);

        return $this->successResponse('Hasil verifikasi receipt.', $result);
    }
}
