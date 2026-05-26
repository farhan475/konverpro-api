<?php

namespace App\Http\Controllers\Api\AdminPt;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pendaftar;
use App\Models\AuditLog;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;

        $stats = [
            'total' => Pendaftar::where('id_kampus', $id_kampus)->count(),
            'approved' => Pendaftar::where('id_kampus', $id_kampus)->where('status', 'Approved')->count(),
            'leads' => Pendaftar::where('id_kampus', $id_kampus)->where('jalur_masuk', 'leads')->count(),
            'walk_in' => Pendaftar::where('id_kampus', $id_kampus)->where('jalur_masuk', 'walk_in')->count(),
        ];

        $laporan = Pendaftar::where('id_kampus', $id_kampus)
            ->where('status', 'Approved')
            ->with('prodi')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'stats' => $stats,
            'laporan' => $laporan
        ]);
    }

    public function auditLog(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $logs = AuditLog::where('id_kampus', $id_kampus)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }
}
