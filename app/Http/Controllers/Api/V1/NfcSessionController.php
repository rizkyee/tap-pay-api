<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Nfc\CreateNfcSessionRequest;
use App\Http\Resources\NfcSessionResource;
use App\Services\NfcSessionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NfcSessionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NfcSessionService $nfcSessions,
    ) {
    }

    // POST /api/nfc/sessions
    public function store(CreateNfcSessionRequest $request): JsonResponse
    {
        $session = $this->nfcSessions->createReceiveSession($request->user());

        return $this->successResponse('Sesi NFC berhasil dibuat.', [
            'session' => new NfcSessionResource($session),
        ], 201);
    }

    // GET /api/nfc/sessions/{token}
    public function show(string $token): JsonResponse
    {
        $session = $this->nfcSessions->findByTokenOrFail($token);

        return $this->successResponse('Detail sesi NFC.', [
            'session' => new NfcSessionResource($session),
        ]);
    }

    // POST /api/nfc/sessions/{id}/refresh
    public function refresh(Request $request, int $id): JsonResponse
    {
        $session = $this->nfcSessions->refresh($id, $request->user());

        return $this->successResponse('Token NFC berhasil diperbarui.', [
            'session' => new NfcSessionResource($session),
        ]);
    }

    // POST /api/nfc/sessions/{id}/cancel
    public function cancel(Request $request, int $id): JsonResponse
    {
        $session = $this->nfcSessions->cancel($id, $request->user());

        return $this->successResponse('Sesi NFC dibatalkan.', [
            'session' => new NfcSessionResource($session),
        ]);
    }
}
