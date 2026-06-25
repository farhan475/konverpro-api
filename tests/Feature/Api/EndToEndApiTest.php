<?php

namespace Tests\Feature\Api;

use App\Models\HasilKonversi;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\TranskripAsal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EndToEndApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->artisan('db:seed --class=WhiteTestingSeeder');
    }

    public function test_superadmin_flow(): void
    {
        $user = User::where('role', 'superadmin')->first();
        $this->withToken($user->createToken('test')->plainTextToken);

        $response = $this->getJson('/api/superadmin/dashboard');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['stats', 'recent_audits']]);

        $response = $this->getJson('/api/superadmin/prodi');
        $response->assertStatus(200);

        $this->get('/api/superadmin/laporan?format=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_flow(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->withToken($user->createToken('test')->plainTextToken);

        $response = $this->getJson('/api/admin/dashboard');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['stats', 'recent_pendaftar']]);

        $this->post('/api/admin/pendaftar', [
            'file_excel' => UploadedFile::fake()->create(
                'invalid.xlsx',
                1,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_auth_cookie_flow(): void
    {
        $user = User::where('role', 'admin')->firstOrFail();

        $login = $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $login->assertOk()
            ->assertCookie('konverpro_token')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonPath('data.user.email', $user->email);

        $plainToken = $user->createToken('test_cookie_token')->plainTextToken;

        $this->withHeader('Cookie', 'konverpro_token='.urlencode($plainToken))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_akademik_flow(): void
    {
        $user = User::where('role', 'akademik')->first();
        $this->withToken($user->createToken('test')->plainTextToken);

        $response = $this->getJson('/api/akademik/dashboard');
        $response->assertStatus(200);

        $response = $this->getJson('/api/akademik/antrean');
        $response->assertStatus(200);

        $prodiTanpaKurikulum = Prodi::where('kode_prodi', 'SI')->firstOrFail();
        $pendaftar = Pendaftar::create([
            'id_prodi' => $prodiTanpaKurikulum->id,
            'created_by' => User::where('role', 'admin')->firstOrFail()->id,
            'nama_lengkap' => 'Mahasiswa Tanpa Kurikulum',
            'nim_asal' => 'EMPTY001',
            'status' => 'Baru',
        ]);
        TranskripAsal::create([
            'id_pendaftar' => $pendaftar->id,
            'nama_mk_asal' => 'Analisis Proses Bisnis',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'A',
        ]);

        $this->postJson("/api/akademik/antrean/{$pendaftar->id}/proses")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Proses matching gagal: Data kurikulum prodi tujuan kosong.');
    }

    public function test_kaprodi_flow(): void
    {
        $prodi = Prodi::where('kode_prodi', 'IF')
            ->whereNotNull('id_kaprodi')
            ->whereHas('kurikulumMk')
            ->firstOrFail();
        $user = User::findOrFail($prodi->id_kaprodi);
        $this->withToken($user->createToken('test')->plainTextToken);

        $response = $this->getJson('/api/kaprodi/dashboard');
        $response->assertStatus(200);

        $this->getJson('/api/akademik/kurikulum')
            ->assertOk();

        $this->postJson('/api/akademik/kurikulum', [
            'id_prodi' => $prodi->id,
            'kode_mk' => 'TEST-ACCESS',
            'nama_mk' => 'Mata Kuliah Tidak Boleh Dibuat Kaprodi',
            'sks' => 3,
            'semester' => 1,
            'tipe_mk' => 'Wajib',
        ])->assertForbidden();

        $pendaftar = Pendaftar::create([
            'id_prodi' => $prodi->id,
            'created_by' => User::where('role', 'admin')->firstOrFail()->id,
            'nama_lengkap' => 'Mahasiswa Validasi',
            'nim_asal' => 'VAL001',
            'status' => 'Pending Kaprodi',
        ]);

        $transkrip = TranskripAsal::create([
            'id_pendaftar' => $pendaftar->id,
            'nama_mk_asal' => 'Pemrograman Dasar',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'A',
        ]);

        $mkTujuan = $prodi->kurikulumMk()->firstOrFail();

        $hasil = HasilKonversi::create([
            'id_pendaftar' => $pendaftar->id,
            'id_transkrip_asal' => $transkrip->id,
            'sks_diakui' => 0,
            'is_unmatched' => true,
        ]);

        $this->putJson("/api/kaprodi/validasi/{$hasil->id}", [
            'id_mk_tujuan' => $mkTujuan->id,
            'nilai_akhir_huruf' => 'A',
        ])
            ->assertOk()
            ->assertJsonPath('data.metode_pemetaan', 'Manual Kaprodi')
            ->assertJsonPath('data.is_unmatched', false);

        $this->postJson("/api/kaprodi/validasi/{$pendaftar->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('pendaftar', [
            'id' => $pendaftar->id,
            'status' => 'Approved',
            'total_sks_diakui' => $mkTujuan->sks,
        ]);

        $this->postJson("/api/kaprodi/validasi/{$pendaftar->id}/approve")
            ->assertUnprocessable();

        $this->get("/api/kaprodi/validasi/{$pendaftar->id}/download-ba")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get('/api/kaprodi/laporan?format=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
