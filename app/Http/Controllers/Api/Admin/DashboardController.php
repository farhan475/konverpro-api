<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $userId = auth()->id();
        
        return $this->successResponse([
            'stats' => [
                'total_input' => Pendaftar::where('created_by', $userId)->count(),
                'pending' => Pendaftar::where('created_by', $userId)->whereIn('status', ['Baru', 'AI Processing', 'Pending Kaprodi'])->count(),
                'approved' => Pendaftar::where('created_by', $userId)->where('status', 'Approved')->count(),
                'revisi' => Pendaftar::where('created_by', $userId)->where('status', 'Revisi')->count(),
            ],
            'recent_pendaftar' => Pendaftar::where('created_by', $userId)->with('prodi')->latest()->limit(10)->get(),
        ]);
    }
}
