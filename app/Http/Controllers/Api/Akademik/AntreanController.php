<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Enums\StatusPendaftarEnum;
use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Services\MatchingService;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;

class AntreanController extends Controller
{
    use ApiResponse;

    public function __construct(protected MatchingService $matchingService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            Pendaftar::whereIn('status', [StatusPendaftarEnum::BARU, StatusPendaftarEnum::AI_PROCESSING])
                ->with('prodi')
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Pendaftar $pendaftar): JsonResponse
    {
        return $this->successResponse($pendaftar->load('prodi', 'transkripAsal'));
    }

    public function proses(Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->status !== StatusPendaftarEnum::BARU) {
            return $this->errorResponse('Pendaftar is not in "Baru" status.', 400);
        }

        $pendaftar->update(['status' => StatusPendaftarEnum::AI_PROCESSING]);
        
        try {
            $this->matchingService->processMatching($pendaftar);
            $pendaftar->update(['status' => StatusPendaftarEnum::PENDING_KAPRODI]);
            
            AuditService::log('process_matching', 'Pendaftar', $pendaftar->id, "Processed matching for {$pendaftar->nama_lengkap}");
            
            return $this->successResponse(null, 'Matching process completed.');
        } catch (\Exception $e) {
            $pendaftar->update(['status' => StatusPendaftarEnum::BARU]);
            return $this->errorResponse('Matching failed: ' . $e->getMessage(), 500);
        }
    }
}
