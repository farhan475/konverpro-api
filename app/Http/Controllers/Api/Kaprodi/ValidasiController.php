<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\HasilKonversi;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidasiController extends Controller
{
    use ApiResponse;

    public function __construct(protected NotificationService $notifService) {}

    public function index(): JsonResponse
    {
        $prodiIds = Prodi::where('id_kaprodi', auth()->id())->pluck('id');
        
        return $this->successResponse(
            Pendaftar::whereIn('id_prodi', $prodiIds)
                ->with('prodi')
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Pendaftar $pendaftar): JsonResponse
    {
        $prodiIds = Prodi::where('id_kaprodi', auth()->id())->pluck('id');
        if (!$prodiIds->contains($pendaftar->id_prodi)) {
            return $this->errorResponse('Unauthorized for this prodi.', 403);
        }

        return $this->successResponse($pendaftar->load('prodi', 'transkripAsal', 'hasilKonversi.mkTujuan', 'hasilKonversi.transkripAsal'));
    }

    public function updateHasil(\App\Http\Requests\ProcessValidasiRequest $request, HasilKonversi $hasilKonversi): JsonResponse
    {
        $validated = $request->validated();

        $hasilKonversi->update(array_merge($validated, ['metode_pemetaan' => 'Manual Kaprodi']));

        return $this->successResponse($hasilKonversi, 'Mapping updated manually.');
    }

    public function approve(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        $pendaftar->update([
            'status' => 'Approved',
            'total_sks_diakui' => $pendaftar->hasilKonversi()->where('is_unmatched', false)->sum('sks_diakui'),
            'hash_ba_digital' => hash('sha256', $pendaftar->id . now())
        ]);

        $this->notifService->send($pendaftar, 'Approved');
        AuditService::log('approve_konversi', 'Pendaftar', $pendaftar->id, "Approved conversion for {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Conversion approved.');
    }

    public function revisi(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        $request->validate(['catatan' => 'required|string']);

        $pendaftar->update([
            'status' => 'Revisi',
            'catatan_revisi' => $request->catatan
        ]);

        $this->notifService->send($pendaftar, 'Revisi');
        AuditService::log('revisi_konversi', 'Pendaftar', $pendaftar->id, "Requested revision for {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Revision requested.');
    }

    public function reject(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        $request->validate(['alasan' => 'required|string']);

        $pendaftar->update([
            'status' => 'Rejected',
            'catatan_revisi' => $request->alasan
        ]);

        $this->notifService->send($pendaftar, 'Rejected');
        AuditService::log('reject_konversi', 'Pendaftar', $pendaftar->id, "Rejected conversion for {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Conversion rejected.');
    }
}
