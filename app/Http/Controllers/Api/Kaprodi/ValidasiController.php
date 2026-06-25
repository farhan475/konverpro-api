<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Enums\StatusPendaftarEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessValidasiRequest;
use App\Jobs\SendPendaftarNotificationJob;
use App\Models\HasilKonversi;
use App\Models\KurikulumMk;
use App\Models\Pendaftar;
use App\Models\PengaturanProdi;
use App\Models\Prodi;
use App\Services\AuditService;
use App\Services\BeritaAcaraService;
use App\Services\CourseEquivalencyService;
use App\Services\InternalNotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ValidasiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuditService $audit,
        private InternalNotificationService $notifications,
        private BeritaAcaraService $beritaAcara,
        private CourseEquivalencyService $equivalencies
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:Pending Kaprodi,Revisi,Approved,Rejected',
        ]);
        $prodiIds = Prodi::forCurrentKaprodi()->pluck('id');
        $query = Pendaftar::whereIn('id_prodi', $prodiIds);

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
                ->with('prodi')
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Pendaftar $pendaftar): JsonResponse
    {
        $prodiIds = Prodi::forCurrentKaprodi()->pluck('id');
        if (! $prodiIds->contains($pendaftar->id_prodi)) {
            return $this->errorResponse('Unauthorized for this prodi.', 403);
        }

        return $this->successResponse($pendaftar->load([
            'prodi.pengaturan',
            'prodi.kurikulumMk',
            'transkripAsal',
            'hasilKonversi.mkTujuan',
            'hasilKonversi.transkripAsal',
        ]));
    }

    public function updateHasil(ProcessValidasiRequest $request, HasilKonversi $hasilKonversi): JsonResponse
    {
        // Ownership Check: Kaprodi hanya bisa mengubah hasil jika pendaftar di bawah prodinya
        $prodiIds = Prodi::forCurrentKaprodi()->pluck('id');

        /** @var Pendaftar $pendaftar */
        $pendaftar = $hasilKonversi->pendaftar;
        if (! $prodiIds->contains($pendaftar->id_prodi)) {
            return $this->errorResponse('Unauthorized. Pendaftar bukan dari prodi Anda.', 403);
        }

        if ($pendaftar->status !== StatusPendaftarEnum::PENDING_KAPRODI) {
            return $this->errorResponse('Hasil konversi hanya dapat diubah saat status "Pending Kaprodi".', 422);
        }

        $validated = $request->validated();
        $targetIdProvided = array_key_exists('id_mk_tujuan', $validated);
        $mkTujuan = null;

        if (isset($validated['id_mk_tujuan'])) {
            $targetId = $validated['id_mk_tujuan'];
            if (! is_string($targetId)) {
                return $this->errorResponse('Mata kuliah tujuan tidak valid.', 422);
            }
            $mkTujuan = KurikulumMk::query()->whereKey($targetId)->first();
            if (! $mkTujuan || $mkTujuan->id_prodi !== $pendaftar->id_prodi) {
                return $this->errorResponse('Mata kuliah tujuan harus berasal dari prodi pendaftar.', 422);
            }
        } elseif (! $targetIdProvided) {
            $existingTargetId = $hasilKonversi->id_mk_tujuan;
            $mkTujuan = is_string($existingTargetId)
                ? KurikulumMk::query()->whereKey($existingTargetId)->first()
                : null;
        }

        $transkripAsal = $hasilKonversi->transkripAsal;
        if (! $transkripAsal) {
            return $this->errorResponse('Data transkrip asal untuk hasil konversi ini tidak tersedia.', 422);
        }

        if ($targetIdProvided && $mkTujuan === null) {
            $validated['sks_diakui'] = 0;
        } elseif ($mkTujuan) {
            $maxSks = min($transkripAsal->sks_asal, $mkTujuan->sks);
            if (isset($validated['sks_diakui']) && $validated['sks_diakui'] > $maxSks) {
                return $this->errorResponse("SKS diakui tidak boleh melebihi {$maxSks} SKS.", 422);
            }

            if (! isset($validated['sks_diakui'])) {
                $validated['sks_diakui'] = $maxSks;
            }
        } elseif (isset($validated['sks_diakui']) && $validated['sks_diakui'] > 0) {
            return $this->errorResponse('Pilih mata kuliah tujuan sebelum memberikan SKS diakui.', 422);
        }

        $isUnmatched = $targetIdProvided
            ? $mkTujuan === null
            : $hasilKonversi->is_unmatched;

        $hasilKonversi->update(array_merge($validated, [
            'metode_pemetaan' => 'Manual Kaprodi',
            'is_unmatched' => $isUnmatched,
            'match_reason' => 'Pemetaan disesuaikan manual oleh Kaprodi.',
        ]));

        return $this->successResponse($hasilKonversi, 'Mapping updated manually.');
    }

    public function approve(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        if (! $this->canManage($pendaftar)) {
            return $this->errorResponse('Unauthorized. Pendaftar bukan dari prodi Anda.', 403);
        }

        if ($pendaftar->status !== StatusPendaftarEnum::PENDING_KAPRODI) {
            return $this->errorResponse('Hanya pendaftar berstatus "Pending Kaprodi" yang dapat disetujui.', 422);
        }

        $totalSksDiakui = (int) $pendaftar->hasilKonversi()->where('is_unmatched', false)->sum('sks_diakui');
        $limitError = $this->approvalLimitError($pendaftar, $totalSksDiakui);
        if ($limitError !== null) {
            return $this->errorResponse($limitError, 422);
        }

        $documentData = $this->beritaAcara->approvalDocumentData($pendaftar);
        $pendaftar->update(array_merge([
            'status' => StatusPendaftarEnum::APPROVED,
            'total_sks_diakui' => $totalSksDiakui,
        ], $documentData));
        $pendaftar->refresh();
        $this->beritaAcara->createDocument($pendaftar, (string) Auth::id());
        $this->equivalencies->learnFromApproval($pendaftar, (string) Auth::id());

        SendPendaftarNotificationJob::dispatch($pendaftar->id, 'Approved', (string) Auth::id());
        $this->notifyCreator($pendaftar, 'conversion_approved', 'Konversi disetujui', "Konversi {$pendaftar->nama_lengkap} telah disetujui.");
        $this->audit->log('approve_konversi', 'Pendaftar', $pendaftar->id, "Approved conversion for {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Conversion approved.');
    }

    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:pendaftar,id',
        ]);

        /** @var array<string> $ids */
        $ids = $request->ids;
        $count = 0;

        // Security check: Pastikan hanya memproses pendaftar dari prodi milik Kaprodi
        $prodiIds = Prodi::forCurrentKaprodi()->pluck('id');

        $pendaftars = Pendaftar::whereIn('id', $ids)
            ->whereIn('id_prodi', $prodiIds)
            ->where('status', StatusPendaftarEnum::PENDING_KAPRODI)
            ->get();

        if ($pendaftars->count() !== count(array_unique($ids))) {
            return $this->errorResponse('Sebagian permohonan tidak ditemukan, bukan milik prodi Anda, atau tidak lagi pending.', 422);
        }

        foreach ($pendaftars as $pendaftar) {
            $totalSksDiakui = (int) $pendaftar->hasilKonversi()->where('is_unmatched', false)->sum('sks_diakui');
            $limitError = $this->approvalLimitError($pendaftar, $totalSksDiakui);
            if ($limitError !== null) {
                return $this->errorResponse("{$pendaftar->nama_lengkap}: {$limitError}", 422);
            }
        }

        DB::beginTransaction();
        try {
            foreach ($pendaftars as $pendaftar) {
                $totalSksDiakui = (int) $pendaftar->hasilKonversi()->where('is_unmatched', false)->sum('sks_diakui');

                $documentData = $this->beritaAcara->approvalDocumentData($pendaftar);
                $pendaftar->update(array_merge([
                    'status' => StatusPendaftarEnum::APPROVED,
                    'total_sks_diakui' => $totalSksDiakui,
                ], $documentData));
                $pendaftar->refresh();
                $this->beritaAcara->createDocument($pendaftar, (string) Auth::id());
                $this->equivalencies->learnFromApproval($pendaftar, (string) Auth::id());

                SendPendaftarNotificationJob::dispatch($pendaftar->id, 'Approved', (string) Auth::id());
                $this->notifyCreator($pendaftar, 'conversion_approved', 'Konversi disetujui', "Konversi {$pendaftar->nama_lengkap} telah disetujui.");
                $this->audit->log('approve_konversi', 'Pendaftar', $pendaftar->id, "Approved conversion (bulk) for {$pendaftar->nama_lengkap}");
                $count++;
            }
            DB::commit();

            return $this->successResponse(null, "{$count} permohonan berhasil disetujui.");
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Gagal melakukan persetujuan massal: '.$e->getMessage(), 500);
        }
    }

    public function revisi(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        if (! $this->canManage($pendaftar)) {
            return $this->errorResponse('Unauthorized. Pendaftar bukan dari prodi Anda.', 403);
        }

        if ($pendaftar->status !== StatusPendaftarEnum::PENDING_KAPRODI) {
            return $this->errorResponse('Hanya pendaftar berstatus "Pending Kaprodi" yang dapat dikembalikan untuk revisi.', 422);
        }

        $request->validate(['catatan' => 'required|string']);

        $pendaftar->update([
            'status' => StatusPendaftarEnum::REVISI,
            'catatan_revisi' => $request->catatan,
        ]);

        $pendaftar->loadMissing('prodi');
        SendPendaftarNotificationJob::dispatch($pendaftar->id, 'Revisi', (string) Auth::id());
        $this->notifyCreator($pendaftar, 'revision_requested', 'Revisi diperlukan', "Kaprodi meminta revisi untuk {$pendaftar->nama_lengkap}.");
        $this->audit->log('revisi_konversi', 'Pendaftar', $pendaftar->id, "Requested revision for {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Revision requested.');
    }

    public function reject(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        if (! $this->canManage($pendaftar)) {
            return $this->errorResponse('Unauthorized. Pendaftar bukan dari prodi Anda.', 403);
        }

        if ($pendaftar->status !== StatusPendaftarEnum::PENDING_KAPRODI) {
            return $this->errorResponse('Hanya pendaftar berstatus "Pending Kaprodi" yang dapat ditolak.', 422);
        }

        $request->validate(['alasan' => 'required|string']);

        $pendaftar->update([
            'status' => StatusPendaftarEnum::REJECTED,
            'catatan_revisi' => $request->alasan,
        ]);

        $pendaftar->loadMissing('prodi');
        SendPendaftarNotificationJob::dispatch($pendaftar->id, 'Rejected', (string) Auth::id());
        $this->notifyCreator($pendaftar, 'conversion_rejected', 'Konversi ditolak', "Konversi {$pendaftar->nama_lengkap} ditolak.");
        $this->audit->log('reject_konversi', 'Pendaftar', $pendaftar->id, "Rejected conversion for {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Conversion rejected.');
    }

    private function canManage(Pendaftar $pendaftar): bool
    {
        return Prodi::forCurrentKaprodi()
            ->where('id', $pendaftar->id_prodi)
            ->exists();
    }

    private function notifyCreator(Pendaftar $pendaftar, string $type, string $title, string $message): void
    {
        if (! is_string($pendaftar->created_by)) {
            return;
        }

        $this->notifications->notifyUser(
            $pendaftar->created_by,
            $type,
            $title,
            $message,
            "/admin/pendaftar/{$pendaftar->id}",
            'Pendaftar',
            $pendaftar->id
        );
    }

    private function approvalLimitError(Pendaftar $pendaftar, int $totalSksDiakui): ?string
    {
        $pendaftar->loadMissing('prodi.pengaturan', 'prodi.kurikulumMk');

        /** @var PengaturanProdi|null $pengaturan */
        $pengaturan = $pendaftar->prodi?->pengaturan;
        if (! $pengaturan) {
            return null;
        }

        $totalSksValue = $pendaftar->prodi?->kurikulumMk?->sum('sks') ?? 0;
        $totalSksKurikulum = is_numeric($totalSksValue) ? (float) $totalSksValue : 0.0;
        if ($totalSksKurikulum <= 0) {
            return null;
        }

        $maxPersen = (float) $pengaturan->max_konversi_sks_persen;
        $maxSks = ($maxPersen / 100) * $totalSksKurikulum;
        if ($totalSksDiakui <= $maxSks) {
            return null;
        }

        return 'Total SKS diakui ('.round($totalSksDiakui)
            .') melebihi batas maksimal konversi prodi ('
            .round($maxSks)." SKS / {$maxPersen}%).";
    }
}
