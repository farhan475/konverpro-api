<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $stats = [
            'global' => [
                'total_pendaftar' => Pendaftar::count(),
                'total_sks_diakui' => Pendaftar::where('status', 'Approved')->sum('total_sks_diakui'),
                'avg_sks_per_mhs' => round(Pendaftar::where('status', 'Approved')->avg('total_sks_diakui') ?? 0, 2),
            ],
            'by_status' => Pendaftar::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get(),
            'by_prodi' => Prodi::withCount('pendaftar')
                ->with(['pendaftar' => function($query) {
                    $query->select('id_prodi', 'status', DB::raw('count(*) as count'), DB::raw('sum(total_sks_diakui) as sks'))
                          ->groupBy('id_prodi', 'status');
                }])
                ->get()
                ->map(function ($prodi) {
                    return [
                        'nama_prodi' => $prodi->nama_prodi,
                        'total_mhs' => $prodi->pendaftar_count,
                        'approved' => $prodi->pendaftar->where('status', 'Approved')->sum('count'),
                        'total_sks' => $prodi->pendaftar->where('status', 'Approved')->sum('sks'),
                    ];
                }),
            'monthly_trends' => Pendaftar::select(
                    DB::raw("strftime('%Y-%m', created_at) as month"),
                    DB::raw('count(*) as total')
                )
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->get()
        ];

        return $this->successResponse($stats);
    }
}
