<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KonverproDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 1. Seed Global Settings
        foreach ($this->globalSettings() as $key => $value) {
            DB::table('pengaturan_global')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_at' => $now],
            );
        }

        // 2. Seed Default Superadmin
        if (!User::where('role', RoleEnum::SUPERADMIN)->exists()) {
            User::create([
                'nama_lengkap' => 'Super Admin Konverpro',
                'email' => 'admin@unsia.ac.id',
                'password' => 'password', // Will be hashed by model cast if implemented, or manually hash here
                'role' => RoleEnum::SUPERADMIN,
                'status' => 'active',
            ]);
        }
    }

    private function globalSettings(): array
    {
        return [
            'nama_institusi' => 'Universitas Siber Asia',
            'fuzzy_threshold_auto' => '80',
            'fuzzy_threshold_sumopod' => '50',
            'min_nilai_huruf_konversi' => 'C',
            'max_konversi_sks_persen' => '70',
            'format_no_ba' => 'BA/{YEAR}/{NO}/{PRODI}',
            'sumopod_api_key' => '', // Empty by default
            'sumopod_model' => 'gpt-4o-mini',
            'sumopod_base_url' => 'https://api.openai.com/v1',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_name' => 'KonverPro UNSIA',
            'fonnte_api_key' => '',
            'notif_email_aktif' => 'false',
            'notif_wa_aktif' => 'false',
        ];
    }
}
