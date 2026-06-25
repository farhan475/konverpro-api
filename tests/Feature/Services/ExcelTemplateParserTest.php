<?php

namespace Tests\Feature\Services;

use App\Models\Pendaftar;
use App\Models\User;
use App\Services\ExcelParserService;
use Database\Seeders\WhiteTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelTemplateParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(WhiteTestingSeeder::class);
    }

    public function test_downloaded_template_is_blank_validated_and_contains_examples_separately(): void
    {
        $admin = User::where('email', 'admin@test.com')->firstOrFail();
        $this->withToken($admin->createToken('test')->plainTextToken);

        $response = $this->get('/api/admin/template-excel')->assertOk();
        $path = $this->temporaryPath();
        file_put_contents($path, $response->streamedContent());

        try {
            $workbook = IOFactory::load($path);
            $this->assertSame(['Mahasiswa', 'Transkrip', 'Referensi', 'Contoh'], $workbook->getSheetNames());
            $this->assertNull($workbook->getSheetByName('Mahasiswa')?->getCell('A2')->getValue());
            $this->assertNull($workbook->getSheetByName('Transkrip')?->getCell('A2')->getValue());
            $this->assertSame('Budi Santoso', $workbook->getSheetByName('Contoh')?->getCell('B3')->getValue());
            $this->assertSame(
                '=DaftarProdi',
                $workbook->getSheetByName('Mahasiswa')?->getCell('E2')->getDataValidation()->getFormula1()
            );
            $this->assertTrue($workbook->getDefinedName('DaftarProdi') !== null);
        } finally {
            @unlink($path);
        }
    }

    public function test_parser_imports_a_valid_workbook(): void
    {
        $path = $this->makeWorkbook([
            ['NIM001', 'Mahasiswa Valid', 'Universitas Asal', 'Informatika', 'PJJ Informatika', 'valid@example.com', '628123456789'],
        ], [
            ['NIM001', 'Pemrograman Dasar', 3, 'a', 4],
        ]);

        try {
            $admin = User::where('email', 'admin@test.com')->firstOrFail();
            $results = app(ExcelParserService::class)->parse($path, $admin->id);

            $this->assertCount(1, $results);
            $this->assertDatabaseHas('pendaftar', ['nim_asal' => 'NIM001', 'status' => 'Baru']);
            $this->assertDatabaseHas('transkrip_asal', ['nilai_huruf_asal' => 'A', 'sks_asal' => 3]);
        } finally {
            @unlink($path);
        }
    }

    public function test_parser_rolls_back_the_entire_file_when_any_student_row_is_invalid(): void
    {
        $path = $this->makeWorkbook([
            ['NIM001', 'Mahasiswa Valid', 'Universitas Asal', 'Informatika', 'PJJ Informatika', 'valid@example.com', '628123456789'],
            ['NIM002', 'Mahasiswa Invalid', 'Universitas Asal', 'Informatika', 'PJJ Informatika', 'bukan-email', '0812345'],
        ], [
            ['NIM001', 'Pemrograman Dasar', 3, 'A', 4],
            ['NIM002', 'Basis Data', 3, 'B', 3],
        ]);

        try {
            try {
                app(ExcelParserService::class)->parse($path);
                $this->fail('Workbook dengan baris tidak valid seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->assertSame(0, Pendaftar::count());
            }
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  array<int, array<int, mixed>>  $students
     * @param  array<int, array<int, mixed>>  $transcripts
     */
    private function makeWorkbook(array $students, array $transcripts): string
    {
        $workbook = new Spreadsheet;
        $studentSheet = $workbook->getActiveSheet();
        $studentSheet->setTitle('Mahasiswa');
        $studentSheet->fromArray(
            ['nim_asal', 'nama_lengkap', 'asal_kampus', 'asal_prodi', 'prodi_tujuan', 'email', 'no_whatsapp'],
            null,
            'A1'
        );
        $studentSheet->fromArray($students, null, 'A2');

        $transcriptSheet = $workbook->createSheet();
        $transcriptSheet->setTitle('Transkrip');
        $transcriptSheet->fromArray(
            ['nim_asal', 'nama_mk_asal', 'sks_asal', 'nilai_huruf_asal', 'nilai_angka_asal'],
            null,
            'A1'
        );
        $transcriptSheet->fromArray($transcripts, null, 'A2');

        $path = $this->temporaryPath();
        (new Xlsx($workbook))->save($path);

        return $path;
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'konverpro_');
        if ($path === false) {
            $this->fail('Tidak dapat membuat file sementara.');
        }
        @unlink($path);

        return $path.'.xlsx';
    }
}
