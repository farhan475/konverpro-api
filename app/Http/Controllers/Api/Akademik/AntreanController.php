<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Enums\StatusPendaftarEnum;
use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Services\AuditService;
use App\Services\MatchingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AntreanController extends Controller
{
    use ApiResponse;

    public function __construct(
        private MatchingService $matchingService,
        private AuditService $audit
    ) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            Pendaftar::whereIn('status', [
                StatusPendaftarEnum::BARU,
                StatusPendaftarEnum::AI_PROCESSING,
            ])
                ->with('prodi:id,nama_prodi,kode_prodi')
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Pendaftar $pendaftar): JsonResponse
    {
        return $this->successResponse(
            $pendaftar->load(['prodi:id,nama_prodi', 'transkripAsal'])
        );
    }

    public function proses(Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->status !== StatusPendaftarEnum::BARU) {
            return $this->errorResponse('Hanya pendaftar dengan status "Baru" yang dapat diproses.', 422);
        }

        try {
            $this->matchingService->processMatching($pendaftar);

            $this->audit->log(
                'matching.processed',
                'Pendaftar',
                $pendaftar->id,
                "Matching selesai untuk {$pendaftar->nama_lengkap}"
            );

            return $this->successResponse(null, 'Proses matching selesai. Menunggu validasi kaprodi.');
        } catch (\Exception $e) {
            return $this->errorResponse('Proses matching gagal: ' . $e->getMessage(), 500);
        }
    }
}
