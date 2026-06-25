<?php

namespace Tests\Feature\Services;

use App\Models\BaDocument;
use App\Models\HasilKonversi;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Models\Prodi;
use App\Models\TranskripAsal;
use App\Models\User;
use App\Services\BeritaAcaraWhatsappService;
use Database\Seeders\WhiteTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BeritaAcaraWhatsappServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(WhiteTestingSeeder::class);
    }

    public function test_pdf_ba_is_uploaded_directly_to_fonnte_and_timestamped(): void
    {
        Http::fake([
            'api.fonnte.com/send' => Http::response(['status' => true, 'detail' => 'success']),
        ]);
        PengaturanGlobal::set('fonnte_api_key', 'test-fonnte-key');

        $pendaftar = $this->makeApprovedApplicant();
        app(BeritaAcaraWhatsappService::class)->send($pendaftar);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->hasHeader('Authorization', 'test-fonnte-key')
                && str_contains($request->body(), 'Berita_Acara_BAWA002.pdf')
                && str_contains($request->body(), '628123456789');
        });
        $this->assertNotNull($pendaftar->fresh()->ba_wa_sent_at);
    }

    public function test_failed_fonnte_response_does_not_mark_ba_as_sent(): void
    {
        Http::fake([
            'api.fonnte.com/send' => Http::response(['status' => false, 'reason' => 'device disconnected'], 200),
        ]);
        PengaturanGlobal::set('fonnte_api_key', 'test-fonnte-key');
        $pendaftar = $this->makeApprovedApplicant();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('device disconnected');

        try {
            app(BeritaAcaraWhatsappService::class)->send($pendaftar);
        } finally {
            $this->assertNull($pendaftar->fresh()->ba_wa_sent_at);
        }
    }

    private function makeApprovedApplicant(): Pendaftar
    {
        $prodi = Prodi::where('kode_prodi', 'IF')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        $pendaftar = Pendaftar::create([
            'id_prodi' => $prodi->id,
            'created_by' => $admin->id,
            'nama_lengkap' => 'Mahasiswa BA WA',
            'nim_asal' => 'BAWA002',
            'no_whatsapp' => '628123456789',
            'asal_kampus' => 'Universitas Dummy',
            'asal_prodi' => 'Informatika',
            'status' => 'Approved',
            'total_sks_diakui' => 3,
            'nomor_ba' => 'BA/2026/002/IF',
            'approved_at' => now(),
            'hash_ba_digital' => str_repeat('b', 64),
        ]);
        $transkrip = TranskripAsal::create([
            'id_pendaftar' => $pendaftar->id,
            'nama_mk_asal' => 'Pemrograman Dasar',
            'sks_asal' => 3,
            'nilai_huruf_asal' => 'A',
        ]);
        $course = $prodi->kurikulumMk()->firstOrFail();
        HasilKonversi::create([
            'id_pendaftar' => $pendaftar->id,
            'id_transkrip_asal' => $transkrip->id,
            'id_mk_tujuan' => $course->id,
            'nilai_akhir_huruf' => 'A',
            'sks_diakui' => 3,
            'metode_pemetaan' => 'Fuzzy',
            'match_score' => 100,
            'is_unmatched' => false,
        ]);
        $document = BaDocument::create([
            'id_pendaftar' => $pendaftar->id,
            'version' => 1,
            'document_number' => 'BA/2026/002/IF',
            'document_hash' => str_repeat('b', 64),
            'status' => 'final',
            'approved_by' => $prodi->id_kaprodi,
            'approved_at' => $pendaftar->approved_at,
        ]);
        $pendaftar->update(['current_ba_document_id' => $document->id]);

        return $pendaftar->refresh();
    }
}
