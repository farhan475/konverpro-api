<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Enums\StatusPendaftarEnum;
use App\Http\Controllers\Controller;
use App\Jobs\SendBeritaAcaraWhatsappJob;
use App\Models\BaDocument;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Services\AuditService;
use App\Services\BeritaAcaraService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuditService $audit,
        private BeritaAcaraService $beritaAcara
    ) {}

    public function downloadBa(Pendaftar $pendaftar): Response
    {
        if ($pendaftar->prodi?->id_kaprodi !== Auth::id()) {
            abort(403, 'Pendaftar bukan dari prodi Anda.');
        }

        if ($pendaftar->status !== StatusPendaftarEnum::APPROVED) {
            abort(403, 'Hanya permohonan yang disetujui yang dapat mengunduh Berita Acara.');
        }

        try {
            $document = $this->beritaAcara->renderPdf($pendaftar);
        } catch (\RuntimeException $exception) {
            abort(409, $exception->getMessage());
        }

        $this->audit->log(
            'document.ba_downloaded',
            'Pendaftar',
            $pendaftar->id,
            "Berita Acara {$document['nomor_ba']} diunduh."
        );

        return new Response($document['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$document['filename']}\"",
        ]);
    }

    public function sendBaWhatsapp(Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->prodi?->id_kaprodi !== Auth::id()) {
            return $this->errorResponse('Pendaftar bukan dari prodi Anda.', 403);
        }

        if ($pendaftar->status !== StatusPendaftarEnum::APPROVED) {
            return $this->errorResponse('Hanya Berita Acara yang sudah disetujui yang dapat dikirim.', 422);
        }

        if (! is_string($pendaftar->no_whatsapp) || preg_match('/^628[0-9]{7,15}$/', $pendaftar->no_whatsapp) !== 1) {
            return $this->errorResponse('Nomor WhatsApp mahasiswa belum tersedia atau tidak valid.', 422);
        }

        if (PengaturanGlobal::get('notif_wa_aktif', 'false') !== 'true') {
            return $this->errorResponse('Kanal notifikasi WhatsApp belum diaktifkan.', 422);
        }

        if (PengaturanGlobal::get('fonnte_api_key') === '') {
            return $this->errorResponse('Fonnte API key belum dikonfigurasi.', 422);
        }

        $userId = Auth::id();
        if (! is_string($userId)) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        SendBeritaAcaraWhatsappJob::dispatch($pendaftar->id, $userId);
        $this->audit->log(
            'document.ba_whatsapp_queued',
            'Pendaftar',
            $pendaftar->id,
            "Pengiriman Berita Acara {$pendaftar->nomor_ba} dijadwalkan."
        );

        return $this->successResponse(
            ['queued' => true],
            'Berita Acara dijadwalkan untuk dikirim ke WhatsApp mahasiswa.'
        );
    }

    public function revokeBa(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->prodi?->id_kaprodi !== Auth::id()) {
            return $this->errorResponse('Pendaftar bukan dari prodi Anda.', 403);
        }
        $validated = $request->validate(['reason' => 'required|string|max:1000']);
        $document = $pendaftar->currentBaDocument;
        if (! $document instanceof BaDocument) {
            return $this->errorResponse('Dokumen final aktif tidak ditemukan.', 422);
        }
        if ($document->status !== 'final') {
            return $this->errorResponse('Dokumen final aktif tidak ditemukan.', 422);
        }
        $document->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_reason' => $validated['reason'],
        ]);
        $this->audit->log(
            'document.ba_revoked',
            'BaDocument',
            $document->id,
            $validated['reason']
        );

        return $this->successResponse($document->fresh(), 'Berita Acara dicabut.');
    }

    public function replaceBa(Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->prodi?->id_kaprodi !== Auth::id()) {
            return $this->errorResponse('Pendaftar bukan dari prodi Anda.', 403);
        }
        if ($pendaftar->status !== StatusPendaftarEnum::APPROVED) {
            return $this->errorResponse('Hanya permohonan approved yang dapat diterbitkan ulang.', 422);
        }
        $document = $this->beritaAcara->replaceDocument($pendaftar, (string) Auth::id());
        $this->audit->log(
            'document.ba_replaced',
            'BaDocument',
            $document->id,
            "Berita Acara versi {$document->version} diterbitkan."
        );

        return $this->successResponse($document, 'Versi baru Berita Acara diterbitkan.');
    }
}
