<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Injectable instance method — gunakan ini via constructor injection.
     */
    public function log(
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $details = null
    ): void {
        AuditLog::create([
            'id_user'      => Auth::id(),
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'details'      => $details,
            'ip_address'   => Request::ip(),
        ]);
    }
}