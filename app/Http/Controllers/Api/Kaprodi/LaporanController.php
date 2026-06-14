<?php

namespace App\Http\Controllers\Api\Kaprodi;

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
        $prodiIds = Prodi::where('id_kaprodi', auth()->id())->pluck('id');

        $stats = [
            'summary' => Pendaftar::whereIn('id_prodi', $prodiIds)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get(),
            'total_sks' => Pendaftar::whereIn('id_prodi', $prodiIds)
                ->where('status', 'Approved')
                ->sum('total_sks_diakui'),
            'total_pendaftar' => Pendaftar::whereIn('id_prodi', $prodiIds)->count(),
            'recent_approved' => Pendaftar::whereIn('id_prodi', $prodiIds)
                ->where('status', 'Approved')
                ->with('prodi')
                ->latest()
                ->limit(20)
                ->get(),
        ];

        return $this->successResponse($stats);
    }
}
