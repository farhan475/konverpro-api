<?php

namespace Tests\Feature\Api;

use App\Models\HasilKonversi;
use App\Models\InternalNotification;
use App\Models\KamusSinonim;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CompleteWorkflowUatTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $admin;

    private User $akademik;

    private User $kaprodi;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superadmin = User::where('email', 'admin@unsia.ac.id')->firstOrFail();
        $this->admin = User::where('email', 'admin-konversi@unsia.ac.id')->firstOrFail();
        $this->akademik = User::where('email', 'akademik@unsia.ac.id')->firstOrFail();
        $this->kaprodi = User::where('email', 'syahidabdullah@lecturer.unsia.ac.id')->firstOrFail();
        $this->prodi = Prodi::where('id_kaprodi', $this->kaprodi->id)->firstOrFail();
    }

    public function test_complete_dummy_uat_workflow_for_every_role(): void
    {
        Storage::fake('private');

        $this->runSuperadminMasterDataTasks();
        [$approved, $revision, $rejected] = $this->runAdminUploadTasks();
        $this->runAcademicReviewTasks([$approved, $revision, $rejected]);
        $this->runKaprodiDecisionTasks($approved, $revision, $rejected);
        $this->runRevisionLoop($revision);
        $this->verifyAdminResultsAndNotifications($approved, $revision, $rejected);
        $this->verifySuperadminMonitoring();
    }

    private function runSuperadminMasterDataTasks(): void
    {
        $this->authenticate($this->superadmin);

        $createdUser = $this->postJson('/api/superadmin/users', [
            'nama_lengkap' => 'User UAT Sementara',
            'email' => 'uat-temp@unsia.ac.id',
            'password' => 'password123',
            'role' => 'admin',
            'no_whatsapp' => '628111111111',
        ])->assertCreated()->json('data');

        $this->getJson("/api/superadmin/users/{$createdUser['id']}")
            ->assertOk()
            ->assertJsonPath('data.email', 'uat-temp@unsia.ac.id');
        $this->putJson("/api/superadmin/users/{$createdUser['id']}", [
            'nama_lengkap' => 'User UAT Diperbarui',
            'status' => 'inactive',
        ])->assertOk();
        $this->deleteJson("/api/superadmin/users/{$createdUser['id']}")->assertOk();

        $createdProdi = $this->postJson('/api/superadmin/prodi', [
            'nama_prodi' => 'PJJ UAT Sementara',
            'kode_prodi' => 'UAT',
            'jenjang' => 'S1',
        ])->assertCreated()->json('data');
        $this->putJson("/api/superadmin/prodi/{$createdProdi['id']}/settings", [
            'min_nilai_huruf' => 'C',
            'max_konversi_sks_persen' => 60,
            'format_no_ba' => 'BA/{YEAR}/{NO}/{PRODI}',
            'metode_pengakuan' => 'direct',
        ])->assertOk();
        $this->getJson("/api/superadmin/prodi/{$createdProdi['id']}")->assertOk();
        $this->deleteJson("/api/superadmin/prodi/{$createdProdi['id']}")->assertOk();

        $kamus = $this->postJson('/api/superadmin/kamus-sinonim', [
            'kata_utama' => 'rekayasa perangkat lunak',
            'sinonim' => 'software engineering uat',
            'keterangan' => 'Data UAT',
        ])->assertCreated()->json('data');
        $this->putJson("/api/superadmin/kamus-sinonim/{$kamus['id']}", [
            'kata_utama' => 'rekayasa perangkat lunak',
            'sinonim' => 'software engineering testing',
            'keterangan' => 'Diperbarui saat UAT',
        ])->assertOk();
        $this->deleteJson("/api/superadmin/kamus-sinonim/{$kamus['id']}")->assertOk();

        $this->putJson('/api/superadmin/config', [
            'settings' => [
                'fuzzy_threshold_auto' => '80',
                'fuzzy_threshold_sumopod' => '50',
                'notif_email_aktif' => 'false',
                'notif_wa_aktif' => 'false',
            ],
        ])->assertOk();

        $this->getJson('/api/superadmin/dashboard')->assertOk();
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'config_updated');
    }

    /**
     * @return array{Pendaftar, Pendaftar, Pendaftar}
     */
    private function runAdminUploadTasks(): array
    {
        $this->authenticate($this->admin);
        $this->get('/api/admin/template-excel')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $workbookPath = $this->makeWorkbook();
        try {
            $response = $this->post('/api/admin/pendaftar', [
                'file_excel' => new UploadedFile(
                    $workbookPath,
                    'UAT_Konversi.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ], ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJsonCount(3, 'data');
        } finally {
            @unlink($workbookPath);
        }

        $ids = collect($response->json('data'))->pluck('id');
        $pendaftars = Pendaftar::whereIn('id', $ids)->orderBy('nim_asal')->get();
        $this->assertCount(3, $pendaftars);

        foreach ($pendaftars as $pendaftar) {
            $expectedTranscripts = $pendaftar->nim_asal === 'UAT001' ? 2 : 1;
            $this->getJson("/api/admin/pendaftar/{$pendaftar->id}")
                ->assertOk()
                ->assertJsonCount($expectedTranscripts, 'data.pendaftar.transkrip_asal')
                ->assertJsonPath('data.pendaftar.id', $pendaftar->id);
            $this->get("/api/files/excel/{$pendaftar->id}")->assertOk();
        }

        $this->getJson('/api/admin/pendaftar?status=Baru&search=UAT')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        return [$pendaftars[0], $pendaftars[1], $pendaftars[2]];
    }

    /**
     * @param  array<int, Pendaftar>  $pendaftars
     */
    private function runAcademicReviewTasks(array $pendaftars): void
    {
        $this->authenticate($this->akademik);
        $this->getJson('/api/akademik/dashboard')->assertOk();
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'application_created');

        $temporaryCourse = $this->postJson('/api/akademik/kurikulum', [
            'id_prodi' => $this->prodi->id,
            'kode_mk' => 'UAT-COURSE',
            'nama_mk' => 'Mata Kuliah UAT',
            'sks' => 2,
            'semester' => 8,
            'tipe_mk' => 'Pilihan',
        ])->assertCreated()->json('data');
        $this->putJson("/api/akademik/kurikulum/{$temporaryCourse['id']}", [
            'nama_mk' => 'Mata Kuliah UAT Diperbarui',
        ])->assertOk();
        $this->deleteJson("/api/akademik/kurikulum/{$temporaryCourse['id']}")->assertOk();

        $kamus = $this->postJson('/api/akademik/kamus-sinonim', [
            'kata_utama' => 'algoritma dan pemrograman',
            'sinonim' => 'algoritma uat',
            'keterangan' => 'UAT Akademik',
        ])->assertCreated()->json('data');
        $this->putJson("/api/akademik/kamus-sinonim/{$kamus['id']}", [
            'kata_utama' => 'algoritma dan pemrograman',
            'sinonim' => 'algoritma uat revisi',
            'keterangan' => 'UAT Akademik diperbarui',
        ])->assertOk();
        $this->deleteJson("/api/akademik/kamus-sinonim/{$kamus['id']}")->assertOk();

        foreach ($pendaftars as $pendaftar) {
            $pendaftar->refresh()->load('transkripAsal');
            $transkrip = $pendaftar->transkripAsal->firstOrFail();
            $this->getJson("/api/akademik/antrean/{$pendaftar->id}")->assertOk();
            $this->putJson("/api/akademik/antrean/{$pendaftar->id}", [
                'nama_lengkap' => $pendaftar->nama_lengkap,
                'nim_asal' => $pendaftar->nim_asal,
                'email' => $pendaftar->email,
                'no_whatsapp' => $pendaftar->no_whatsapp,
                'asal_kampus' => $pendaftar->asal_kampus,
                'asal_prodi' => $pendaftar->asal_prodi,
                'id_prodi' => $pendaftar->id_prodi,
                'transkrip' => [[
                    'id' => $transkrip->id,
                    'nama_mk_asal' => $transkrip->nama_mk_asal,
                    'sks_asal' => $transkrip->sks_asal,
                    'nilai_huruf_asal' => $transkrip->nilai_huruf_asal,
                ]],
            ])->assertOk();

            $this->postJson("/api/akademik/antrean/{$pendaftar->id}/proses")
                ->assertOk()
                ->assertJsonPath('message', 'Proses matching dijadwalkan.');
            $this->assertSame('Review Akademik', $pendaftar->fresh()->status->value);
            $this->postJson("/api/akademik/antrean/{$pendaftar->id}/confirm")->assertOk();
            $this->assertSame('Pending Kaprodi', $pendaftar->fresh()->status->value);
        }
    }

    private function runKaprodiDecisionTasks(
        Pendaftar $approved,
        Pendaftar $revision,
        Pendaftar $rejected
    ): void {
        $this->authenticate($this->kaprodi);
        $this->getJson('/api/kaprodi/dashboard')->assertOk();
        $this->getJson('/api/kaprodi/validasi?status=Pending%20Kaprodi')
            ->assertOk()
            ->assertJsonCount(3, 'data');
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 3);

        $approvedResult = HasilKonversi::where('id_pendaftar', $approved->id)->firstOrFail();
        $this->putJson("/api/kaprodi/validasi/{$approvedResult->id}", [
            'id_mk_tujuan' => $approvedResult->id_mk_tujuan,
            'sks_diakui' => $approvedResult->sks_diakui,
            'nilai_akhir_huruf' => 'A',
        ])->assertOk()->assertJsonPath('data.metode_pemetaan', 'Manual Kaprodi');

        $this->postJson("/api/kaprodi/validasi/{$revision->id}/revisi", [
            'catatan' => 'Periksa kembali nama mata kuliah asal.',
        ])->assertOk();
        $this->postJson("/api/kaprodi/validasi/{$rejected->id}/reject", [
            'alasan' => 'Dokumen UAT tidak memenuhi syarat simulasi.',
        ])->assertOk();

        $this->postJson("/api/kaprodi/validasi/{$approved->id}/approve")->assertOk();
        $approved->refresh();
        $this->assertSame('Approved', $approved->status->value);
        $this->assertNotNull($approved->approved_at);
        $this->assertNotNull($approved->nomor_ba);
        $this->assertSame(64, strlen((string) $approved->hash_ba_digital));
        $this->assertDatabaseHas('hasil_konversi', [
            'id_pendaftar' => $approved->id,
            'is_unmatched' => true,
            'sks_diakui' => 0,
        ]);

        $firstBa = $this->get("/api/kaprodi/validasi/{$approved->id}/download-ba")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(5000, strlen($firstBa->getContent()));
        $artifactDir = getenv('UAT_ARTIFACT_DIR');
        if (is_string($artifactDir) && $artifactDir !== '') {
            if (! is_dir($artifactDir)) {
                mkdir($artifactDir, 0777, true);
            }
            file_put_contents(
                rtrim($artifactDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'berita-acara-uat.pdf',
                $firstBa->getContent()
            );
        }
        $stableNumber = $approved->nomor_ba;

        $approved->update(['notif_sent_at' => now()]);
        $this->get("/api/kaprodi/validasi/{$approved->id}/download-ba")->assertOk();
        $this->assertSame($stableNumber, $approved->fresh()->nomor_ba);

        $this->getJson('/api/kaprodi/laporan')->assertOk();
        $this->get('/api/kaprodi/laporan?format=csv')->assertOk();
    }

    private function runRevisionLoop(Pendaftar $revision): void
    {
        $this->authenticate($this->akademik);
        $revision->refresh()->load('transkripAsal');
        $transkrip = $revision->transkripAsal->firstOrFail();

        $this->putJson("/api/akademik/antrean/{$revision->id}", [
            'nama_lengkap' => $revision->nama_lengkap,
            'nim_asal' => $revision->nim_asal,
            'email' => $revision->email,
            'no_whatsapp' => $revision->no_whatsapp,
            'asal_kampus' => $revision->asal_kampus,
            'asal_prodi' => $revision->asal_prodi,
            'id_prodi' => $revision->id_prodi,
            'transkrip' => [[
                'id' => $transkrip->id,
                'nama_mk_asal' => 'Basis Data',
                'sks_asal' => 4,
                'nilai_huruf_asal' => 'B+',
            ]],
        ])->assertOk();
        $this->postJson("/api/akademik/antrean/{$revision->id}/proses")->assertOk();
        $this->postJson("/api/akademik/antrean/{$revision->id}/confirm")->assertOk();

        $this->authenticate($this->kaprodi);
        $this->postJson("/api/kaprodi/validasi/{$revision->id}/approve")->assertOk();
        $this->assertSame('Approved', $revision->fresh()->status->value);
    }

    private function verifyAdminResultsAndNotifications(
        Pendaftar $approved,
        Pendaftar $revision,
        Pendaftar $rejected
    ): void {
        $this->authenticate($this->admin);
        $this->getJson("/api/admin/pendaftar/{$approved->id}")
            ->assertOk()
            ->assertJsonPath('data.pendaftar.status', 'Approved');
        $this->getJson("/api/admin/pendaftar/{$revision->id}")
            ->assertOk()
            ->assertJsonPath('data.pendaftar.status', 'Approved');
        $this->getJson("/api/admin/pendaftar/{$rejected->id}")
            ->assertOk()
            ->assertJsonPath('data.pendaftar.status', 'Rejected');

        $notificationResponse = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 4);
        $notificationId = $notificationResponse->json('data.0.id');
        $this->postJson("/api/notifications/{$notificationId}/read")->assertOk();
        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 3);
    }

    private function verifySuperadminMonitoring(): void
    {
        $this->authenticate($this->superadmin);
        $this->getJson('/api/superadmin/audit?search=auth')
            ->assertOk();
        $this->getJson('/api/superadmin/audit?search=download')
            ->assertOk();
        $this->getJson('/api/superadmin/laporan')
            ->assertOk()
            ->assertJsonPath('data.global.total_pendaftar', 3);
        $this->get('/api/superadmin/laporan?format=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertDatabaseHas('audit_logs', ['action' => 'matching.queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'matching.processed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.ba_downloaded']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'file.excel_downloaded']);
        $this->assertGreaterThan(0, InternalNotification::count());
        $this->assertSame(0, KamusSinonim::where('sinonim', 'like', '%uat%')->count());
    }

    private function authenticate(User $user): void
    {
        $this->withToken($user->createToken('uat')->plainTextToken);
    }

    private function makeWorkbook(): string
    {
        $workbook = new Spreadsheet;
        $students = $workbook->getActiveSheet();
        $students->setTitle('Mahasiswa');
        $students->fromArray(
            ['nim_asal', 'nama_lengkap', 'asal_kampus', 'asal_prodi', 'prodi_tujuan', 'email', 'no_whatsapp'],
            null,
            'A1'
        );
        $students->fromArray([
            ['UAT001', 'Mahasiswa UAT Approve', 'Universitas Dummy', 'Informatika', $this->prodi->nama_prodi, 'approve@example.com', '628111111101'],
            ['UAT002', 'Mahasiswa UAT Revisi', 'Universitas Dummy', 'Informatika', $this->prodi->nama_prodi, 'revisi@example.com', '628111111102'],
            ['UAT003', 'Mahasiswa UAT Reject', 'Universitas Dummy', 'Informatika', $this->prodi->nama_prodi, 'reject@example.com', '628111111103'],
        ], null, 'A2');

        $transcripts = $workbook->createSheet();
        $transcripts->setTitle('Transkrip');
        $transcripts->fromArray(
            ['nim_asal', 'nama_mk_asal', 'sks_asal', 'nilai_huruf_asal', 'nilai_angka_asal'],
            null,
            'A1'
        );
        $transcripts->fromArray([
            ['UAT001', 'Algoritma dan Pemrograman', 3, 'A', 4],
            ['UAT001', 'Mata Kuliah Lokal Tanpa Padanan', 2, 'B', 3],
            ['UAT002', 'Basis Data', 4, 'B+', 3.5],
            ['UAT003', 'Kecerdasan Buatan', 3, 'B', 3],
        ], null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'konverpro_uat_');
        if ($path === false) {
            $this->fail('Tidak dapat membuat workbook UAT.');
        }
        @unlink($path);
        $path .= '.xlsx';
        (new Xlsx($workbook))->save($path);

        return $path;
    }
}
