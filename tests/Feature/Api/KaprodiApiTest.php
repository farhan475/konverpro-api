<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Kampus;
use App\Models\Prodi;
use App\Models\KurikulumMk;
use App\Models\Pendaftar;
use App\Models\TranskripAsal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KaprodiApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $kampus;
    protected $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kampus = Kampus::create([
            'nama_kampus' => 'Test University',
            'email_utama' => 'test@example.com',
            'status_akun' => 'active',
        ]);

        $this->user = User::create([
            'id_kampus' => $this->kampus->id,
            'nama_lengkap' => 'Kaprodi Informatika',
            'email' => 'kaprodi@test.com',
            'password_hash' => bcrypt('password'),
            'role' => 'kaprodi',
            'status' => 'active',
        ]);

        $this->prodi = Prodi::create([
            'id_kampus' => $this->kampus->id,
            'id_kaprodi' => $this->user->id,
            'nama_prodi' => 'Informatika',
            'jenjang' => 'S1',
        ]);

        KurikulumMk::create([
            'id_prodi' => $this->prodi->id,
            'nama_mk' => 'Algoritma',
            'sks' => 3,
            'semester' => 1,
        ]);
    }

    public function test_can_get_mahasiswa_list()
    {
        Pendaftar::create([
            'id' => 'APL_001',
            'id_kampus' => $this->kampus->id,
            'id_prodi' => $this->prodi->id,
            'nama_lengkap' => 'Mhs Baru',
            'status' => 'Approved',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/kaprodi/mahasiswa');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_validasi_list()
    {
        Pendaftar::create([
            'id' => 'APL_002',
            'id_kampus' => $this->kampus->id,
            'id_prodi' => $this->prodi->id,
            'nama_lengkap' => 'Mhs Pending',
            'status' => 'Pending Kaprodi',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/kaprodi/validasi');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_validasi_detail()
    {
        $pendaftar = Pendaftar::create([
            'id' => 'APL_003',
            'id_kampus' => $this->kampus->id,
            'id_prodi' => $this->prodi->id,
            'nama_lengkap' => 'Mhs Detail',
            'status' => 'Pending Kaprodi',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/kaprodi/validasi/{$pendaftar->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nama_lengkap',
                    'transkrip_asal',
                    'kurikulum_target',
                    'hasil_konversi'
                ]
            ]);
    }

    public function test_can_process_validasi()
    {
        $pendaftar = Pendaftar::create([
            'id' => 'APL_004',
            'id_kampus' => $this->kampus->id,
            'id_prodi' => $this->prodi->id,
            'nama_lengkap' => 'Mhs Process',
            'status' => 'Pending Kaprodi',
        ]);

        $mk = KurikulumMk::where('id_prodi', $this->prodi->id)->first();
        $transkrip = TranskripAsal::create([
            'id_pendaftar' => $pendaftar->id,
            'nama_mk_asal' => 'Algo Asal',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'A'
        ]);

        $payload = [
            'status' => 'Approved',
            'catatan' => 'OK',
            'mappings' => [
                [
                    'id_mk_tujuan' => $mk->id,
                    'id_transkrip_asal' => $transkrip->id,
                    'nilai_akhir_huruf' => 'A'
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->patchJson("/api/kaprodi/validasi/{$pendaftar->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('pendaftar', [
            'id' => $pendaftar->id,
            'status' => 'Approved'
        ]);

        $this->assertDatabaseHas('hasil_konversi', [
            'id_pendaftar' => $pendaftar->id,
            'id_mk_tujuan' => $mk->id,
            'id_transkrip_asal' => $transkrip->id
        ]);
    }

    public function test_can_get_pemetaan_kurikulum()
    {
        $response = $this->actingAs($this->user)->getJson('/api/kaprodi/pemetaan');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }
}
