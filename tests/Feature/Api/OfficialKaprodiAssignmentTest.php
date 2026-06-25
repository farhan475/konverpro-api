<?php

namespace Tests\Feature\Api;

use App\Models\BaDocument;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\User;
use App\Services\DocumentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Zxing\QrReader;

class OfficialKaprodiAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{prodi: string, nama: string, email: string}> */
    private array $officialKaprodi = [
        'IF' => [
            'prodi' => 'PJJ Informatika',
            'nama' => 'Syahid Abdullah, S.Si, M.Kom',
            'email' => 'syahidabdullah@lecturer.unsia.ac.id',
        ],
        'SI' => [
            'prodi' => 'PJJ Sistem Informasi',
            'nama' => 'Vika Febri Muliati, S.Kom., M.Kom',
            'email' => 'vikamuliati@lecturer.unsia.ac.id',
        ],
        'MN' => [
            'prodi' => 'PJJ Manajemen',
            'nama' => 'Wahyu Purbo Santoso, S.E., M.M',
            'email' => 'wahyupurbo@lecturer.unsia.ac.id',
        ],
        'AK' => [
            'prodi' => 'PJJ Akuntansi',
            'nama' => 'Nurhayati Siregar, S.E., M.Ak., CSRS.,CSRA.,CSP',
            'email' => 'nurhayatisiregar@lecturer.unsia.ac.id',
        ],
        'IK' => [
            'prodi' => 'PJJ Komunikasi',
            'nama' => 'Rosanah, S.S., M.I.Kom',
            'email' => 'rosanah@lecturer.unsia.ac.id',
        ],
        'TI' => [
            'prodi' => 'PJJ Teknologi Informasi',
            'nama' => 'Ir. Ahmad Chusyairi, S.Kom., M.Kom., CDS., IPM., ASEAN Eng',
            'email' => 'ahmadchusyairi@lecturer.unsia.ac.id',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_all_official_kaprodi_are_assigned_and_scoped_to_their_program(): void
    {
        $admin = User::where('email', 'admin-konversi@unsia.ac.id')->firstOrFail();
        $applicants = [];

        foreach ($this->officialKaprodi as $code => $expected) {
            $kaprodi = User::where('email', $expected['email'])->firstOrFail();
            $prodi = Prodi::where('kode_prodi', $code)->firstOrFail();

            $this->assertSame($expected['nama'], $kaprodi->nama_lengkap);
            $this->assertSame('kaprodi', $kaprodi->role->value);
            $this->assertSame($expected['prodi'], $prodi->nama_prodi);
            $this->assertSame($kaprodi->id, $prodi->id_kaprodi);

            $applicants[$code] = Pendaftar::create([
                'id_prodi' => $prodi->id,
                'created_by' => $admin->id,
                'nama_lengkap' => "Mahasiswa {$code}",
                'nim_asal' => "NIM-{$code}",
                'status' => 'Pending Kaprodi',
            ]);
        }

        foreach ($this->officialKaprodi as $code => $expected) {
            $kaprodi = User::where('email', $expected['email'])->firstOrFail();
            $token = $kaprodi->createToken("test-{$code}")->plainTextToken;

            $this->withToken($token)
                ->getJson('/api/kaprodi/dashboard')
                ->assertOk()
                ->assertJsonPath('data.prodi.0.kode_prodi', $code)
                ->assertJsonPath('data.prodi.0.nama_prodi', $expected['prodi'])
                ->assertJsonPath('data.verification_method', 'qr')
                ->assertJsonPath('data.stats.pending_validation', 1);

            $this->withToken($token)
                ->getJson('/api/kaprodi/validasi')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $applicants[$code]->id);
        }
    }

    public function test_qr_verification_text_uses_the_related_official_kaprodi_identity(): void
    {
        $verification = app(DocumentVerificationService::class);

        foreach ($this->officialKaprodi as $code => $expected) {
            $prodi = Prodi::where('kode_prodi', $code)->firstOrFail();
            $pendaftar = Pendaftar::create([
                'id_prodi' => $prodi->id,
                'nama_lengkap' => "Mahasiswa QR {$code}",
                'nim_asal' => "QR-{$code}",
                'status' => 'Approved',
            ]);
            $prodiName = preg_replace('/^PJJ\s+/', '', $expected['prodi']);
            $expectedText = "Dokumen ini telah diverifikasi, disetujui, dan diresmikan oleh Ketua Program Studi {$prodiName}, {$expected['nama']}";

            $this->assertSame($expectedText, $verification->verificationText($pendaftar));

            $document = BaDocument::create([
                'id_pendaftar' => $pendaftar->id,
                'version' => 1,
                'document_number' => "BA/QR/{$code}",
                'document_hash' => str_repeat('a', 64),
                'status' => 'final',
                'approved_by' => $prodi->id_kaprodi,
                'approved_at' => now(),
            ]);
            $pendaftar->update(['current_ba_document_id' => $document->id]);
            $pendaftar->refresh();

            $dataUri = $verification->qrDataUri($pendaftar);
            $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
            $png = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
            $this->assertIsString($png);
            $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
            $this->assertSame(
                "http://localhost:3000/verify/{$document->id}",
                (new QrReader($png, QrReader::SOURCE_TYPE_BLOB, false))->text()
            );
        }
    }
}
