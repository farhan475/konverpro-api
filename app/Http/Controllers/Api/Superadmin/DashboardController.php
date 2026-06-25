<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HasilKonversi;
use App\Models\Pendaftar;
use App\Models\PendaftarAppeal;
use App\Models\PengaturanGlobal;
use App\Models\Prodi;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $aiApiKey = PengaturanGlobal::get('sumopod_api_key');
        $approved = Pendaftar::where('status', 'Approved');
        $averageValue = Pendaftar::whereNotNull('approved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, approved_at)) as average_hours')
            ->value('average_hours');
        $averageHours = is_numeric($averageValue) ? (float) $averageValue : 0.0;
        $mappedTotal = HasilKonversi::where('is_unmatched', false)->count();
        $manualTotal = HasilKonversi::where('metode_pemetaan', 'Manual Kaprodi')->count();

        return $this->successResponse([
            'stats' => [
                'total_user' => User::count(),
                'total_prodi' => Prodi::count(),
                'total_pendaftar' => Pendaftar::count(),
                'total_audit' => AuditLog::count(),
            ],
            'ai_status' => [
                'configured' => ! empty($aiApiKey),
                'model' => PengaturanGlobal::get('sumopod_model', 'gpt-4o-mini'),
            ],
            'quality' => [
                'average_processing_hours' => round($averageHours, 1),
                'manual_override_rate' => $mappedTotal > 0 ? round(($manualTotal / $mappedTotal) * 100, 1) : 0,
                'approved_count' => (clone $approved)->count(),
                'ba_whatsapp_delivery_rate' => (clone $approved)->count() > 0
                    ? round(((clone $approved)->whereNotNull('ba_wa_sent_at')->count() / (clone $approved)->count()) * 100, 1)
                    : 0,
                'open_appeals' => PendaftarAppeal::where('status', 'submitted')->count(),
                'unmatched_courses' => HasilKonversi::where('is_unmatched', true)->count(),
                'monthly_approved' => Pendaftar::where('status', 'Approved')
                    ->where('approved_at', '>=', now()->subMonths(5)->startOfMonth())
                    ->selectRaw("DATE_FORMAT(approved_at, '%Y-%m') as month, COUNT(*) as total")
                    ->groupBy(DB::raw("DATE_FORMAT(approved_at, '%Y-%m')"))
                    ->orderBy('month')
                    ->get(),
            ],
            'recent_audits' => AuditLog::with('user')->latest()->limit(10)->get(),
        ]);
    }
}
