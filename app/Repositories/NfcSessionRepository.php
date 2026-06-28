<?php

namespace App\Repositories;

use App\Models\NfcSession;

class NfcSessionRepository
{
    public function create(array $data): NfcSession
    {
        return NfcSession::query()->create($data);
    }

    public function findByToken(string $token): ?NfcSession
    {
        return NfcSession::query()->where('token', $token)->first();
    }

    public function findById(int $id): ?NfcSession
    {
        return NfcSession::query()->find($id);
    }

    /**
     * Tandai semua sesi aktif milik user sebagai expired (single active session).
     */
    public function expireActiveForUser(int $userId): void
    {
        NfcSession::query()
            ->where('user_id', $userId)
            ->where('status', NfcSession::STATUS_ACTIVE)
            ->update(['status' => NfcSession::STATUS_EXPIRED]);
    }
}
