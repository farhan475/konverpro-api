<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->successResponse([
            'stats' => [
                'total_user' => User::count(),
                'total_prodi' => Prodi::count(),
                'total_pendaftar' => Pendaftar::count(),
                'total_audit' => AuditLog::count(),
            ],
            'recent_audits' => AuditLog::with('user')->latest()->limit(10)->get(),
        ]);
    }
}
