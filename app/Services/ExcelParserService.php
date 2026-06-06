<?php

namespace App\Services;

use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\TranskripAsal;
use Illuminate\Support\Facades\DB;
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
        
        $sheetMahasiswa = $spreadsheet->getSheet(0);
        $dataMahasiswa = $sheetMahasiswa->toArray(null, true, true, true);
        
        $sheetTranskrip = $spreadsheet->getSheet(1);
        $dataTranskrip = $sheetTranskrip->toArray(null, true, true, true);

        $results = [];

        DB::transaction(function () use ($dataMahasiswa, $dataTranskrip, $createdBy, &$results) {
            // Skip header (row 1)
            for ($i = 2; $i <= count($dataMahasiswa); $i++) {
                $row = $dataMahasiswa[$i];
                $nimAsal = isset($row['A']) ? strval($row['A']) : '';
                if (empty($nimAsal)) continue;

                $prodiTujuanName = isset($row['E']) ? strval($row['E']) : '';
                $prodiTujuan = Prodi::where('nama_prodi', $prodiTujuanName)->first();
                if (!$prodiTujuan) continue;

                /** @var Pendaftar $pendaftar */
                $pendaftar = Pendaftar::create([
                    'id_prodi' => $prodiTujuan->id,
                    'created_by' => $createdBy,
                    'nama_lengkap' => isset($row['B']) ? strval($row['B']) : '',
                    'nim_asal' => $nimAsal,
                    'asal_kampus' => isset($row['C']) ? strval($row['C']) : '',
                    'asal_prodi' => isset($row['D']) ? strval($row['D']) : '',
                    'email' => isset($row['F']) ? strval($row['F']) : '',
                    'no_whatsapp' => isset($row['G']) ? strval($row['G']) : '',
                    'status' => 'Baru',
                ]);

                // Match transkrip by nim_asal (Column A in sheet 2)
                foreach ($dataTranskrip as $index => $tRow) {
                    if ($index === 1) continue; // Skip header
                    $tNimAsal = isset($tRow['A']) ? strval($tRow['A']) : '';
                    if ($tNimAsal === $nimAsal) {
                        TranskripAsal::create([
                            'id_pendaftar' => $pendaftar->id,
                            'nama_mk_asal' => isset($tRow['B']) ? strval($tRow['B']) : '',
                            'sks_asal' => isset($tRow['C']) ? intval($tRow['C']) : 0,
                            'nilai_huruf_asal' => isset($tRow['D']) ? strval($tRow['D']) : '',
                            'nilai_angka_asal' => isset($tRow['E']) ? floatval($tRow['E']) : null,
                        ]);
                    }
                }

                $results[] = $pendaftar;
            }
        });

        return $results;
    }
}
