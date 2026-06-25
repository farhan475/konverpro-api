<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KonverproDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ($this->globalSettings() as $key => $value) {
            DB::table('pengaturan_global')->insertOrIgnore([
                'setting_key' => $key,
                'setting_value' => $value,
                'updated_at' => $now,
            ]);
        }

        User::firstOrCreate(
            ['email' => 'admin@unsia.ac.id'],
            [
                'nama_lengkap' => 'Super Admin Konverpro',
                'password' => 'password',
                'role' => RoleEnum::SUPERADMIN,
                'status' => 'active',
            ],
        );
    }

    /** @return array<string, string> */
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
            'sumopod_base_url' => 'https://api.sumopod.com/v1',
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
