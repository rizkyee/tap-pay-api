<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Repositories\AuditLogRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;

class AuditLogService
{
    public function __construct(
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    /**
     * Catat aktivitas backend untuk kebutuhan audit (login, topup, transfer, dll).
     */
    public function log(
        ?int $userId,
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $payload = [],
        ?string $deviceId = null,
    ): AuditLog {
        /** @var Request $request */
        $request = RequestFacade::instance();

        return $this->auditLogs->create([
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'ip_address' => $request?->ip(),
            'device_id' => $deviceId ?? $request?->header('X-Device-ID'),
            'payload' => $payload ?: null,
            'created_at' => now(),
        ]);
    }
}
