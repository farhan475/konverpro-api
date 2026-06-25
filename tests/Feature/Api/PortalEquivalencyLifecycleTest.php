<?php

namespace Tests\Feature\Api;

use App\Models\CourseEquivalency;
use App\Models\HasilKonversi;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\TranskripAsal;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalEquivalencyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $akademik;

    private User $kaprodi;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin-konversi@unsia.ac.id')->firstOrFail();
        $this->akademik = User::where('email', 'akademik@unsia.ac.id')->firstOrFail();
        $this->kaprodi = User::where('email', 'syahidabdullah@lecturer.unsia.ac.id')->firstOrFail();
        $this->prodi = Prodi::where('kode_prodi', 'IF')->firstOrFail();
    }

    public function test_public_verification_portal_appeal_and_document_lifecycle(): void
    {
        $pendaftar = $this->approvedApplicant('PORTAL001');
        $token = $pendaftar->portal_token;
        $this->assertIsString($token);
        $document = $pendaftar->currentBaDocument;
        $this->assertNotNull($document);

        $this->getJson("/api/public/verify/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'final')
            ->assertJsonPath('data.program', 'PJJ Informatika');

        $this->getJson("/api/public/portal/{$token}")
            ->assertOk()
            ->assertJsonPath('data.status', 'Approved')
            ->assertJsonPath('data.document.id', $document->id);

        $appealId = $this->postJson("/api/public/portal/{$token}/appeals", [
            'reason' => 'Mohon evaluasi ulang karena terdapat bukti akademik tambahan.',
            'additional_information' => 'Silabus lengkap tersedia dan dapat diverifikasi.',
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'appeal.submitted',
            'subject_id' => $appealId,
        ]);
        $this->assertDatabaseHas('internal_notifications', [
            'id_user' => $this->akademik->id,
            'type' => 'appeal_submitted',
            'subject_id' => $appealId,
        ]);

        $this->withToken($this->akademik->createToken('academic')->plainTextToken)
            ->putJson("/api/akademik/appeals/{$appealId}", [
                'decision' => 'accepted',
                'resolution_notes' => 'Permohonan diterima untuk diproses kembali.',
            ])->assertOk();

        $this->assertSame('Revisi', $pendaftar->fresh()->status->value);
        $this->assertSame('revoked', $document->fresh()->status);
        $this->assertDatabaseHas('internal_notifications', [
            'id_user' => $this->admin->id,
            'type' => 'appeal_resolved',
            'subject_id' => $appealId,
        ]);
        $this->getJson("/api/public/verify/{$document->id}")
            ->assertJsonPath('data.status', 'revoked');
    }

    public function test_approved_mapping_becomes_reusable_equivalency_and_documents_can_be_replaced(): void
    {
        $first = $this->approvedApplicant('REF001');
        $this->assertDatabaseCount('course_equivalencies', 1);

        $second = Pendaftar::create([
            'id_prodi' => $this->prodi->id,
            'created_by' => $this->admin->id,
            'nama_lengkap' => 'Mahasiswa Referensi',
            'nim_asal' => 'REF002',
            'asal_kampus' => 'Universitas Asal',
            'asal_prodi' => 'Informatika',
            'status' => 'Baru',
        ]);
        TranskripAsal::create([
            'id_pendaftar' => $second->id,
            'nama_mk_asal' => 'Pemrograman Dasar',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'A',
        ]);

        app(MatchingService::class)->processMatching($second->fresh('transkripAsal'));
        $this->assertDatabaseHas('hasil_konversi', [
            'id_pendaftar' => $second->id,
            'metode_pemetaan' => 'Referensi',
            'is_unmatched' => false,
        ]);

        $oldDocument = $first->currentBaDocument;
        $this->withToken($this->kaprodi->createToken('kaprodi-replace')->plainTextToken)
            ->postJson("/api/kaprodi/validasi/{$first->id}/replace-ba")
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->assertSame('replaced', $oldDocument?->fresh()->status);
        $this->assertSame(2, $first->fresh()->currentBaDocument?->version);
        $this->assertSame(1, CourseEquivalency::count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.ba_replaced',
            'subject_id' => $first->fresh()->current_ba_document_id,
        ]);

        $equivalency = CourseEquivalency::firstOrFail();
        $superadmin = User::where('role', 'superadmin')->firstOrFail();
        $this->withToken($superadmin->createToken('equivalency')->plainTextToken)
            ->putJson("/api/superadmin/equivalencies/{$equivalency->id}", [
                'is_active' => false,
                'valid_until' => null,
                'alasan' => 'Dinonaktifkan dalam pengujian lifecycle.',
            ])->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'course_equivalency.updated',
            'subject_id' => $equivalency->id,
        ]);
    }

    private function approvedApplicant(string $nim): Pendaftar
    {
        $pendaftar = Pendaftar::create([
            'id_prodi' => $this->prodi->id,
            'created_by' => $this->admin->id,
            'nama_lengkap' => "Mahasiswa {$nim}",
            'nim_asal' => $nim,
            'asal_kampus' => 'Universitas Asal',
            'asal_prodi' => 'Informatika',
            'status' => 'Pending Kaprodi',
        ]);
        $transkrip = TranskripAsal::create([
            'id_pendaftar' => $pendaftar->id,
            'nama_mk_asal' => 'Pemrograman Dasar',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'A',
        ]);
        $target = $this->prodi->kurikulumMk()->firstOrFail();
        HasilKonversi::create([
            'id_pendaftar' => $pendaftar->id,
            'id_transkrip_asal' => $transkrip->id,
            'id_mk_tujuan' => $target->id,
            'nilai_akhir_huruf' => 'A',
            'sks_diakui' => min(3, $target->sks),
            'metode_pemetaan' => 'Manual Kaprodi',
            'is_unmatched' => false,
        ]);

        $this->withToken($this->kaprodi->createToken("approve-{$nim}")->plainTextToken)
            ->postJson("/api/kaprodi/validasi/{$pendaftar->id}/approve")
            ->assertOk();

        return $pendaftar->fresh(['currentBaDocument', 'hasilKonversi']);
    }
}
