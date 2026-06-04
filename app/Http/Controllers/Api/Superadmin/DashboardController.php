<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Kampus;
use App\Models\Pendaftar;
use App\Models\AuditLog;
use App\Models\Prodi;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_kampus' => Kampus::count(),
            'total_mahasiswa_approved' => Pendaftar::where('status', 'Approved')->count(),
            'total_pendaftar_global' => Pendaftar::count(),
            'total_prodi' => Prodi::count(),
        ];

        $recent_activities = AuditLog::with(['user', 'kampus'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $kampus_terbaru = Kampus::withCount('pendaftar')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $top_mitra = Kampus::withCount('pendaftar')
            ->orderBy('pendaftar_count', 'desc')
            ->limit(5)
            ->get();

        $registration_chart = Pendaftar::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_activities' => $recent_activities,
                'kampus_terbaru' => $kampus_terbaru,
                'top_mitra' => $top_mitra,
                'charts' => [
                    'registration' => $registration_chart
                ]
            ]
        ], 200);
    }
}
