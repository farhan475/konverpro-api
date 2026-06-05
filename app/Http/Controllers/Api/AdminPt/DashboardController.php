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
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
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
            'avg_sks_diakui' => (float) (Pendaftar::where('id_kampus', $id_kampus)->avg('total_sks_diakui') ?? 0),
            'total_mk' => KurikulumMk::whereHas('prodi', function ($query) use ($id_kampus) {
                $query->where('id_kampus', $id_kampus);
            })->count(),
        ];

        $status_breakdown = Pendaftar::where('id_kampus', $id_kampus)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->orderBy('total', 'desc')
            ->get();

        $prodi_performance = Prodi::where('id_kampus', $id_kampus)
            ->with(['kaprodi'])
            ->withCount(['pendaftar as total_pendaftar'])
            ->withCount(['pendaftar as pending_validasi' => function ($query) {
                $query->where('status', 'Pending Kaprodi');
            }])
            ->withCount(['pendaftar as approved' => function ($query) {
                $query->where('status', 'Approved');
            }])
            ->withCount(['pendaftar as butuh_tindak_lanjut' => function ($query) {
                $query->whereIn('status', ['Revisi', 'Rejected']);
            }])
            ->get()
            ->map(function ($prodi) {
                $prodi->avg_sks_diakui = (float) (Pendaftar::where('id_prodi', $prodi->id)->avg('total_sks_diakui') ?? 0);
                return $prodi;
            });

        $registration_chart = Pendaftar::where('id_kampus', $id_kampus)
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'), DB::raw('COUNT(*) as total'))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        $ai_summary = [
            'total_reference_keywords' => DB::table('mk_referensi_ai')->count(), 
            'total_described_courses' => KurikulumMk::whereHas('prodi', function ($q) use ($id_kampus) {
                $q->where('id_kampus', $id_kampus);
            })->whereNotNull('deskripsi_singkat')->count(), 
            'total_courses' => $stats['total_mk'],
        ];

        return response()->json([
            'kampus' => $kampus, 
            'stats' => $stats, 
            'status_breakdown' => $status_breakdown, 
            'prodi_performance' => $prodi_performance, 
            'registration_chart' => $registration_chart, 
            'ai_summary' => $ai_summary, 
            'nama_admin' => $user->nama_lengkap
        ], 200);
    }
}
