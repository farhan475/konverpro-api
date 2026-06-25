<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BaDocument;
use App\Models\Pendaftar;
use App\Services\AuditService;
use App\Services\BeritaAcaraService;
use App\Services\DocumentVerificationService;
use App\Services\InternalNotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicPortalController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuditService $audit,
        private InternalNotificationService $notifications
    ) {}

    public function verify(BaDocument $document, DocumentVerificationService $verification): JsonResponse
    {
        $document->load('pendaftar.prodi.kaprodi');
        $pendaftar = $document->pendaftar;
        if (! $pendaftar instanceof Pendaftar) {
            return $this->notFoundResponse('Dokumen tidak memiliki data permohonan.');
        }

        return $this->successResponse([
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'version' => $document->version,
            'status' => $document->status,
            'approved_at' => $document->approved_at,
            'program' => $pendaftar->prodi?->nama_prodi,
            'approver' => $pendaftar->prodi?->kaprodi?->nama_lengkap,
            'verification_statement' => $verification->verificationText($pendaftar),
            'revoked_reason' => $document->status === 'revoked' ? $document->revoked_reason : null,
            'replaced_by_id' => $document->replaced_by_id,
        ]);
    }

    public function portal(string $token): JsonResponse
    {
        $pendaftar = $this->findByToken($token);
        $pendaftar->load([
            'prodi:id,nama_prodi,jenjang',
            'hasilKonversi.mkTujuan',
            'hasilKonversi.transkripAsal',
            'appeals' => fn ($query) => $query->latest(),
            'currentBaDocument',
        ]);

        return $this->successResponse([
            'student' => [
                'name' => $pendaftar->nama_lengkap,
                'nim_asal' => $pendaftar->nim_asal,
                'program' => $pendaftar->prodi?->nama_prodi,
            ],
            'status' => $pendaftar->status->value,
            'total_sks_diakui' => $pendaftar->total_sks_diakui,
            'catatan_revisi' => $pendaftar->catatan_revisi,
            'results' => $pendaftar->hasilKonversi,
            'appeals' => $pendaftar->appeals,
            'document' => $pendaftar->currentBaDocument,
        ]);
    }

    public function submitAppeal(Request $request, string $token): JsonResponse
    {
        $pendaftar = $this->findByToken($token);
        $validated = $request->validate([
            'reason' => 'required|string|min:20|max:3000',
            'additional_information' => 'nullable|string|max:5000',
        ]);

        if (! in_array($pendaftar->status->value, ['Approved', 'Rejected'], true)) {
            return $this->errorResponse('Evaluasi ulang hanya tersedia setelah keputusan akhir.', 422);
        }

        if ($pendaftar->appeals()->where('status', 'submitted')->exists()) {
            return $this->errorResponse('Masih ada permohonan evaluasi ulang yang sedang diproses.', 422);
        }

        $appeal = $pendaftar->appeals()->create($validated);
        $this->audit->logAs(
            null,
            'appeal.submitted',
            'PendaftarAppeal',
            $appeal->id,
            "Evaluasi ulang diajukan untuk {$pendaftar->nama_lengkap}.",
            $request->ip()
        );
        $this->notifications->notifyRole(
            'akademik',
            'appeal_submitted',
            'Evaluasi ulang baru',
            "{$pendaftar->nama_lengkap} mengajukan evaluasi ulang hasil konversi.",
            '/akademik/appeals',
            'PendaftarAppeal',
            $appeal->id
        );

        return $this->createdResponse($appeal, 'Permohonan evaluasi ulang berhasil dikirim.');
    }

    public function downloadBa(string $token, BeritaAcaraService $service): Response
    {
        $pendaftar = $this->findByToken($token);
        $document = $pendaftar->currentBaDocument;
        if ($pendaftar->status->value !== 'Approved'
            || ! $document instanceof BaDocument
            || $document->status !== 'final') {
            abort(404, 'Berita Acara final belum tersedia.');
        }
        $document = $service->renderPdf($pendaftar);

        return new Response($document['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$document['filename']}\"",
        ]);
    }

    private function findByToken(string $token): Pendaftar
    {
        return Pendaftar::where('portal_token_hash', hash('sha256', $token))->firstOrFail();
    }
}
