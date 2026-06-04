<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Kampus;
use App\Models\Prodi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class WhiteTestingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a Test Campus (Strictly Internal)
        $kampus = Kampus::updateOrCreate(
            ['email_utama' => 'admin@unsia.ac.id'],
            [
                'nama_kampus' => 'Universitas Siber Indonesia',
                'no_telp' => '021-998877',
                'alamat_resmi' => 'Jl. Digital No. 1, Jakarta',
                'rektor_pimpinan' => 'Dr. White Tester, M.Kom',
                'website' => 'https://unsia.ac.id',
                'paket_layanan' => 'Enterprise',
                'status_akun' => 'active',
                'is_official_partner' => true
            ]
        );

        // 2. Create Users for Each Role
        $users = [
            [
                'nama_lengkap' => 'Super Admin KonverPro',
                'email' => 'superadmin@konverpro.com',
                'password' => 'password123',
                'role' => 'superadmin',
                'id_kampus' => null,
            ],
            [
                'nama_lengkap' => 'Admin Institusi',
                'email' => 'admin@unsia.ac.id',
                'password' => 'password123',
                'role' => 'admin_pt',
                'id_kampus' => $kampus->id,
            ],
            [
                'nama_lengkap' => 'Staf Akademik',
                'email' => 'akademik@unsia.ac.id',
                'password' => 'password123',
                'role' => 'akademik',
                'id_kampus' => $kampus->id,
            ],
            [
                'nama_lengkap' => 'Kaprodi Informatika',
                'email' => 'kaprodi@unsia.ac.id',
                'password' => 'password123',
                'role' => 'kaprodi',
                'id_kampus' => $kampus->id,
            ]
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'nama_lengkap' => $u['nama_lengkap'],
                    'id_kampus' => $u['id_kampus'],
                    'password_hash' => Hash::make($u['password']),
                    'role' => $u['role'],
                    'status' => 'active'
                ]
            );
        }

        // 3. Setup Prodi for Kaprodi
        $kaprodi = User::where('email', 'kaprodi@unsia.ac.id')->first();
        $prodi = Prodi::updateOrCreate(
            ['id_kampus' => $kampus->id, 'nama_prodi' => 'Informatika'],
            [
                'jenjang' => 'S1',
                'kode_prodi' => 'INF-01',
                'id_kaprodi' => $kaprodi->id,
                'biaya_pendaftaran' => 0,
                'biaya_kuliah' => 0
            ]
        );

        echo "Seeders created successfully!\n";
        echo "----------------------------------\n";
        echo "Login Credentials (Password: password123)\n";
        echo "Superadmin: superadmin@konverpro.com\n";
        echo "Admin PT  : admin@unsia.ac.id\n";
        echo "Akademik  : akademik@unsia.ac.id\n";
        echo "Kaprodi   : kaprodi@unsia.ac.id\n";
        echo "----------------------------------\n";
    }
}