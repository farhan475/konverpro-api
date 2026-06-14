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
     * @param string $filePath
     * @param string|null $createdBy
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
            'E' => 'prodi_tujuan_unsia',
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

        DB::transaction(function () use ($dataMahasiswa, $transkripByNim, $createdBy, &$results, &$errors) {
            for ($i = 2; $i <= count($dataMahasiswa); $i++) {
                $row = $dataMahasiswa[$i] ?? [];
                $nimAsal = trim((string) ($row['A'] ?? ''));

                if ($nimAsal === '') {
                    continue;
                }

                $namaLengkap = trim((string) ($row['B'] ?? ''));
                $asalKampus = trim((string) ($row['C'] ?? ''));
                $asalProdi = trim((string) ($row['D'] ?? ''));
                $prodiTujuanName = trim((string) ($row['E'] ?? ''));

                if ($namaLengkap === '' || $asalKampus === '' || $asalProdi === '' || $prodiTujuanName === '') {
                    $errors[] = "Sheet Mahasiswa baris {$i}: data wajib belum lengkap.";
                    continue;
                }

                $prodiTujuan = Prodi::where('nama_prodi', $prodiTujuanName)->first();
                if (!$prodiTujuan) {
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
                    'email' => trim((string) ($row['F'] ?? '')) ?: null,
                    'no_whatsapp' => trim((string) ($row['G'] ?? '')) ?: null,
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

            if (count($errors) > 0 && count($results) === 0) {
                throw new InvalidArgumentException(implode(' ', array_slice($errors, 0, 5)));
            }
        });

        return $results;
    }

    /**
     * @param array<string, mixed> $headers
     * @param array<string, string> $expected
     */
    private function validateHeaders(array $headers, array $expected, string $sheetName): void
    {
        foreach ($expected as $column => $label) {
            $actual = strtolower(trim((string) ($headers[$column] ?? '')));
            if ($actual !== $label) {
                throw new InvalidArgumentException(
                    "Header sheet {$sheetName} kolom {$column} harus '{$label}', saat ini '{$actual}'."
                );
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $dataTranskrip
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

            $result[$nimAsal][] = [
                'nama_mk_asal' => $namaMk,
                'sks_asal' => $sksAsal,
                'nilai_huruf_asal' => $nilaiHuruf,
                'nilai_angka_asal' => $nilaiAngkaRaw === null || $nilaiAngkaRaw === '' ? null : (float) $nilaiAngkaRaw,
            ];
        }

        return $result;
    }
}
