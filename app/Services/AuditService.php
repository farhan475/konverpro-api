<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $details = null
    ): void {
        $authId = Auth::id();
        $this->logAs(
            is_string($authId) ? $authId : null,
            $action,
            $subjectType,
            $subjectId,
            $details,
            Request::ip()
        );
    }

    public function logAs(
        ?string $userId,
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $details = null,
        ?string $ipAddress = null
    ): void {
        AuditLog::create([
            'id_user' => $userId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details,
            'ip_address' => $ipAddress ?? Request::ip(),
        ]);
    }
}
