<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\KurikulumMk;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $id_kampus = $user->id_kampus;
        $id_kaprodi = $user->id;

        // Stats
        $stats = [
            'total_pendaftar' => Pendaftar::whereHas('prodi', function($q) use ($id_kaprodi) {
                $q->where('id_kaprodi', $id_kaprodi);
            })->count(),
            'pending_validasi' => Pendaftar::whereHas('prodi', function($q) use ($id_kaprodi) {
                $q->where('id_kaprodi', $id_kaprodi);
            })->where('status', 'Pending Kaprodi')->count(),
            'approved' => Pendaftar::whereHas('prodi', function($q) use ($id_kaprodi) {
                $q->where('id_kaprodi', $id_kaprodi);
            })->where('status', 'Approved')->count(),
            'avg_sks_diakui' => (float) (Pendaftar::whereHas('prodi', function($q) use ($id_kaprodi) {
                $q->where('id_kaprodi', $id_kaprodi);
            })->avg('total_sks_diakui') ?? 0),
        ];

        // Status Breakdown
        $status_breakdown = Pendaftar::whereHas('prodi', function($q) use ($id_kaprodi) {
                $q->where('id_kaprodi', $id_kaprodi);
            })
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        // Prodi Performance
        $prodi_performance = Prodi::where('id_kaprodi', $id_kaprodi)
            ->withCount([
                'pendaftar as total_pendaftar',
                'pendaftar as pending_validasi' => function($q) { $q->where('status', 'Pending Kaprodi'); },
                'pendaftar as approved' => function($q) { $q->where('status', 'Approved'); },
                'kurikulum as total_mk'
            ])
            ->get()
            ->map(function($prodi) {
                $prodi->total_sks_diakui = (int) Pendaftar::where('id_prodi', $prodi->id)->sum('total_sks_diakui');
                return $prodi;
            });

        // Report Rows (History)
        $report_rows = Pendaftar::whereHas('prodi', function($q) use ($id_kaprodi) {
                $q->where('id_kaprodi', $id_kaprodi);
            })
            ->with(['prodi'])
            ->withCount('hasilKonversi as jumlah_mk_dikonversi')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'status_breakdown' => $status_breakdown,
                'prodi_performance' => $prodi_performance,
                'report_rows' => $report_rows
            ]
        ]);
    }
}
