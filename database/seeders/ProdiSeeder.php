<?php

namespace Database\Seeders;

use App\Models\Prodi;
use Illuminate\Database\Seeder;

class ProdiSeeder extends Seeder
{
    public function run(): void
    {
        $prodis = [
            ['nama_prodi' => 'PJJ Informatika', 'jenjang' => 'S1', 'kode_prodi' => 'IF'],
            ['nama_prodi' => 'PJJ Sistem Informasi', 'jenjang' => 'S1', 'kode_prodi' => 'SI'],
            ['nama_prodi' => 'PJJ Manajemen', 'jenjang' => 'S1', 'kode_prodi' => 'MNJ'],
            ['nama_prodi' => 'PJJ Akuntansi', 'jenjang' => 'S1', 'kode_prodi' => 'AKT'],
            ['nama_prodi' => 'PJJ Komunikasi', 'jenjang' => 'S1', 'kode_prodi' => 'IK'],
            ['nama_prodi' => 'PJJ Teknologi Informasi', 'jenjang' => 'S1', 'kode_prodi' => 'TI'],
        ];

        foreach ($prodis as $prodi) {
            Prodi::updateOrCreate(
                ['nama_prodi' => $prodi['nama_prodi']],
                $prodi
            );
        }
    }
}
