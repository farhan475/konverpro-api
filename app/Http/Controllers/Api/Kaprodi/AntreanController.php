<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Enums\StatusPendaftarEnum;
use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AntreanController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:Baru,AI Processing,Review Akademik,Revisi',
        ]);

        $prodiIds = Prodi::forCurrentKaprodi()->pluck('id');

        $query = Pendaftar::whereIn('id_prodi', $prodiIds)
            ->whereIn('status', [
                StatusPendaftarEnum::BARU,
                StatusPendaftarEnum::AI_PROCESSING,
                StatusPendaftarEnum::REVIEW_AKADEMIK,
                StatusPendaftarEnum::REVISI,
            ]);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nim_asal', 'like', "%{$search}%")
                    ->orWhere('asal_kampus', 'like', "%{$search}%");
            });
        }

        return $this->successResponse(
            $query
                ->with('prodi:id,nama_prodi,kode_prodi')
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Pendaftar $pendaftar): JsonResponse
    {
        $prodiIds = Prodi::forCurrentKaprodi()->pluck('id');

        if (! $prodiIds->contains($pendaftar->id_prodi)) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        return $this->successResponse(
            $pendaftar->load(['prodi:id,nama_prodi', 'transkripAsal'])
        );
    }
}
