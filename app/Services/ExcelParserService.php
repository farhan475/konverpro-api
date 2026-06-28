<?php

namespace App\Services;

use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\TranskripAsal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelParserService
{
    /**
     * @return array<int, Pendaftar>
     */
    public function parse(string $filePath, ?string $createdBy = null): array
    {
        $spreadsheet = IOFactory::load($filePath);

        if ($spreadsheet->getSheetCount() < 2) {
            throw new InvalidArgumentException('Template Excel harus memiliki 2 sheet: Mahasiswa dan Transkrip.');
        }

        $sheetMahasiswa = $spreadsheet->getSheet(0);
        $dataMahasiswa = $sheetMahasiswa->toArray(null, true, true, true);

        $sheetTranskrip = $spreadsheet->getSheet(1);
        $dataTranskrip = $sheetTranskrip->toArray(null, true, true, true);

        $this->validateHeaders($dataMahasiswa[1] ?? [], [
            'A' => 'nim_asal',
            'B' => 'nama_lengkap',
            'C' => 'asal_kampus',
            'D' => 'asal_prodi',
            'E' => ['prodi_tujuan', 'prodi_tujuan_unsia'],
        ], 'Mahasiswa');

        $this->validateHeaders($dataTranskrip[1] ?? [], [
            'A' => 'nim_asal',
            'B' => 'nama_mk_asal',
            'C' => 'sks_asal',
            'D' => 'nilai_huruf_asal',
        ], 'Transkrip');

        $transkripByNim = $this->groupTranskripByNim($dataTranskrip);
        $results = [];
        $errors = [];
        $seenNims = [];

        DB::transaction(function () use ($dataMahasiswa, $transkripByNim, $createdBy, &$results, &$errors, &$seenNims) {
            for ($i = 2; $i <= count($dataMahasiswa); $i++) {
                $row = $dataMahasiswa[$i] ?? [];
                $nimAsal = trim((string) ($row['A'] ?? ''));

                if ($nimAsal === '') {
                    continue;
                }

                if (isset($seenNims[$nimAsal])) {
                    $errors[] = "Sheet Mahasiswa baris {$i}: NIM '{$nimAsal}' terduplikasi.";

                    continue;
                }
                $seenNims[$nimAsal] = true;

                $namaLengkap = trim((string) ($row['B'] ?? ''));
                $asalKampus = trim((string) ($row['C'] ?? ''));
                $asalProdi = trim((string) ($row['D'] ?? ''));
                $prodiTujuanName = trim((string) ($row['E'] ?? ''));
                $email = trim((string) ($row['F'] ?? ''));
                $noWhatsapp = trim((string) ($row['G'] ?? ''));

                if ($namaLengkap === '' || $asalKampus === '' || $asalProdi === '' || $prodiTujuanName === '') {
                    $errors[] = "Sheet Mahasiswa baris {$i}: data wajib belum lengkap.";

                    continue;
                }

                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = "Sheet Mahasiswa baris {$i}: format email tidak valid.";

                    continue;
                }

                if ($noWhatsapp !== '' && preg_match('/^628[0-9]{7,15}$/', $noWhatsapp) !== 1) {
                    $errors[] = "Sheet Mahasiswa baris {$i}: nomor WhatsApp harus menggunakan format 628xxx.";

                    continue;
                }

                $prodiTujuan = Prodi::where('nama_prodi', $prodiTujuanName)->first();
                if (! $prodiTujuan) {
                    $errors[] = "Sheet Mahasiswa baris {$i}: prodi tujuan '{$prodiTujuanName}' tidak ditemukan.";

                    continue;
                }

                $rowsTranskrip = $transkripByNim[$nimAsal] ?? [];
                if (count($rowsTranskrip) === 0) {
                    $errors[] = "Sheet Mahasiswa baris {$i}: transkrip untuk NIM '{$nimAsal}' tidak ditemukan.";

                    continue;
                }

                /** @var Pendaftar $pendaftar */
                $pendaftar = Pendaftar::create([
                    'id_prodi' => $prodiTujuan->id,
                    'created_by' => $createdBy,
                    'nama_lengkap' => $namaLengkap,
                    'nim_asal' => $nimAsal,
                    'asal_kampus' => $asalKampus,
                    'asal_prodi' => $asalProdi,
                    'email' => $email ?: null,
                    'no_whatsapp' => $noWhatsapp ?: null,
                    'status' => 'Baru',
                ]);

                foreach ($rowsTranskrip as $transkripRow) {
                    TranskripAsal::create([
                        'id_pendaftar' => $pendaftar->id,
                        'nama_mk_asal' => $transkripRow['nama_mk_asal'],
                        'sks_asal' => $transkripRow['sks_asal'],
                        'nilai_huruf_asal' => $transkripRow['nilai_huruf_asal'],
                        'nilai_angka_asal' => $transkripRow['nilai_angka_asal'],
                    ]);
                }

                $results[] = $pendaftar;
            }

            if (count($errors) > 0) {
                throw new InvalidArgumentException(implode(' ', array_slice($errors, 0, 5)));
            }
        });

        return $results;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, string|array<int, string>>  $expected
     */
    private function validateHeaders(array $headers, array $expected, string $sheetName): void
    {
        foreach ($expected as $column => $labels) {
            $actual = strtolower(trim((string) ($headers[$column] ?? '')));
            $accepted = is_array($labels) ? $labels : [$labels];
            if (! in_array($actual, $accepted, true)) {
                throw new InvalidArgumentException(
                    "Header sheet {$sheetName} kolom {$column} harus salah satu dari '".implode("', '", $accepted)."', saat ini '{$actual}'."
                );
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $dataTranskrip
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupTranskripByNim(array $dataTranskrip): array
    {
        $result = [];

        foreach ($dataTranskrip as $index => $row) {
            if ((int) $index === 1) {
                continue;
            }

            $nimAsal = trim((string) ($row['A'] ?? ''));
            $namaMk = trim((string) ($row['B'] ?? ''));
            $sksAsal = (int) ($row['C'] ?? 0);
            $nilaiHuruf = trim((string) ($row['D'] ?? ''));
            $nilaiAngkaRaw = $row['E'] ?? null;

            if ($nimAsal === '' && $namaMk === '') {
                continue;
            }

            if ($nimAsal === '' || $namaMk === '' || $sksAsal <= 0 || $nilaiHuruf === '') {
                throw new InvalidArgumentException("Sheet Transkrip baris {$index}: data wajib belum lengkap atau SKS tidak valid.");
            }

            if (! in_array(strtoupper($nilaiHuruf), ['A', 'B+', 'B', 'C+', 'C', 'D', 'E'], true)) {
                throw new InvalidArgumentException("Sheet Transkrip baris {$index}: nilai huruf tidak valid.");
            }

            if ($nilaiAngkaRaw !== null && $nilaiAngkaRaw !== '') {
                if (! is_numeric($nilaiAngkaRaw) || (float) $nilaiAngkaRaw < 0 || (float) $nilaiAngkaRaw > 100) {
                    throw new InvalidArgumentException("Sheet Transkrip baris {$index}: nilai angka harus berada pada rentang 0 sampai 100.");
                }
            }

            $result[$nimAsal][] = [
                'nama_mk_asal' => $namaMk,
                'sks_asal' => $sksAsal,
                'nilai_huruf_asal' => strtoupper($nilaiHuruf),
                'nilai_angka_asal' => $nilaiAngkaRaw === null || $nilaiAngkaRaw === '' ? null : (float) $nilaiAngkaRaw,
            ];
        }

        return $result;
    }
}
