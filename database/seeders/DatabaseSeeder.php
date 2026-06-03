<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(KonverproDefaultsSeeder::class);

        $id_kampus = \Illuminate\Support\Facades\DB::table('kampus')->insertGetId([
            'nama_kampus' => 'Universitas Siber Asia',
            'email_utama' => 'info@unsia.ac.id',
            'status_akun' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\User::create([
            'id_kampus' => $id_kampus,
            'nama_lengkap' => 'Admin UNSIA',
            'email' => 'adminpt@unsia.ac.id',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'admin_pt',
            'status' => 'active',
        ]);

        $kaprodi = \App\Models\User::create([
            'id_kampus' => $id_kampus,
            'nama_lengkap' => 'Kaprodi Informatika',
            'email' => 'kaprodi@unsia.ac.id',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'kaprodi',
            'status' => 'active',
        ]);

        $prodi = \App\Models\Prodi::create([
            'id_kampus' => $id_kampus,
            'id_kaprodi' => $kaprodi->id,
            'nama_prodi' => 'Informatika',
            'jenjang' => 'S1',
        ]);

        $mks = [
            ['kode_mk' => 'INF101', 'nama_mk' => 'Algoritma dan Pemrograman', 'sks' => 4, 'semester' => 1],
            ['kode_mk' => 'INF102', 'nama_mk' => 'Basis Data', 'sks' => 3, 'semester' => 2],
            ['kode_mk' => 'INF103', 'nama_mk' => 'Pancasila', 'sks' => 2, 'semester' => 1],
            ['kode_mk' => 'INF104', 'nama_mk' => 'Bahasa Inggris', 'sks' => 2, 'semester' => 1],
            ['kode_mk' => 'INF105', 'nama_mk' => 'Matematika Diskrit', 'sks' => 3, 'semester' => 1],
            ['kode_mk' => 'INF106', 'nama_mk' => 'Sistem Operasi', 'sks' => 3, 'semester' => 3],
            ['kode_mk' => 'INF107', 'nama_mk' => 'Jaringan Komputer', 'sks' => 3, 'semester' => 3],
        ];

        foreach ($mks as $mk) {
            \App\Models\KurikulumMk::create(array_merge($mk, ['id_prodi' => $prodi->id]));
        }

        $this->call(AiReferensiSeeder::class);
    }
}
