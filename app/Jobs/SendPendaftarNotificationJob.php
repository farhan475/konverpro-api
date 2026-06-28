<?php

namespace App\Jobs;

use App\Models\Pendaftar;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPendaftarNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $pendaftarId,
        public string $status,
        public ?string $initiatedBy = null
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function failed(?\Throwable $exception): void
    {
        $audit = app(AuditService::class);
        $audit->log(
            'notification.send_failed',
            'Pendaftar',
            $this->pendaftarId,
            "Notifikasi status {$this->status} gagal dikirim setelah {$this->tries} percobaan: {$exception?->getMessage()}"
        );
    }

    public function handle(NotifikasiService $notifications, AuditService $audit): void
    {
        $pendaftar = Pendaftar::with(['creator', 'prodi'])->findOrFail($this->pendaftarId);
        $sent = $notifications->send($pendaftar, $this->status);

        $audit->logAs(
            $this->initiatedBy,
            $sent ? 'notification.sent' : 'notification.skipped',
            'Pendaftar',
            $pendaftar->id,
            "Notifikasi status {$this->status} untuk {$pendaftar->nama_lengkap}"
        );
    }
}
