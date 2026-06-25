<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Enums\StatusPendaftarEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Akademik\UpdateAntreanRequest;
use App\Jobs\ProcessMatchingJob;
use App\Models\Pendaftar;
use App\Models\TranskripAsal;
use App\Services\AuditService;
use App\Services\InternalNotificationService;
use App\Services\MatchingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AntreanController extends Controller
{
    use ApiResponse;

    public function __construct(
        private MatchingService $matchingService,
        private AuditService $audit,
        private InternalNotificationService $notifications
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:Baru,AI Processing,Review Akademik,Revisi',
        ]);

        $query = Pendaftar::whereIn('status', [
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
        return $this->successResponse(
            $pendaftar->load(['prodi:id,nama_prodi', 'transkripAsal'])
        );
    }

    public function update(UpdateAntreanRequest $request, Pendaftar $pendaftar): JsonResponse
    {
        if (! in_array($pendaftar->status, [StatusPendaftarEnum::BARU, StatusPendaftarEnum::REVISI], true)) {
            return $this->errorResponse('Hanya pendaftar dengan status "Baru" atau "Revisi" yang dapat diubah.', 422);
        }

        DB::beginTransaction();
        try {
            $pendaftar->update($request->only([
                'nama_lengkap', 'nim_asal', 'email', 'no_whatsapp', 'asal_kampus', 'asal_prodi', 'id_prodi',
            ]));

            /** @var array<int, array{id: string, nama_mk_asal: string, sks_asal: int, nilai_huruf_asal: string}> $transkripItems */
            $transkripItems = $request->transkrip;
            $submittedIds = collect($transkripItems)->pluck('id');
            $ownedCount = TranskripAsal::where('id_pendaftar', $pendaftar->id)
                ->whereIn('id', $submittedIds)
                ->count();

            if ($ownedCount !== $submittedIds->unique()->count()) {
                DB::rollBack();

                return $this->errorResponse('Terdapat baris transkrip yang bukan milik pendaftar ini.', 422);
            }

            foreach ($transkripItems as $item) {
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

            return $this->errorResponse('Gagal memperbarui data: '.$e->getMessage(), 500);
        }
    }

    public function proses(Pendaftar $pendaftar): JsonResponse
    {
        if (! in_array($pendaftar->status, [StatusPendaftarEnum::BARU, StatusPendaftarEnum::REVISI], true)) {
            return $this->errorResponse('Hanya pendaftar dengan status "Baru" atau "Revisi" yang dapat diproses.', 422);
        }

        try {
            $this->matchingService->validateReady($pendaftar);
            $pendaftar->update(['status' => StatusPendaftarEnum::AI_PROCESSING]);
            ProcessMatchingJob::dispatch($pendaftar->id, (string) auth()->id());
            $this->audit->log(
                'matching.queued',
                'Pendaftar',
                $pendaftar->id,
                "Matching dijadwalkan untuk {$pendaftar->nama_lengkap}"
            );

            return $this->successResponse(null, 'Proses matching dijadwalkan.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse('Proses matching gagal: '.$e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Proses matching gagal: '.$e->getMessage(), 500);
        }
    }

    public function confirmToKaprodi(Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->status !== StatusPendaftarEnum::REVIEW_AKADEMIK) {
            return $this->errorResponse('Hanya pendaftar dengan status "Review Akademik" yang dapat dikonfirmasi.', 422);
        }

        $pendaftar->update(['status' => StatusPendaftarEnum::PENDING_KAPRODI]);

        $this->audit->log(
            'antrean.confirmed',
            'Pendaftar',
            $pendaftar->id,
            "Akademik mengkonfirmasi hasil matching {$pendaftar->nama_lengkap} ke Kaprodi"
        );
        $kaprodiId = $pendaftar->prodi?->id_kaprodi;
        if (is_string($kaprodiId)) {
            $this->notifications->notifyUser(
                $kaprodiId,
                'validation_requested',
                'Validasi baru',
                "{$pendaftar->nama_lengkap} menunggu validasi konversi.",
                "/kaprodi/validasi/{$pendaftar->id}",
                'Pendaftar',
                $pendaftar->id
            );
        }

        return $this->successResponse(null, 'Hasil matching dikonfirmasi. Menunggu validasi Kaprodi.');
    }
}
