<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Enums\StatusPendaftarEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessValidasiRequest;
use App\Models\HasilKonversi;
use App\Models\KurikulumMk;
use App\Models\Pendaftar;
use App\Models\PengaturanProdi;
use App\Models\Prodi;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ValidasiController extends Controller
{
    use ApiResponse;

    public function __construct(protected NotifikasiService $notifService, private AuditService $audit) {}

    public function index(): JsonResponse
    {
        $prodiIds = Prodi::where('id_kaprodi', Auth::id())->pluck('id');

        return $this->successResponse(
            Pendaftar::whereIn('id_prodi', $prodiIds)
                ->with('prodi')
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Pendaftar $pendaftar): JsonResponse
    {
        $prodiIds = Prodi::where('id_kaprodi', Auth::id())->pluck('id');
        if (!$prodiIds->contains($pendaftar->id_prodi)) {
            return $this->errorResponse('Unauthorized for this prodi.', 403);
        }

        return $this->successResponse($pendaftar->load([
            'prodi.pengaturan', 
            'prodi.kurikulumMk',
            'transkripAsal', 
            'hasilKonversi.mkTujuan', 
            'hasilKonversi.transkripAsal'
        ]));
    }

    public function updateHasil(ProcessValidasiRequest $request, HasilKonversi $hasilKonversi): JsonResponse
    {
        // Ownership Check: Kaprodi hanya bisa mengubah hasil jika pendaftar di bawah prodinya
        $prodiIds = Prodi::where('id_kaprodi', Auth::id())->pluck('id');

        /** @var Pendaftar $pendaftar */
        $pendaftar = $hasilKonversi->pendaftar;
        if (!$prodiIds->contains($pendaftar->id_prodi)) {
            return $this->errorResponse('Unauthorized. Pendaftar bukan dari prodi Anda.', 403);
        }

        $validated = $request->validated();

        if (isset($validated['id_mk_tujuan']) && !isset($validated['sks_diakui'])) {
            /** @var KurikulumMk|null $mkTujuan */
            $mkTujuan = KurikulumMk::find($validated['id_mk_tujuan']);
            if ($mkTujuan && $hasilKonversi->transkripAsal) {
                $validated['sks_diakui'] = min($hasilKonversi->transkripAsal->sks_asal, $mkTujuan->sks);
            }
        }

        $hasilKonversi->update(array_merge($validated, [
            'metode_pemetaan' => 'Manual Kaprodi',
            'is_unmatched' => isset($validated['id_mk_tujuan']) ? false : $hasilKonversi->is_unmatched
        ]));

        return $this->successResponse($hasilKonversi, 'Mapping updated manually.');
    }

    public function approve(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        $totalSksDiakui = $pendaftar->hasilKonversi()->where('is_unmatched', false)->sum('sks_diakui');
        $pendaftar->loadMissing('prodi.pengaturan', 'prodi.kurikulumMk');
        
        /** @var PengaturanProdi|null $pengaturan */
        $pengaturan = $pendaftar->prodi?->pengaturan;
        if ($pengaturan) {
            $totalSksKurikulum = (float) ($pendaftar->prodi?->kurikulumMk?->sum('sks') ?? 0);
            if ($totalSksKurikulum > 0) {
                $maxPersen = (float) $pengaturan->max_konversi_sks_persen;
                $maxSks = ($maxPersen / 100) * $totalSksKurikulum;
                if ($totalSksDiakui > $maxSks) {
                    return $this->errorResponse("Total SKS diakui (" . round((float) $totalSksDiakui) . ") melebihi batas maksimal konversi prodi (" . round($maxSks) . " SKS / {$maxPersen}%).", 422);
                }
            }
        }

        $pendaftar->update([
            'status' => StatusPendaftarEnum::APPROVED,
            'total_sks_diakui' => $totalSksDiakui,
            'hash_ba_digital' => hash('sha256', $pendaftar->id . now())
        ]);

        $this->notifService->send($pendaftar, 'Approved');
        $this->audit->log('approve_konversi', 'Pendaftar', $pendaftar->id, "Approved conversion for {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Conversion approved.');
    }

    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:pendaftar,id'
        ]);

        /** @var array<string> $ids */
        $ids = $request->ids;
        $count = 0;

        // Security check: Pastikan hanya memproses pendaftar dari prodi milik Kaprodi
        $prodiIds = Prodi::where('id_kaprodi', Auth::id())->pluck('id');

        DB::beginTransaction();
        try {
            $pendaftars = Pendaftar::whereIn('id', $ids)
                ->whereIn('id_prodi', $prodiIds)
                ->where('status', StatusPendaftarEnum::PENDING_KAPRODI)
                ->get();

            foreach ($pendaftars as $pendaftar) {
                $totalSksDiakui = $pendaftar->hasilKonversi()->where('is_unmatched', false)->sum('sks_diakui');
                
                $pendaftar->update([
                    'status' => StatusPendaftarEnum::APPROVED,
                    'total_sks_diakui' => $totalSksDiakui,
                    'hash_ba_digital' => hash('sha256', $pendaftar->id . now())
                ]);

                $this->notifService->send($pendaftar, 'Approved');
                $this->audit->log('approve_konversi', 'Pendaftar', $pendaftar->id, "Approved conversion (bulk) for {$pendaftar->nama_lengkap}");
                $count++;
            }
            DB::commit();
            return $this->successResponse(null, "{$count} permohonan berhasil disetujui.");
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal melakukan persetujuan massal: ' . $e->getMessage(), 500);
        }
    }

    public function revisi(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        $request->validate(['catatan' => 'required|string']);

        $pendaftar->update([
            'status' => StatusPendaftarEnum::REVISI,
            'catatan_revisi' => $request->catatan
        ]);

        $pendaftar->loadMissing('prodi');
        $this->notifService->send($pendaftar, 'Revisi');
        $this->audit->log('revisi_konversi', 'Pendaftar', $pendaftar->id, "Requested revision for {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Revision requested.');
    }

    public function reject(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        $request->validate(['alasan' => 'required|string']);

        $pendaftar->update([
            'status' => StatusPendaftarEnum::REJECTED,
            'catatan_revisi' => $request->alasan
        ]);

        $pendaftar->loadMissing('prodi');
        $this->notifService->send($pendaftar, 'Rejected');
        $this->audit->log('reject_konversi', 'Pendaftar', $pendaftar->id, "Rejected conversion for {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Conversion rejected.');
    }
}