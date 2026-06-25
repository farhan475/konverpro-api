<?php

namespace Tests\Feature\Api;

use App\Models\HasilKonversi;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\TranskripAsal;
use App\Models\User;
use Database\Seeders\WhiteTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KaprodiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $kaprodi;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(WhiteTestingSeeder::class);

        $this->kaprodi = User::where('email', 'kaprodi@test.com')->firstOrFail();
        $this->prodi = Prodi::where('id_kaprodi', $this->kaprodi->id)->firstOrFail();
        $this->withToken($this->kaprodi->createToken('test')->plainTextToken);
    }

    public function test_revision_and_rejection_only_accept_pending_applicants(): void
    {
        $revision = $this->makePendingApplicant('REV001');
        $rejected = $this->makePendingApplicant('REJ001');

        $this->postJson("/api/kaprodi/validasi/{$revision->id}/revisi", [
            'catatan' => 'Mohon koreksi mata kuliah asal.',
        ])
            ->assertOk();

        $this->assertDatabaseHas('pendaftar', [
            'id' => $revision->id,
            'status' => 'Revisi',
            'catatan_revisi' => 'Mohon koreksi mata kuliah asal.',
        ]);

        $this->postJson("/api/kaprodi/validasi/{$revision->id}/revisi", [
            'catatan' => 'Tidak boleh diproses dua kali.',
        ])->assertUnprocessable();

        $this->postJson("/api/kaprodi/validasi/{$rejected->id}/reject", [
            'alasan' => 'Dokumen tidak memenuhi ketentuan.',
        ])
            ->assertOk();

        $this->assertDatabaseHas('pendaftar', [
            'id' => $rejected->id,
            'status' => 'Rejected',
            'catatan_revisi' => 'Dokumen tidak memenuhi ketentuan.',
        ]);

        $this->postJson("/api/kaprodi/validasi/{$rejected->id}/reject", [
            'alasan' => 'Tidak boleh diproses dua kali.',
        ])->assertUnprocessable();
    }

    public function test_bulk_approve_only_processes_owned_pending_applicants(): void
    {
        $first = $this->makePendingApplicant('BULK001', true);
        $second = $this->makePendingApplicant('BULK002', true);
        $notPending = $this->makePendingApplicant('BULK003', true);
        $notPending->update(['status' => 'Revisi']);

        $this->postJson('/api/kaprodi/validasi/bulk-approve', [
            'ids' => [$first->id, $second->id, $notPending->id],
        ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('pendaftar', ['id' => $first->id, 'status' => 'Pending Kaprodi']);
        $this->assertDatabaseHas('pendaftar', ['id' => $second->id, 'status' => 'Pending Kaprodi']);
        $this->assertDatabaseHas('pendaftar', ['id' => $notPending->id, 'status' => 'Revisi']);

        $this->postJson('/api/kaprodi/validasi/bulk-approve', [
            'ids' => [$first->id, $second->id],
        ])
            ->assertOk()
            ->assertJsonPath('message', '2 permohonan berhasil disetujui.');
    }

    private function makePendingApplicant(string $nim, bool $withMatch = false): Pendaftar
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $pendaftar = Pendaftar::create([
            'id_prodi' => $this->prodi->id,
            'created_by' => $admin->id,
            'nama_lengkap' => "Mahasiswa {$nim}",
            'nim_asal' => $nim,
            'status' => 'Pending Kaprodi',
        ]);

        if ($withMatch) {
            $transkrip = TranskripAsal::create([
                'id_pendaftar' => $pendaftar->id,
                'nama_mk_asal' => 'Pemrograman Dasar',
                'sks_asal' => 3,
                'nilai_huruf_asal' => 'A',
            ]);
            $mkTujuan = $this->prodi->kurikulumMk()->firstOrFail();

            HasilKonversi::create([
                'id_pendaftar' => $pendaftar->id,
                'id_transkrip_asal' => $transkrip->id,
                'id_mk_tujuan' => $mkTujuan->id,
                'nilai_akhir_huruf' => 'A',
                'sks_diakui' => min(3, $mkTujuan->sks),
                'metode_pemetaan' => 'Fuzzy',
                'match_score' => 100,
                'is_unmatched' => false,
            ]);
        }

        return $pendaftar;
    }
}
