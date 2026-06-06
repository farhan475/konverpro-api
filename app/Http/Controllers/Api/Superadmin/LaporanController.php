<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\HasilKonversi;
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
            'pendaftar_per_prodi' => Prodi::withCount('pendaftar')->get(),
            'status_distribusi' => Pendaftar::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get(),
            'metode_matching' => HasilKonversi::select('metode_pemetaan', DB::raw('count(*) as total'))
                ->whereNotNull('metode_pemetaan')
                ->groupBy('metode_pemetaan')
                ->get(),
        ];

        return $this->successResponse($stats);
    }
}
