<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessMatchingJob;
use App\Jobs\SendBeritaAcaraWhatsappJob;
use App\Jobs\SendPendaftarNotificationJob;
use App\Models\HasilKonversi;
use App\Models\InternalNotification;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Models\Prodi;
use App\Models\TranskripAsal;
use App\Models\User;
use Database\Seeders\WhiteTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(WhiteTestingSeeder::class);
    }

    public function test_matching_and_external_notifications_are_dispatched_to_dedicated_queues(): void
    {
        Queue::fake();
        $akademik = User::where('email', 'akademik@test.com')->firstOrFail();
        $kaprodi = User::where('email', 'kaprodi@test.com')->firstOrFail();
        $admin = User::where('email', 'admin@test.com')->firstOrFail();
        $prodi = Prodi::where('id_kaprodi', $kaprodi->id)->firstOrFail();

        $pendaftar = Pendaftar::create([
            'id_prodi' => $prodi->id,
            'created_by' => $admin->id,
            'nama_lengkap' => 'Mahasiswa Queue',
            'nim_asal' => 'QUEUE001',
            'status' => 'Baru',
        ]);
        $transkrip = TranskripAsal::create([
            'id_pendaftar' => $pendaftar->id,
            'nama_mk_asal' => 'Pemrograman Dasar',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'A',
        ]);

        $this->withToken($akademik->createToken('test')->plainTextToken)
            ->postJson("/api/akademik/antrean/{$pendaftar->id}/proses")
            ->assertOk();

        Queue::assertPushedOn('matching', ProcessMatchingJob::class);
        $this->assertSame('AI Processing', $pendaftar->fresh()->status->value);

        $course = $prodi->kurikulumMk()->firstOrFail();
        $pendaftar->update(['status' => 'Pending Kaprodi']);
        HasilKonversi::create([
            'id_pendaftar' => $pendaftar->id,
            'id_transkrip_asal' => $transkrip->id,
            'id_mk_tujuan' => $course->id,
            'nilai_akhir_huruf' => 'A',
            'sks_diakui' => min(3, $course->sks),
            'metode_pemetaan' => 'Fuzzy',
            'match_score' => 100,
            'is_unmatched' => false,
        ]);

        $this->withToken($kaprodi->createToken('test')->plainTextToken)
            ->postJson("/api/kaprodi/validasi/{$pendaftar->id}/approve")
            ->assertOk();

        Queue::assertPushedOn('notifications', SendPendaftarNotificationJob::class);
        $this->assertDatabaseHas('internal_notifications', [
            'id_user' => $admin->id,
            'type' => 'conversion_approved',
            'subject_id' => $pendaftar->id,
        ]);
    }

    public function test_notification_access_is_private_and_read_state_is_persisted(): void
    {
        $admin = User::where('email', 'admin@test.com')->firstOrFail();
        $otherAdmin = User::create([
            'nama_lengkap' => 'Admin Lain',
            'email' => 'admin-lain@example.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $notification = InternalNotification::create([
            'id_user' => $admin->id,
            'type' => 'test',
            'title' => 'Notifikasi Privat',
            'message' => 'Hanya pemilik yang dapat membacanya.',
            'created_at' => now(),
        ]);

        $this->withToken($otherAdmin->createToken('test')->plainTextToken)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertForbidden();

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.id', $notification->id);

        $this->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_kaprodi_can_queue_approved_ba_for_whatsapp(): void
    {
        Queue::fake();
        PengaturanGlobal::set('notif_wa_aktif', 'true');
        PengaturanGlobal::set('fonnte_api_key', 'test-key');

        $kaprodi = User::where('email', 'kaprodi@test.com')->firstOrFail();
        $admin = User::where('email', 'admin@test.com')->firstOrFail();
        $prodi = Prodi::where('id_kaprodi', $kaprodi->id)->firstOrFail();
        $pendaftar = Pendaftar::create([
            'id_prodi' => $prodi->id,
            'created_by' => $admin->id,
            'nama_lengkap' => 'Mahasiswa BA WhatsApp',
            'nim_asal' => 'BAWA001',
            'no_whatsapp' => '628123456789',
            'status' => 'Approved',
            'total_sks_diakui' => 3,
            'nomor_ba' => 'BA/2026/001/IF',
            'approved_at' => now(),
            'hash_ba_digital' => str_repeat('a', 64),
        ]);

        $this->withToken($kaprodi->createToken('test')->plainTextToken)
            ->postJson("/api/kaprodi/validasi/{$pendaftar->id}/send-ba-whatsapp")
            ->assertOk()
            ->assertJsonPath('data.queued', true);

        Queue::assertPushedOn('notifications', SendBeritaAcaraWhatsappJob::class);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.ba_whatsapp_queued',
            'subject_id' => $pendaftar->id,
        ]);
    }
}
