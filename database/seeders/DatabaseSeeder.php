<?php

namespace Database\Seeders;

use App\Models\KamusSinonim;
use App\Models\KurikulumMk;
use App\Models\PengaturanProdi;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(KonverproDefaultsSeeder::class);

        $superadmin = User::where('email', 'admin@unsia.ac.id')->firstOrFail();

        // User demo untuk pengembangan lokal.
        User::updateOrCreate(
            ['email' => 'admin-konversi@unsia.ac.id'],
            [
                'nama_lengkap' => 'Admin Konversi',
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

        $kaprodiData = [
            'IF' => ['nama' => 'Syahid Abdullah, S.Si, M.Kom', 'email' => 'syahidabdullah@lecturer.unsia.ac.id'],
            'SI' => ['nama' => 'Vika Febri Muliati, S.Kom., M.Kom', 'email' => 'vikamuliati@lecturer.unsia.ac.id'],
            'MN' => ['nama' => 'Wahyu Purbo Santoso, S.E., M.M', 'email' => 'wahyupurbo@lecturer.unsia.ac.id'],
            'AK' => ['nama' => 'Nurhayati Siregar, S.E., M.Ak., CSRS.,CSRA.,CSP', 'email' => 'nurhayatisiregar@lecturer.unsia.ac.id'],
            'IK' => ['nama' => 'Rosanah, S.S., M.I.Kom', 'email' => 'rosanah@lecturer.unsia.ac.id'],
            'TI' => ['nama' => 'Ir. Ahmad Chusyairi, S.Kom., M.Kom., CDS., IPM., ASEAN Eng', 'email' => 'ahmadchusyairi@lecturer.unsia.ac.id'],
        ];
        $kaprodiByCode = [];

        foreach ($kaprodiData as $code => $data) {
            $kaprodiByCode[$code] = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'nama_lengkap' => $data['nama'],
                    'password' => Hash::make('password'),
                    'role' => 'kaprodi',
                    'status' => 'active',
                ]
            );
        }

        User::where('email', 'if@unsia.ac.id')
            ->where('role', 'kaprodi')
            ->update(['status' => 'inactive']);

        // 4. Prodi & Pengaturan Prodi
        $prodiData = [
            ['nama' => 'PJJ Informatika', 'kode' => 'IF'],
            ['nama' => 'PJJ Sistem Informasi', 'kode' => 'SI'],
            ['nama' => 'PJJ Manajemen', 'kode' => 'MN'],
            ['nama' => 'PJJ Akuntansi', 'kode' => 'AK'],
            ['nama' => 'PJJ Komunikasi', 'kode' => 'IK'],
            ['nama' => 'PJJ Teknologi Informasi', 'kode' => 'TI'],
        ];

        foreach ($prodiData as $p) {
            $prodi = Prodi::updateOrCreate(
                ['nama_prodi' => $p['nama']],
                [
                    'kode_prodi' => $p['kode'],
                    'id_kaprodi' => $kaprodiByCode[$p['kode']]->id,
                    'jenjang' => 'S1',
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
                    ['kode' => 'IF101', 'nama' => 'Algoritma dan Pemrograman', 'sks' => 3, 'sem' => 1],
                    ['kode' => 'IF102', 'nama' => 'Matematika Diskrit', 'sks' => 3, 'sem' => 1],
                    ['kode' => 'IF201', 'nama' => 'Sistem Operasi', 'sks' => 3, 'sem' => 2],
                    ['kode' => 'IF202', 'nama' => 'Basis Data', 'sks' => 4, 'sem' => 2],
                    ['kode' => 'IF301', 'nama' => 'Pemrograman Berorientasi Objek', 'sks' => 3, 'sem' => 3],
                    ['kode' => 'IF302', 'nama' => 'Jaringan Komputer', 'sks' => 3, 'sem' => 3],
                    ['kode' => 'IF401', 'nama' => 'Kecerdasan Buatan', 'sks' => 3, 'sem' => 4],
                    ['kode' => 'IF402', 'nama' => 'Rekayasa Perangkat Lunak', 'sks' => 3, 'sem' => 4],
                ];

                foreach ($mkList as $mk) {
                    KurikulumMk::updateOrCreate(
                        ['id_prodi' => $prodi->id, 'nama_mk' => $mk['nama']],
                        [
                            'kode_mk' => $mk['kode'],
                            'sks' => $mk['sks'],
                            'semester' => $mk['sem'],
                            'tipe_mk' => 'Wajib',
                        ]
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
