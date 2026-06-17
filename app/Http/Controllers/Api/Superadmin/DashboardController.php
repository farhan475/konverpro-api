<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Models\Prodi;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $aiApiKey = PengaturanGlobal::get('sumopod_api_key');

        return $this->successResponse([
            'stats' => [
                'total_user' => User::count(),
                'total_prodi' => Prodi::count(),
                'total_pendaftar' => Pendaftar::count(),
                'total_audit' => AuditLog::count(),
            ],
            'ai_status' => [
                'configured' => !empty($aiApiKey),
                'model' => PengaturanGlobal::get('sumopod_model', 'gpt-4o-mini'),
            ],
            'recent_audits' => AuditLog::with('user')->latest()->limit(10)->get(),
        ]);
    }
}
