<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\HasilKonversi;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidasiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private NotificationService $notifService,
        private AuditService $audit
    ) {}

    public function index(Request $request): JsonResponse
    {
        $prodiIds = Prodi::where('id_kaprodi', Auth::id())->pluck('id');

        $data = Pendaftar::whereIn('id_prodi', $prodiIds)
            ->with('prodi:id,nama_prodi,kode_prodi')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByRaw("FIELD(status, 'Pending Kaprodi', 'Revisi', 'Approved', 'Rejected', 'Baru', 'AI Processing')")
            ->latest()
            ->paginate(20);

        return $this->successResponse($data);
    }

    public function show(Pendaftar $pendaftar): JsonResponse
    {
        $prodiIds = Prodi::where('id_kaprodi', Auth::id())->pluck('id');

        if (!$prodiIds->contains($pendaftar->id_prodi)) {
            return $this->unauthorizedResponse('Prodi ini bukan tanggung jawab Anda.');
        }

        return $this->successResponse(
            $pendaftar->load([
                'prodi:id,nama_prodi,kode_prodi',
                'transkripAsal',
                'hasilKonversi.mkTujuan:id,nama_mk,sks,kode_mk',
                'hasilKonversi.transkripAsal:id,nama_mk_asal,sks_asal,nilai_huruf_asal',
                'createdBy:id,nama_lengkap',
            ])
        );
    }

    /**
     * Override manual satu baris hasil konversi.
     */
    public function updateHasil(Request $request, HasilKonversi $hasilKonversi): JsonResponse
    {
        $validated = $request->validate([
            'id_mk_tujuan'    => 'required|exists:kurikulum_mk,id',
            'nilai_akhir_huruf'=> 'nullable|string|max:5',
            'sks_diakui'      => 'required|integer|min:0',
        ]);

        $hasilKonversi->update(array_merge($validated, [
            'metode_pemetaan' => 'Manual Kaprodi',
            'is_unmatched'    => false,
        ]));

        $this->audit->log(
            'hasil_konversi.override',
            'HasilKonversi',
            $hasilKonversi->id,
            "Manual override oleh kaprodi"
        );

        return $this->successResponse($hasilKonversi->load('mkTujuan:id,nama_mk,sks'), 'Mapping berhasil diperbarui.');
    }

    public function approve(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        if (!$this->ownedByKaprodi($pendaftar)) {
            return $this->unauthorizedResponse();
        }

        $totalSks = $pendaftar->hasilKonversi()
            ->where('is_unmatched', false)
            ->sum('sks_diakui');

        $pendaftar->update([
            'status'           => 'Approved',
            'total_sks_diakui' => $totalSks,
            'hash_ba_digital'  => hash('sha256', $pendaftar->id . now()->timestamp . Auth::id()),
        ]);

        $this->notifService->send($pendaftar->load('prodi'), 'Approved');
        $this->audit->log('konversi.approved', 'Pendaftar', $pendaftar->id, "Approved — {$pendaftar->nama_lengkap}");

        return $this->successResponse(null, 'Permohonan disetujui.');
    }

    public function revisi(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        if (!$this->ownedByKaprodi($pendaftar)) {
            return $this->unauthorizedResponse();
        }

        $request->validate(['catatan' => 'required|string|max:500']);

        $pendaftar->update([
            'status'          => 'Revisi',
            'catatan_revisi'  => $request->catatan,
        ]);

        $this->notifService->send($pendaftar->load('prodi'), 'Revisi');
        $this->audit->log('konversi.revisi', 'Pendaftar', $pendaftar->id, "Revisi — {$request->catatan}");

        return $this->successResponse(null, 'Permintaan revisi dikirim.');
    }

    public function reject(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        if (!$this->ownedByKaprodi($pendaftar)) {
            return $this->unauthorizedResponse();
        }

        $request->validate(['alasan' => 'required|string|max:500']);

        $pendaftar->update([
            'status'         => 'Rejected',
            'catatan_revisi' => $request->alasan,
        ]);

        $this->notifService->send($pendaftar->load('prodi'), 'Rejected');
        $this->audit->log('konversi.rejected', 'Pendaftar', $pendaftar->id, "Rejected — {$request->alasan}");

        return $this->successResponse(null, 'Permohonan ditolak.');
    }

    private function ownedByKaprodi(Pendaftar $pendaftar): bool
    {
        return Prodi::where('id_kaprodi', Auth::id())
            ->where('id', $pendaftar->id_prodi)
            ->exists();
    }
}