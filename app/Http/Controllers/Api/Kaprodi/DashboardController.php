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
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $prodi = Prodi::forCurrentKaprodi()
            ->orderBy('nama_prodi')
            ->get(['id', 'kode_prodi', 'nama_prodi', 'jenjang']);
        $prodiIds = $prodi->pluck('id');

        return $this->successResponse([
            'stats' => [
                'pending_validation' => Pendaftar::whereIn('id_prodi', $prodiIds)->where('status', 'Pending Kaprodi')->count(),
                'revisi' => Pendaftar::whereIn('id_prodi', $prodiIds)->where('status', 'Revisi')->count(),
                'approved' => Pendaftar::whereIn('id_prodi', $prodiIds)->where('status', 'Approved')->count(),
                'total_sks' => Pendaftar::whereIn('id_prodi', $prodiIds)->where('status', 'Approved')->sum('total_sks_diakui'),
            ],
            'prodi' => $prodi,
            'verification_method' => 'qr',
            'recent_validation' => Pendaftar::whereIn('id_prodi', $prodiIds)
                ->where('status', 'Pending Kaprodi')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
