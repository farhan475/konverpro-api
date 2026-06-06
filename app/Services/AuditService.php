<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public static function log(string $action, ?string $subjectType = null, ?string $subjectId = null, ?string $details = null): void
    {
        AuditLog::create([
            'id_user' => Auth::id(), 
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details,
            'ip_address' => Request::ip(),
        ]);
    }
}