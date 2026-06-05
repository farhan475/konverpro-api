<?php

namespace App\Services\Kurikulum;

use App\Models\KurikulumMk;
use Illuminate\Support\Facades\DB;

class ImportKurikulumService
{
    /**
     * @param int $idProdi
     * @param array<int, array<string, mixed>> $data
     * @return int
     */
    public function import(int $idProdi, array $data): int
    {
        return DB::transaction(function () use ($idProdi, $data) {
            $successCount = 0;
            foreach ($data as $mk) {
                $kode = $mk['kode_mk'] ?? '';
                $nama = $mk['nama_mk'] ?? '';
                $sks = $mk['sks'] ?? 0;
                $sem = $mk['semester'] ?? 0;
                $tipe = $mk['tipe_mk'] ?? 'Wajib';

                KurikulumMk::create([
                    'id_prodi' => $idProdi,
                    'kode_mk' => is_scalar($kode) ? (string) $kode : '',
                    'nama_mk' => is_scalar($nama) ? (string) $nama : '',
                    'sks' => is_numeric($sks) ? (int) $sks : 0,
                    'semester' => is_numeric($sem) ? (int) $sem : 0,
                    'tipe_mk' => is_scalar($tipe) ? (string) $tipe : 'Wajib',
                ]);
                $successCount++;
            }
            return $successCount;
        });
    }
}
