<?php

namespace App\Http\Controllers\Api\AdminPt;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Kampus;
use App\Models\User;
use App\Models\Prodi;
use App\Models\Pendaftar;
use App\Models\KurikulumMk;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $id_kampus = $user->id_kampus;

        if (!$id_kampus) {
            return response()->json(['message' => 'User tidak terasosiasi dengan kampus manapun.'], 403);
        }

        $kampus = Kampus::find($id_kampus);
        
        $stats = [
            'total_prodi' => Prodi::where('id_kampus', $id_kampus)->count(),
            'total_users' => User::where('id_kampus', $id_kampus)->count(),
            'total_pendaftar' => Pendaftar::where('id_kampus', $id_kampus)->count(),
            'pending_validasi' => Pendaftar::where('id_kampus', $id_kampus)->where('status', 'Pending Kaprodi')->count(),
            'approved' => Pendaftar::where('id_kampus', $id_kampus)->where('status', 'Approved')->count(),
            'revisi' => Pendaftar::where('id_kampus', $id_kampus)->where('status', 'Revisi')->count(),
            'rejected' => Pendaftar::where('id_kampus', $id_kampus)->where('status', 'Rejected')->count(),
            'avg_sks_diakui' => (float) Pendaftar::where('id_kampus', $id_kampus)->avg('total_sks_diakui') ?? 0.0,
            'total_mk' => KurikulumMk::whereHas('prodi', function ($query) use ($id_kampus) {
                $query->where('id_kampus', $id_kampus);
            })->count(),
        ];

        $status_breakdown = Pendaftar::where('id_kampus', $id_kampus)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->orderBy('total', 'desc')
            ->get();

        $prodi_performance = Prodi::where('id_kampus', $id_kampus)\n            ->with(['kaprodi'])\n            ->withCount(['pendaftar as total_pendaftar'])\n            ->withCount(['pendaftar as pending_validasi' => function ($query) {\n                $query->where('status', 'Pending Kaprodi');\n            }])\n            ->withCount(['pendaftar as approved' => function ($query) {\n                $query->where('status', 'Approved');\n            }])\n            ->withCount(['pendaftar as butuh_tindak_lanjut' => function ($query) {\n                $query->whereIn('status', ['Revisi', 'Rejected']);\n            }])\n            ->get()\n            ->map(function ($prodi) {\n                $prodi->avg_sks_diakui = (float) Pendaftar::where('id_prodi', $prodi->id)->avg('total_sks_diakui') ?? 0.0;\n                return $prodi;\n            });\n\n        $registration_chart = Pendaftar::where('id_kampus', $id_kampus)\n            ->select(\n                DB::raw('DATE_FORMAT(created_at, \"%Y-%m\") as month'),\n                DB::raw('COUNT(*) as total')\n            )\n            ->groupBy('month')\n            ->orderBy('month', 'asc')\n            ->get();\n\n        $ai_summary = [\n            'total_reference_keywords' => DB::table('mk_referensi_ai')->count(),\n            'total_described_courses' => KurikulumMk::whereHas('prodi', function($q) use ($id_kampus) { $q->where('id_kampus', $id_kampus); })->whereNotNull('deskripsi_singkat')->count(),\n            'total_courses' => $stats['total_mk'],\n        ];\n\n        return response()->json([\n            'kampus' => $kampus,\n            'stats' => $stats,\n            'status_breakdown' => $status_breakdown,\n            'prodi_performance' => $prodi_performance,\n            'registration_chart' => $registration_chart,\n            'ai_summary' => $ai_summary,\n            'nama_admin' => $user->nama_lengkap,\n        ], 200);\n    }
}
