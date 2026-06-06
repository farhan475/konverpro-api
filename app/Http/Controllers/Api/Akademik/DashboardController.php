<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->successResponse([
            'stats' => [
                'antrean_baru' => Pendaftar::where('status', 'Baru')->count(),
                'ai_processing' => Pendaftar::where('status', 'AI Processing')->count(),
                'pending_kaprodi' => Pendaftar::where('status', 'Pending Kaprodi')->count(),
            ],
            'recent_queue' => Pendaftar::where('status', 'Baru')->with('prodi')->latest()->limit(10)->get(),
        ]);
    }
}
