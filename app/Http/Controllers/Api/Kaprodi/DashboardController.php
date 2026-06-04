<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Kampus;
use App\Models\Prodi;
use App\Models\Pendaftar;
use App\Models\KurikulumMk;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $id_user = $user->id;

        $my_prodi = Prodi::where('id_kaprodi', $id_user)->get();
        $prodi_ids = $my_prodi->pluck('id')->toArray();

        $stats = [
            'total_pendaftar' => Pendaftar::whereIn('id_prodi', $prodi_ids)->count(),
            'pending_validasi' => Pendaftar::whereIn('id_prodi', $prodi_ids)->where('status', 'Pending Kaprodi')->count(),
            'approved' => Pendaftar::whereIn('id_prodi', $prodi_ids)->where('status', 'Approved')->count(),
            'revisi' => Pendaftar::whereIn('id_prodi', $prodi_ids)->where('status', 'Revisi')->count(),
            'rejected' => Pendaftar::whereIn('id_prodi', $prodi_ids)->where('status', 'Rejected')->count(),
            'total_prodi' => $my_prodi->count(),
            'total_kurikulum' => KurikulumMk::whereIn('id_prodi', $prodi_ids)->count(),
            'avg_sks_diakui' => (float) (Pendaftar::whereIn('id_prodi', $prodi_ids)->avg('total_sks_diakui') ?? 0),
        ];

        $recent_pendaftar = Pendaftar::whereIn('id_prodi', $prodi_ids)
            ->with(['prodi'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        $academic_verified_queue = Pendaftar::whereIn('id_prodi', $prodi_ids)
            ->where('status', 'Pending Kaprodi')
            ->with(['prodi'])
            ->withCount(['transkripAsal as total_mk_asal'])
            ->withCount(['hasilKonversi as total_mk_terpetakan'])
            ->orderBy('created_at', 'asc')
            ->limit(12)
            ->get();

        $prodi_performance = Prodi::where('id_kaprodi', $id_user)
            ->withCount(['pendaftar as total_pendaftar'])
            ->withCount(['pendaftar as pending_validasi' => function ($query) {
                $query->where('status', 'Pending Kaprodi');
            }])
            ->withCount(['pendaftar as approved' => function ($query) {
                $query->where('status', 'Approved');
            }])
            ->withCount(['kurikulumMk as total_mk'])
            ->get()
            ->map(function ($prodi) {
                $prodi->total_sks_diakui = (int) Pendaftar::where('id_prodi', $prodi->id)->sum('total_sks_diakui');
                return $prodi;
            });

        return response()->json([
            'stats' => $stats,
            'recent_pendaftar' => $recent_pendaftar,
            'academic_verified_queue' => $academic_verified_queue,
            'prodi_performance' => $prodi_performance,
            'my_prodi' => $my_prodi,
        ], 200);
    }
}
