<?php

namespace App\Jobs;

use App\Enums\StatusPendaftarEnum;
use App\Models\Pendaftar;
use App\Services\AuditService;
use App\Services\InternalNotificationService;
use App\Services\MatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessMatchingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public string $pendaftarId,
        public ?string $initiatedBy = null
    ) {
        $this->onQueue('matching');
        $this->afterCommit();
    }

    public function handle(
        MatchingService $matching,
        AuditService $audit,
        InternalNotificationService $notifications
    ): void {
        $pendaftar = Pendaftar::findOrFail($this->pendaftarId);
        $matching->processMatching($pendaftar);

        $audit->logAs(
            $this->initiatedBy,
            'matching.processed',
            'Pendaftar',
            $pendaftar->id,
            "Matching selesai untuk {$pendaftar->nama_lengkap}"
        );

        $notifications->notifyRole(
            'akademik',
            'matching_completed',
            'Matching selesai',
            "Hasil matching {$pendaftar->nama_lengkap} siap direview.",
            "/akademik/antrean/{$pendaftar->id}",
            'Pendaftar',
            $pendaftar->id
        );
    }

    public function failed(Throwable $exception): void
    {
        $pendaftar = Pendaftar::find($this->pendaftarId);
        if (! $pendaftar) {
            return;
        }

        $pendaftar->update(['status' => StatusPendaftarEnum::BARU]);
        app(InternalNotificationService::class)->notifyRole(
            'akademik',
            'matching_failed',
            'Matching gagal',
            "Matching {$pendaftar->nama_lengkap} gagal: {$exception->getMessage()}",
            "/akademik/antrean/{$pendaftar->id}",
            'Pendaftar',
            $pendaftar->id
        );
    }
}
