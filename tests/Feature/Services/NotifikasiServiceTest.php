<?php

namespace Tests\Feature\Services;

use App\Mail\DynamicNotificationMail;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Models\Prodi;
use App\Models\User;
use App\Services\NotifikasiService;
use Database\Seeders\WhiteTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifikasiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(WhiteTestingSeeder::class);
    }

    public function test_approved_sends_email_and_whatsapp_when_enabled(): void
    {
        Mail::fake();
        Http::fake([
            'api.fonnte.com/send' => Http::response(['status' => true]),
        ]);

        PengaturanGlobal::set('notif_email_aktif', 'true');
        PengaturanGlobal::set('notif_wa_aktif', 'true');
        PengaturanGlobal::set('fonnte_api_key', 'test-fonnte-key');

        $pendaftar = $this->makePendaftar([
            'email' => 'mahasiswa@example.test',
            'no_whatsapp' => '628123456789',
            'total_sks_diakui' => 12,
        ]);

        app(NotifikasiService::class)->send($pendaftar->load('prodi'), 'Approved');

        Mail::assertSent(DynamicNotificationMail::class, function (DynamicNotificationMail $mail) {
            return $mail->hasTo('mahasiswa@example.test')
                && str_contains($mail->dynamicSubject, 'Disetujui');
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->hasHeader('Authorization', 'test-fonnte-key')
                && $request['target'] === '628123456789';
        });

        $this->assertNotNull($pendaftar->fresh()->notif_sent_at);
    }

    public function test_revision_email_is_sent_to_the_admin_creator(): void
    {
        Mail::fake();
        Http::fake();

        PengaturanGlobal::set('notif_email_aktif', 'true');
        PengaturanGlobal::set('notif_wa_aktif', 'true');
        PengaturanGlobal::set('fonnte_api_key', 'test-fonnte-key');

        $admin = User::where('role', 'admin')->firstOrFail();
        $pendaftar = $this->makePendaftar([
            'created_by' => $admin->id,
            'email' => 'mahasiswa@example.test',
            'no_whatsapp' => '628123456789',
            'catatan_revisi' => 'Periksa kembali mata kuliah asal.',
        ]);

        app(NotifikasiService::class)->send($pendaftar, 'Revisi');

        Mail::assertSent(DynamicNotificationMail::class, function (DynamicNotificationMail $mail) use ($admin) {
            return $mail->hasTo($admin->email)
                && str_contains($mail->dynamicSubject, 'Permintaan Revisi');
        });
        Http::assertNothingSent();
        $this->assertNotNull($pendaftar->fresh()->notif_sent_at);
    }

    public function test_disabled_channels_do_not_mark_notification_as_sent(): void
    {
        Mail::fake();
        Http::fake();

        PengaturanGlobal::set('notif_email_aktif', 'false');
        PengaturanGlobal::set('notif_wa_aktif', 'false');

        $pendaftar = $this->makePendaftar([
            'email' => 'mahasiswa@example.test',
            'no_whatsapp' => '628123456789',
        ]);

        app(NotifikasiService::class)->send($pendaftar, 'Rejected');

        Mail::assertNothingSent();
        Http::assertNothingSent();
        $this->assertNull($pendaftar->fresh()->notif_sent_at);
    }

    /** @param array<string, mixed> $overrides */
    private function makePendaftar(array $overrides = []): Pendaftar
    {
        $prodi = Prodi::where('kode_prodi', 'IF')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();

        return Pendaftar::create(array_merge([
            'id_prodi' => $prodi->id,
            'created_by' => $admin->id,
            'nama_lengkap' => 'Mahasiswa Notifikasi',
            'nim_asal' => 'NOTIF001',
            'status' => 'Pending Kaprodi',
        ], $overrides));
    }
}
