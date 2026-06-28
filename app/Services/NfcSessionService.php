<?php

namespace App\Services;

use App\Exceptions\TapPayException;
use App\Models\NfcSession;
use App\Models\User;
use App\Repositories\NfcSessionRepository;

class NfcSessionService
{
    /**
     * Masa berlaku token NFC (detik). Token bersifat temporary & single-use.
     */
    private const TTL_SECONDS = 300;

    public function __construct(
        private readonly NfcSessionRepository $sessions,
        private readonly AuditLogService $audit,
    ) {
    }

    /**
     * Receiver membuka receive payment mode -> backend membuat token sementara.
     */
    public function createReceiveSession(User $user): NfcSession
    {
        $wallet = $user->wallet;

        if (! $wallet || ! $wallet->isActive()) {
            throw new TapPayException('WALLET_INACTIVE', 'Wallet tidak aktif.', 422);
        }

        // Hanya boleh ada satu sesi aktif per user.
        $this->sessions->expireActiveForUser($user->id);

        $session = $this->sessions->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'token' => NfcSession::generateToken(),
            'mode' => NfcSession::MODE_RECEIVE,
            'status' => NfcSession::STATUS_ACTIVE,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        $this->audit->log($user->id, 'nfc.session.created', 'nfc_session', $session->id);

        return $session;
    }

    public function findByTokenOrFail(string $token): NfcSession
    {
        $session = $this->sessions->findByToken($token);

        if (! $session) {
            throw TapPayException::notFound('Sesi NFC tidak ditemukan.');
        }

        $this->syncExpiry($session);

        return $session;
    }

    /**
     * Ambil sesi yang masih usable, dipakai saat initiate payment.
     */
    public function resolveUsableToken(string $token): NfcSession
    {
        $session = $this->findByTokenOrFail($token);

        if (! $session->isUsable()) {
            throw TapPayException::nfcTokenExpired();
        }

        return $session;
    }

    public function refresh(int $id, User $user): NfcSession
    {
        $session = $this->ownedSessionOrFail($id, $user);

        $session->update([
            'status' => NfcSession::STATUS_ACTIVE,
            'token' => NfcSession::generateToken(),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
            'used_at' => null,
        ]);

        $this->audit->log($user->id, 'nfc.session.refreshed', 'nfc_session', $session->id);

        return $session;
    }

    public function cancel(int $id, User $user): NfcSession
    {
        $session = $this->ownedSessionOrFail($id, $user);

        $session->update(['status' => NfcSession::STATUS_CANCELLED]);

        $this->audit->log($user->id, 'nfc.session.cancelled', 'nfc_session', $session->id);

        return $session;
    }

    /**
     * Tandai token sudah dipakai (single-use) setelah dipakai untuk initiate.
     */
    public function markUsed(NfcSession $session): void
    {
        $session->update([
            'status' => NfcSession::STATUS_USED,
            'used_at' => now(),
        ]);
    }

    private function ownedSessionOrFail(int $id, User $user): NfcSession
    {
        $session = $this->sessions->findById($id);

        if (! $session || $session->user_id !== $user->id) {
            throw TapPayException::notFound('Sesi NFC tidak ditemukan.');
        }

        return $session;
    }

    private function syncExpiry(NfcSession $session): void
    {
        if ($session->status === NfcSession::STATUS_ACTIVE && $session->isExpired()) {
            $session->update(['status' => NfcSession::STATUS_EXPIRED]);
        }
    }
}
