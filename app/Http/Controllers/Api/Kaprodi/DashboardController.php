<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }
        
        $prodiIds = Prodi::where('id_kaprodi', $user->id)->pluck('id');

        return $this->successResponse([
            'stats' => [
                'pending_validation' => Pendaftar::whereIn('id_prodi', $prodiIds)->where('status', 'Pending Kaprodi')->count(),
                'total_approved' => Pendaftar::whereIn('id_prodi', $prodiIds)->where('status', 'Approved')->count(),
                'total_rejected' => Pendaftar::whereIn('id_prodi', $prodiIds)->where('status', 'Rejected')->count(),
            ],
            'recent_validation' => Pendaftar::whereIn('id_prodi', $prodiIds)
                ->where('status', 'Pending Kaprodi')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
