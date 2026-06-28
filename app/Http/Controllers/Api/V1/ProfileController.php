<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\Profile\SetPinRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuditLogService $audit,
    ) {
    }

    // GET /api/profile
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse('Profil user.', [
            'user' => new UserResource($request->user()->load('wallet')),
            'has_transaction_pin' => $request->user()->hasTransactionPin(),
        ]);
    }

    // PATCH /api/profile
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return $this->successResponse('Profil berhasil diperbarui.', [
            'user' => new UserResource($user->load('wallet')),
        ]);
    }

    // POST /api/profile/change-password
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse('Password lama tidak sesuai.', [
                'current_password' => ['Password lama tidak sesuai.'],
            ], 422);
        }

        $user->forceFill(['password' => $validated['password']])->save();

        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        $this->audit->log($user->id, 'profile.password_changed', 'user', $user->id);

        return $this->successResponse('Password berhasil diperbarui.');
    }

    // POST /api/profile/set-pin
    public function setPin(SetPinRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['transaction_pin' => $request->input('pin')])->save();

        $this->audit->log($user->id, 'profile.pin_set', 'user', $user->id);

        return $this->successResponse('PIN transaksi berhasil diatur.');
    }

    // GET /api/profile/devices
    public function devices(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;

        $devices = $request->user()->tokens()
            ->latest('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'created_at' => $token->created_at?->toISOString(),
                'current' => $token->id === $currentTokenId,
            ]);

        return $this->successResponse('Daftar device aktif.', ['devices' => $devices]);
    }

    // POST /api/profile/logout-all-devices
    public function logoutAllDevices(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->successResponse('Berhasil logout dari semua device.');
    }
}
