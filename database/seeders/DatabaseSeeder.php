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
    }
}
