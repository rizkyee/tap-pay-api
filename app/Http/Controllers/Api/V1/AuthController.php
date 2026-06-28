<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated, $request) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'role' => $validated['role'],
                'is_active' => true,
            ]);

            $walletPrefix = $user->role === User::ROLE_MERCHANT ? 'TM' : 'TC';

            $user->wallet()->create([
                'wallet_code' => Wallet::generateWalletCode($walletPrefix),
                'balance' => 0,
                'currency' => 'TPP',
                'status' => Wallet::STATUS_ACTIVE,
            ]);

            $deviceName = $validated['device_name']
                ?? $request->userAgent()
                ?? 'api-token';

            $token = $user->createToken($deviceName)->plainTextToken;

            return [
                'user' => $user->load('wallet'),
                'token' => $token,
            ];
        });

        return $this->successResponse('Registrasi berhasil.', [
            'token_type' => 'Bearer',
            'access_token' => $result['token'],
            'user' => new UserResource($result['user']),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $identifier = $validated['identifier'];
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::query()
            ->where($field, $field === 'email' ? strtolower($identifier) : $identifier)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Email/nomor HP atau password salah.', null, 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Akun tidak aktif. Hubungi admin.', null, 403);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        if (! $user->wallet()->exists()) {
            $walletPrefix = $user->role === User::ROLE_MERCHANT ? 'TM' : 'TC';

            $user->wallet()->create([
                'wallet_code' => Wallet::generateWalletCode($walletPrefix),
                'balance' => 0,
                'currency' => 'TPP',
                'status' => Wallet::STATUS_ACTIVE,
            ]);
        }

        $deviceName = $validated['device_name']
            ?? $request->userAgent()
            ?? 'api-token';

        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->successResponse('Login berhasil.', [
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => new UserResource($user->load('wallet')),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse('Data user aktif berhasil diambil.', [
            'user' => new UserResource($request->user()->load('wallet')),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill($validated);
        $user->save();

        return $this->successResponse('Profil berhasil diperbarui.', [
            'user' => new UserResource($user->load('wallet')),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse('Password lama tidak sesuai.', [
                'current_password' => ['Password lama tidak sesuai.'],
            ], 422);
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        // Setelah password berubah, hapus token lain agar akun lebih aman.
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken?->id;

        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        return $this->successResponse('Password berhasil diperbarui.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->successResponse('Logout berhasil.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->successResponse('Logout dari semua device berhasil.');
    }
}
