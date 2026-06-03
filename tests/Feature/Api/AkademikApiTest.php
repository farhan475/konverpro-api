<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Kampus;
use App\Models\Prodi;
use App\Models\KurikulumMk;
use App\Models\Pendaftar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkademikApiTest extends TestCase
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
            'nama_lengkap' => 'Staff Akademik',
            'email' => 'akademik@test.com',
            'password_hash' => bcrypt('password'),
            'role' => 'akademik',
            'status' => 'active',
        ]);

        $this->prodi = Prodi::create([
            'id_kampus' => $this->kampus->id,
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

    public function test_can_get_dashboard_stats()
    {
        $response = $this->actingAs($this->user)->getJson('/api/akademik/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'total_pendaftar',
                    'antrean_review',
                    'menunggu_kaprodi',
                    'konversi_selesai'
                ],
                'nama_user'
            ]);
    }

    public function test_can_get_antrean_list()
    {
        Pendaftar::create([
            'id' => 'APL_001',
            'id_kampus' => $this->kampus->id,
            'id_prodi' => $this->prodi->id,
            'nama_lengkap' => 'Mhs Baru',
            'status' => 'Baru',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/akademik/antrean');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_prodi_with_kurikulum_for_scanner()
    {
        $response = $this->actingAs($this->user)->getJson('/api/akademik/scanner');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
        
        $response->assertJsonPath('data.0.kurikulum.0.nama_mk', 'Algoritma');
    }

    public function test_can_save_scan_result()
    {
        $payload = [
            'id_prodi' => $this->prodi->id,
            'nama_lengkap' => 'Mahasiswa Transfer',
            'email' => 'transfer@example.com',
            'no_whatsapp' => '08123456789',
            'asal_kampus' => 'Kampus Asal',
            'matches' => [
                [
                    'mk_asal' => 'Pemrograman Dasar',
                    'sks_asal' => 3,
                    'nilai_asal' => 'A'
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson('/api/akademik/scanner', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Transkrip asal berhasil diverifikasi Akademik dan diteruskan ke Kaprodi.'
            ]);

        $this->assertDatabaseHas('pendaftar', [
            'nama_lengkap' => 'Mahasiswa Transfer',
            'status' => 'Pending Kaprodi'
        ]);

        $this->assertDatabaseHas('transkrip_asal', [
            'nama_mk_asal' => 'Pemrograman Dasar',
            'nilai_huruf_asal' => 'A'
        ]);
    }
}
