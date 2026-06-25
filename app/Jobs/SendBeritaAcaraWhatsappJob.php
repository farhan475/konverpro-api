<?php

namespace App\Jobs;

use App\Models\Pendaftar;
use App\Services\AuditService;
use App\Services\BeritaAcaraWhatsappService;
use App\Services\InternalNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendBeritaAcaraWhatsappJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $pendaftarId,
        public string $initiatedBy
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(
        BeritaAcaraWhatsappService $whatsapp,
        AuditService $audit,
        InternalNotificationService $notifications
    ): void {
        $pendaftar = Pendaftar::findOrFail($this->pendaftarId);
        $whatsapp->send($pendaftar);

        $audit->logAs(
            $this->initiatedBy,
            'document.ba_whatsapp_sent',
            'Pendaftar',
            $pendaftar->id,
            "Berita Acara {$pendaftar->nomor_ba} dikirim ke WhatsApp mahasiswa."
        );
        $notifications->notifyUser(
            $this->initiatedBy,
            'ba_whatsapp_sent',
            'Berita Acara terkirim',
            "Berita Acara {$pendaftar->nama_lengkap} berhasil dikirim ke WhatsApp.",
            "/kaprodi/validasi/{$pendaftar->id}",
            'Pendaftar',
            $pendaftar->id
        );
    }

    public function failed(Throwable $exception): void
    {
        $pendaftar = Pendaftar::find($this->pendaftarId);
        $name = $pendaftar ? $pendaftar->nama_lengkap : 'mahasiswa';

        app(AuditService::class)->logAs(
            $this->initiatedBy,
            'document.ba_whatsapp_failed',
            'Pendaftar',
            $this->pendaftarId,
            $exception->getMessage()
        );
        app(InternalNotificationService::class)->notifyUser(
            $this->initiatedBy,
            'ba_whatsapp_failed',
            'Pengiriman Berita Acara gagal',
            "Berita Acara {$name} gagal dikirim: {$exception->getMessage()}",
            "/kaprodi/validasi/{$this->pendaftarId}",
            'Pendaftar',
            $this->pendaftarId
        );
    }
}
