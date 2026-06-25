<?php

namespace Tests\Feature\Api;

use App\Models\HasilKonversi;
use App\Models\KurikulumMk;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\TranskripAsal;
use App\Models\User;
use Database\Seeders\WhiteTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $akademik;

    private User $kaprodi;

    private User $superadmin;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(WhiteTestingSeeder::class);

        $this->admin = User::where('email', 'admin@test.com')->firstOrFail();
        $this->akademik = User::where('email', 'akademik@test.com')->firstOrFail();
        $this->kaprodi = User::where('email', 'kaprodi@test.com')->firstOrFail();
        $this->superadmin = User::where('email', 'admin@unsia.ac.id')->firstOrFail();
        $this->prodi = Prodi::where('id_kaprodi', $this->kaprodi->id)->firstOrFail();
    }

    public function test_inactive_user_token_is_rejected_and_revoked(): void
    {
        $token = $this->admin->createToken('inactive-test')->plainTextToken;
        $this->admin->update(['status' => 'inactive']);

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rate_limit_is_scoped_per_email_and_ip(): void
    {
        $ip = '127.0.0.1';
        $lockedEmail = 'locked@example.com';
        RateLimiter::clear("login:{$lockedEmail}|{$ip}");
        RateLimiter::clear("login-ip:{$ip}");

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => $lockedEmail,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'email' => $lockedEmail,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        $this->postJson('/api/auth/login', [
            'email' => 'different@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        RateLimiter::clear("login:{$lockedEmail}|{$ip}");
        RateLimiter::clear("login:different@example.com|{$ip}");
        RateLimiter::clear("login-ip:{$ip}");
    }

    public function test_shared_prodi_reference_does_not_expose_kaprodi_identity(): void
    {
        $this->authenticate($this->admin);

        $response = $this->getJson('/api/referensi/prodi')
            ->assertOk()
            ->assertJsonMissingPath('data.0.kaprodi')
            ->assertJsonMissingPath('data.0.id_kaprodi');

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_superadmin_cannot_assign_non_kaprodi_or_remove_last_active_superadmin(): void
    {
        $this->authenticate($this->superadmin);

        $this->putJson("/api/superadmin/prodi/{$this->prodi->id}", [
            'id_kaprodi' => $this->admin->id,
        ])->assertUnprocessable();

        $this->putJson("/api/superadmin/users/{$this->superadmin->id}", [
            'role' => 'admin',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $this->superadmin->id,
            'role' => 'superadmin',
            'status' => 'active',
        ]);

        $this->getJson("/api/superadmin/users/{$this->superadmin->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $this->superadmin->email);

        $this->getJson("/api/superadmin/prodi/{$this->prodi->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->prodi->id);
    }

    public function test_kaprodi_cannot_map_across_prodi_exceed_sks_or_edit_approved_result(): void
    {
        $this->authenticate($this->kaprodi);
        [$pendaftar, $hasil] = $this->makePendingResult();
        $otherProdi = Prodi::where('id', '!=', $this->prodi->id)->firstOrFail();
        $otherCourse = KurikulumMk::create([
            'id_prodi' => $otherProdi->id,
            'kode_mk' => 'OTHER-101',
            'nama_mk' => 'Mata Kuliah Prodi Lain',
            'sks' => 3,
            'semester' => 1,
            'tipe_mk' => 'Wajib',
        ]);

        $this->putJson("/api/kaprodi/validasi/{$hasil->id}", [
            'id_mk_tujuan' => $otherCourse->id,
            'sks_diakui' => 3,
        ])->assertUnprocessable();

        $this->putJson("/api/kaprodi/validasi/{$hasil->id}", [
            'sks_diakui' => 99,
        ])->assertUnprocessable();

        $pendaftar->update(['status' => 'Approved']);
        $this->putJson("/api/kaprodi/validasi/{$hasil->id}", [
            'sks_diakui' => 1,
        ])->assertUnprocessable();
    }

    public function test_bulk_approval_enforces_program_conversion_limit(): void
    {
        $this->authenticate($this->kaprodi);
        [$pendaftar] = $this->makePendingResult();
        $this->prodi->pengaturan()->update(['max_konversi_sks_persen' => 1]);

        $this->postJson('/api/kaprodi/validasi/bulk-approve', [
            'ids' => [$pendaftar->id],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('pendaftar', [
            'id' => $pendaftar->id,
            'status' => 'Pending Kaprodi',
        ]);
    }

    public function test_akademik_cannot_update_transcript_owned_by_another_applicant(): void
    {
        $this->authenticate($this->akademik);
        $first = $this->makeApplicant('AKD001', 'Baru');
        $second = $this->makeApplicant('AKD002', 'Baru');
        $foreignTranscript = TranskripAsal::create([
            'id_pendaftar' => $second->id,
            'nama_mk_asal' => 'Basis Data',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'A',
        ]);

        $this->putJson("/api/akademik/antrean/{$first->id}", [
            'nama_lengkap' => $first->nama_lengkap,
            'nim_asal' => $first->nim_asal,
            'id_prodi' => $first->id_prodi,
            'transkrip' => [[
                'id' => $foreignTranscript->id,
                'nama_mk_asal' => 'Diubah Secara Tidak Sah',
                'sks_asal' => 2,
                'nilai_huruf_asal' => 'B',
            ]],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('transkrip_asal', [
            'id' => $foreignTranscript->id,
            'nama_mk_asal' => 'Basis Data',
        ]);
    }

    public function test_locked_or_used_curriculum_cannot_be_mutated(): void
    {
        $this->authenticate($this->akademik);
        $locked = $this->prodi->kurikulumMk()->firstOrFail();
        $locked->update(['is_locked' => true]);

        $this->putJson("/api/akademik/kurikulum/{$locked->id}", [
            'nama_mk' => 'Nama Baru',
        ])->assertUnprocessable();

        $this->deleteJson("/api/akademik/kurikulum/{$locked->id}")
            ->assertUnprocessable();

        $locked->update(['is_locked' => false]);
        [, $hasil] = $this->makePendingResult($locked);

        $this->deleteJson("/api/akademik/kurikulum/{$locked->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('hasil_konversi', ['id' => $hasil->id]);
    }

    public function test_config_validation_and_list_filters_are_enforced(): void
    {
        $this->authenticate($this->superadmin);
        $this->putJson('/api/superadmin/config', [
            'settings' => [
                'fuzzy_threshold_auto' => '50',
                'fuzzy_threshold_sumopod' => '80',
            ],
        ])->assertUnprocessable();

        $this->authenticate($this->akademik);
        $visible = $this->makeApplicant('FILTER001', 'Baru');
        $this->makeApplicant('FILTER002', 'Revisi');

        $this->getJson('/api/akademik/antrean?status=Baru&search=FILTER001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);

        $this->getJson('/api/akademik/antrean?status=Approved')
            ->assertUnprocessable();
    }

    /** @return array{Pendaftar, HasilKonversi} */
    private function makePendingResult(?KurikulumMk $course = null): array
    {
        $pendaftar = $this->makeApplicant('VAL-'.fake()->unique()->numerify('####'), 'Pending Kaprodi');
        $transcript = TranskripAsal::create([
            'id_pendaftar' => $pendaftar->id,
            'nama_mk_asal' => 'Pemrograman Dasar',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'A',
        ]);
        $course ??= $this->prodi->kurikulumMk()->firstOrFail();

        $hasil = HasilKonversi::create([
            'id_pendaftar' => $pendaftar->id,
            'id_transkrip_asal' => $transcript->id,
            'id_mk_tujuan' => $course->id,
            'nilai_akhir_huruf' => 'A',
            'sks_diakui' => min(3, $course->sks),
            'metode_pemetaan' => 'Fuzzy',
            'match_score' => 100,
            'is_unmatched' => false,
        ]);

        return [$pendaftar, $hasil];
    }

    private function makeApplicant(string $nim, string $status): Pendaftar
    {
        return Pendaftar::create([
            'id_prodi' => $this->prodi->id,
            'created_by' => $this->admin->id,
            'nama_lengkap' => "Mahasiswa {$nim}",
            'nim_asal' => $nim,
            'status' => $status,
        ]);
    }

    private function authenticate(User $user): void
    {
        $this->withToken($user->createToken('test')->plainTextToken);
    }
}
