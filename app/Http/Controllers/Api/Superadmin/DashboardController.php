<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kampus;
use App\Models\Pendaftar;
use App\Models\TransaksiSaldo;
use App\Models\AuditLog;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_kampus' => Kampus::count(),
            'total_mahasiswa_approved' => Pendaftar::where('status', 'Approved')->count(),
            'pending_topup' => TransaksiSaldo::where('status', 'pending')->count(),
            'total_pendaftar_global' => Pendaftar::count(),
        ];

        $recent_activities = AuditLog::with(['user', 'kampus'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $kampus_terbaru = Kampus::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_activities' => $recent_activities,
            'kampus_terbaru' => $kampus_terbaru,
        ], 200);
    }
}
