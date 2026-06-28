<?php

namespace App\Repositories;

use App\Models\AuditLog;

class AuditLogRepository
{
    public function create(array $data): AuditLog
    {
        return AuditLog::query()->create($data);
    }
}
