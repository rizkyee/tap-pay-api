<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Services\MerchantService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MerchantService $merchants,
    ) {
    }

    // GET /api/customers/search?phone=...  atau  ?q=...
    public function search(Request $request): JsonResponse
    {
        $term = $request->input('phone')
            ?? $request->input('q')
            ?? $request->input('customer_id');

        if (! $term || strlen(trim((string) $term)) < 3) {
            return $this->errorResponse('Masukkan minimal 3 karakter untuk pencarian.', null, 422);
        }

        $customers = $this->merchants->searchCustomers(trim((string) $term));

        return $this->successResponse('Hasil pencarian customer.', [
            'customers' => CustomerResource::collection($customers),
        ]);
    }
}
