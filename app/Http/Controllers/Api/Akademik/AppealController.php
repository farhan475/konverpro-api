<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Enums\StatusPendaftarEnum;
use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Models\PendaftarAppeal;
use App\Services\AuditService;
use App\Services\InternalNotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppealController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuditService $audit,
        private InternalNotificationService $notifications
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PendaftarAppeal::with('pendaftar.prodi')->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return $this->successResponse($query->paginate(20));
    }

    public function resolve(Request $request, PendaftarAppeal $appeal): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:accepted,rejected',
            'resolution_notes' => 'required|string|max:3000',
        ]);
        if ($appeal->status !== 'submitted') {
            return $this->errorResponse('Permohonan ini sudah diputuskan.', 422);
        }

        $appeal->update([
            'status' => $validated['decision'],
            'resolution_notes' => $validated['resolution_notes'],
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        if ($validated['decision'] === 'accepted') {
            $pendaftar = $appeal->pendaftar;
            if ($pendaftar instanceof Pendaftar) {
                $pendaftar->update([
                    'status' => StatusPendaftarEnum::REVISI,
                    'catatan_revisi' => $validated['resolution_notes'],
                ]);
                $pendaftar->currentBaDocument?->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoked_reason' => 'Evaluasi ulang diterima.',
                ]);
            }
        }

        $this->audit->log('appeal.resolved', 'PendaftarAppeal', $appeal->id, $validated['decision']);
        $pendaftar = $appeal->pendaftar;
        if ($pendaftar instanceof Pendaftar && is_string($pendaftar->created_by)) {
            $decisionLabel = $validated['decision'] === 'accepted' ? 'diterima' : 'ditolak';
            $this->notifications->notifyUser(
                $pendaftar->created_by,
                'appeal_resolved',
                'Evaluasi ulang diputuskan',
                "Evaluasi ulang {$pendaftar->nama_lengkap} {$decisionLabel}.",
                "/admin/pendaftar/{$pendaftar->id}",
                'PendaftarAppeal',
                $appeal->id
            );
        }

        return $this->successResponse($appeal->fresh('pendaftar.prodi'), 'Permohonan evaluasi ulang diputuskan.');
    }
}
