<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Prodi;
use App\Models\KurikulumMk;
use App\Models\KamusSinonim;
use App\Models\PengaturanGlobal;
use App\Models\PengaturanProdi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pengaturan Global
        $settings = [
            'nama_institusi' => 'Universitas Siber Asia',
            'fuzzy_threshold_auto' => '80',
            'fuzzy_threshold_sumopod' => '50',
            'sumopod_model' => 'gpt-4o-mini',
            'sumopod_base_url' => 'https://api.sumopod.com/v1',
            'notif_email_aktif' => 'true',
            'notif_wa_aktif' => 'true',
        ];

        foreach ($settings as $key => $value) {
            PengaturanGlobal::updateOrCreate(['setting_key' => $key], ['setting_value' => $value]);
        }

        // 2. Users (Superadmin)
        $superadmin = User::updateOrCreate(
            ['email' => 'admin@unsia.ac.id'],
            [
                'nama_lengkap' => 'Superadmin KonverPro',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'status' => 'active',
            ]
        );

        // 3. User Lain (Dummy)
        $admin = User::updateOrCreate(
            ['email' => 'staff@unsia.ac.id'],
            [
                'nama_lengkap' => 'Staf Admisi',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $akademik = User::updateOrCreate(
            ['email' => 'akademik@unsia.ac.id'],
            [
                'nama_lengkap' => 'Biro Akademik',
                'password' => Hash::make('password'),
                'role' => 'akademik',
                'status' => 'active',
            ]
        );

        $kaprodi = User::updateOrCreate(
            ['email' => 'if@unsia.ac.id'],
            [
                'nama_lengkap' => 'Kaprodi Informatika',
                'password' => Hash::make('password'),
                'role' => 'kaprodi',
                'status' => 'active',
            ]
        );

        // 4. Prodi & Pengaturan Prodi
        $prodiData = [
            ['nama' => 'PJJ Informatika', 'kode' => 'IF', 'kaprodi' => $kaprodi->id],
            ['nama' => 'PJJ Sistem Informasi', 'kode' => 'SI', 'kaprodi' => null],
            ['nama' => 'PJJ Manajemen', 'kode' => 'MN', 'kaprodi' => null],
            ['nama' => 'PJJ Akuntansi', 'kode' => 'AK', 'kaprodi' => null],
            ['nama' => 'PJJ Komunikasi', 'kode' => 'IK', 'kaprodi' => null],
            ['nama' => 'PJJ Teknologi Informasi', 'kode' => 'TI', 'kaprodi' => null],
        ];

        foreach ($prodiData as $p) {
            $prodi = Prodi::updateOrCreate(
                ['nama_prodi' => $p['nama']],
                [
                    'kode_prodi' => $p['kode'],
                    'id_kaprodi' => $p['kaprodi'],
                    'jenjang' => 'S1'
                ]
            );

            PengaturanProdi::updateOrCreate(
                ['id_prodi' => $prodi->id],
                [
                    'min_nilai_huruf' => 'C',
                    'max_konversi_sks_persen' => 70,
                    'format_no_ba' => "BA/{YEAR}/{NO}/{$p['kode']}",
                ]
            );

            // 5. Kurikulum Dasar (Informatika)
            if ($p['kode'] === 'IF') {
                $mkList = [
                    ['nama' => 'Algoritma dan Pemrograman', 'sks' => 3, 'sem' => 1],
                    ['nama' => 'Matematika Diskrit', 'sks' => 3, 'sem' => 1],
                    ['nama' => 'Sistem Operasi', 'sks' => 3, 'sem' => 2],
                    ['nama' => 'Basis Data', 'sks' => 4, 'sem' => 2],
                    ['nama' => 'Pemrograman Berorientasi Objek', 'sks' => 3, 'sem' => 3],
                    ['nama' => 'Jaringan Komputer', 'sks' => 3, 'sem' => 3],
                    ['nama' => 'Kecerdasan Buatan', 'sks' => 3, 'sem' => 4],
                    ['nama' => 'Rekayasa Perangkat Lunak', 'sks' => 3, 'sem' => 4],
                ];

                foreach ($mkList as $mk) {
                    KurikulumMk::updateOrCreate(
                        ['id_prodi' => $prodi->id, 'nama_mk' => $mk['nama']],
                        ['sks' => $mk['sks'], 'semester' => $mk['sem'], 'tipe_mk' => 'Wajib']
                    );
                }
            }
        }

        // 6. Kamus Sinonim Awal
        $sinonim = [
            ['u' => 'Algoritma dan Pemrograman', 's' => 'Dasar Pemrograman'],
            ['u' => 'Basis Data', 's' => 'Sistem Management Basis Data'],
            ['u' => 'Basis Data', 's' => 'Database System'],
            ['u' => 'Jaringan Komputer', 's' => 'Komunikasi Data'],
            ['u' => 'Kecerdasan Buatan', 's' => 'Artificial Intelligence'],
        ];

        foreach ($sinonim as $s) {
            KamusSinonim::updateOrCreate(
                ['kata_utama' => $s['u'], 'sinonim' => $s['s']],
                ['is_active' => true, 'created_by' => $superadmin->id]
            );
        }
    }
}
