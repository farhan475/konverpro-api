<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Enums\StatusPendaftarEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Akademik\UpdateAntreanRequest;
use App\Models\Pendaftar;
use App\Models\TranskripAsal;
use App\Services\AuditService;
use App\Services\MatchingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

    public function update(UpdateAntreanRequest $request, Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->status !== StatusPendaftarEnum::BARU) {
            return $this->errorResponse('Hanya pendaftar dengan status "Baru" yang dapat diubah.', 422);
        }

        DB::beginTransaction();
        try {
            $pendaftar->update($request->only([
                'nama_lengkap', 'nim_asal', 'email', 'no_whatsapp', 'asal_kampus', 'asal_prodi', 'id_prodi'
            ]));

            foreach ($request->transkrip as $item) {
                TranskripAsal::where('id', $item['id'])
                    ->where('id_pendaftar', $pendaftar->id)
                    ->update([
                        'nama_mk_asal' => $item['nama_mk_asal'],
                        'sks_asal' => $item['sks_asal'],
                        'nilai_huruf_asal' => $item['nilai_huruf_asal'],
                    ]);
            }

            $this->audit->log(
                'antrean.updated',
                'Pendaftar',
                $pendaftar->id,
                "Data pendaftar {$pendaftar->nama_lengkap} dikoreksi oleh akademik"
            );

            DB::commit();
            return $this->successResponse($pendaftar->load('transkripAsal'), 'Data pendaftar berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal memperbarui data: ' . $e->getMessage(), 500);
        }
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
