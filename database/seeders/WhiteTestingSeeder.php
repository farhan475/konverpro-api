<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\KurikulumMk;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Seeder;

class WhiteTestingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Users for Each Role
        $users = [
            [
                'nama_lengkap' => 'Admin Inputer',
                'email' => 'admin@test.com',
                'password' => 'password',
                'role' => RoleEnum::ADMIN,
            ],
            [
                'nama_lengkap' => 'Staff Akademik',
                'email' => 'akademik@test.com',
                'password' => 'password',
                'role' => RoleEnum::AKADEMIK,
            ],
            [
                'nama_lengkap' => 'Kaprodi Informatika',
                'email' => 'kaprodi@test.com',
                'password' => 'password',
                'role' => RoleEnum::KAPRODI,
            ],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['email' => $u['email']], $u);
        }

        $kaprodi = User::where('email', 'kaprodi@test.com')->first();

        // 2. Create All UNSIA Prodi
        $prodis = [
            ['nama_prodi' => 'PJJ Informatika', 'jenjang' => 'S1', 'kode_prodi' => 'IF', 'id_kaprodi' => $kaprodi->id],
            ['nama_prodi' => 'PJJ Sistem Informasi', 'jenjang' => 'S1', 'kode_prodi' => 'SI'],
            ['nama_prodi' => 'PJJ Manajemen', 'jenjang' => 'S1', 'kode_prodi' => 'MNJ'],
            ['nama_prodi' => 'PJJ Akuntansi', 'jenjang' => 'S1', 'kode_prodi' => 'AKT'],
            ['nama_prodi' => 'PJJ Komunikasi', 'jenjang' => 'S1', 'kode_prodi' => 'IK'],
            ['nama_prodi' => 'PJJ Teknologi Informasi', 'jenjang' => 'S1', 'kode_prodi' => 'TI'],
        ];

        foreach ($prodis as $p) {
            $prodi = Prodi::updateOrCreate(['nama_prodi' => $p['nama_prodi']], $p);

            // 3. Seed some Curriculum for IF to allow testing
            if ($p['kode_prodi'] === 'IF') {
                $this->seedKurikulumIF($prodi->id);
            }
        }
    }

    private function seedKurikulumIF(string $prodiId): void
    {
        $mks = [
            ['kode_mk' => 'IF101', 'nama_mk' => 'Pemrograman Dasar', 'sks' => 3, 'semester' => 1],
            ['kode_mk' => 'IF102', 'nama_mk' => 'Matematika Diskrit', 'sks' => 3, 'semester' => 1],
            ['kode_mk' => 'IF201', 'nama_mk' => 'Struktur Data', 'sks' => 4, 'semester' => 2],
            ['kode_mk' => 'IF202', 'nama_mk' => 'Basis Data', 'sks' => 3, 'semester' => 2],
            ['kode_mk' => 'IF301', 'nama_mk' => 'Kecerdasan Buatan', 'sks' => 3, 'semester' => 3],
        ];

        foreach ($mks as $mk) {
            KurikulumMk::updateOrCreate(
                ['id_prodi' => $prodiId, 'kode_mk' => $mk['kode_mk']],
                $mk
            );
        }
    }
}
