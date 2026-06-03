<?php

namespace App\Services\Kurikulum;

use App\Models\KurikulumMk;
use App\Models\Prodi;
use Illuminate\Support\Facades\DB;

class ImportKurikulumService
{
    public function import(int $idProdi, array $data): int
    {
        return DB::transaction(function () use ($idProdi, $data) {
            $successCount = 0;
            foreach ($data as $mk) {
                KurikulumMk::create([
                    'id_prodi' => $idProdi,
                    'kode_mk' => $mk['kode_mk'],
                    'nama_mk' => $mk['nama_mk'],
                    'sks' => $mk['sks'],
                    'semester' => $mk['semester'],
                    'tipe_mk' => $mk['tipe_mk'],
                ]);
                $successCount++;
            }
            return $successCount;
        });
    }
}
