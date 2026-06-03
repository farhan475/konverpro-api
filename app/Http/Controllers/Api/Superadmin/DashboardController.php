<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Kampus;
use App\Models\Pendaftar;
use App\Models\TransaksiSaldo;
use App\Models\AuditLog;
use App\Models\Prodi;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_kampus' => Kampus::count(),
            'total_mahasiswa_approved' => Pendaftar::where('status', 'Approved')->count(),
            'pending_topup' => TransaksiSaldo::where('jenis_transaksi', 'topup')->where('status', 'pending')->count(),
            'total_pendaftar_global' => Pendaftar::count(),
            'total_revenue' => (float) TransaksiSaldo::where('status', 'success')->where('jenis_transaksi', 'topup')->sum('nominal'),
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

        $top_mitra = Kampus::withCount('pendaftar')\n            ->orderBy('pendaftar_count', 'desc')\n            ->limit(5)\n            ->get();\n\n        $revenue_chart = TransaksiSaldo::where('status', 'success')\n            ->where('jenis_transaksi', 'topup')\n            ->select(\n                DB::raw('DATE_FORMAT(created_at, \"%Y-%m\") as month'),\n                DB::raw('SUM(nominal) as total')\n            )\n            ->groupBy('month')\n            ->orderBy('month', 'asc')\n            ->get();\n\n        $registration_chart = Pendaftar::select(\n                DB::raw('DATE_FORMAT(created_at, \"%Y-%m\") as month'),\n                DB::raw('COUNT(*) as total')\n            )\n            ->groupBy('month')\n            ->orderBy('month', 'asc')\n            ->get();\n\n        return response()->json([\n            'success' => true,\n            'data' => [\n                'stats' => $stats,\n                'recent_activities' => $recent_activities,\n                'kampus_terbaru' => $kampus_terbaru,\n                'top_mitra' => $top_mitra,\n                'charts' => [\n                    'revenue' => $revenue_chart,\n                    'registration' => $registration_chart\n                ]\n            ]\n        ], 200);\n    }
}
