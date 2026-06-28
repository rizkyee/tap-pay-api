<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Merchant\MerchantTopUpRequest;
use App\Http\Resources\TransactionResource;
use App\Repositories\TransactionRepository;
use App\Services\MerchantService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MerchantService $merchants,
        private readonly TransactionRepository $transactions,
    ) {
    }

    // GET /api/merchant/summary
    public function summary(Request $request): JsonResponse
    {
        return $this->successResponse('Ringkasan merchant.', $this->merchants->summary($request->user()));
    }

    // GET /api/merchant/limits
    public function limits(Request $request): JsonResponse
    {
        return $this->successResponse('Limit merchant.', $this->merchants->limits($request->user()));
    }

    // POST /api/merchant/topups
    public function topUp(MerchantTopUpRequest $request): JsonResponse
    {
        $transaction = $this->merchants->topUp(
            $request->user(),
            (string) $request->input('customer_phone'),
            (float) $request->input('amount'),
            $request->input('notes'),
            $request->header('Idempotency-Key'),
        );

        return $this->successResponse('Top up berhasil.', [
            'transaction' => new TransactionResource($transaction),
            'new_balance' => (float) ($transaction->receiverWallet?->balance ?? 0),
            'receipt_url' => "/api/transactions/{$transaction->id}/receipt",
        ], 201);
    }

    // GET /api/merchant/topups
    public function topUps(Request $request): JsonResponse
    {
        $paginated = $this->transactions->paginateTopupsForMerchant($request->user()->id);

        return $this->successResponse(
            'Riwayat top up merchant.',
            TransactionResource::collection($paginated)->response()->getData(true),
        );
    }

    // GET /api/merchant/topups/recent
    public function recentTopUps(Request $request): JsonResponse
    {
        $items = $this->transactions->recentTopupsForMerchant($request->user()->id);

        return $this->successResponse('Top up terbaru merchant.', [
            'topups' => TransactionResource::collection($items),
        ]);
    }
}
