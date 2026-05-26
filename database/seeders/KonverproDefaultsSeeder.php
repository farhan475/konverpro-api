<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KonverproDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ($this->globalSettings() as $key => $value) {
            DB::table('pengaturan_global')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_at' => $now],
            );
        }

        foreach ($this->notificationTemplates() as $template) {
            DB::table('notifikasi_templates')->updateOrInsert(
                ['kode_event' => $template['kode_event']],
                array_merge($template, ['updated_at' => $now]),
            );
        }
    }

    private function globalSettings(): array
    {
        return [
            'tarif_internal' => '150000',
            'tarif_lead' => '350000',
            'surcharge_partner' => '25',
            'pajak_persen' => '11',
            'min_topup' => '500000',
            'maintenance_mode' => '0',
        ];
    }

    private function notificationTemplates(): array
    {
        return [
            [
                'kode_event' => 'konversi_approved',
                'nama_event' => 'Konversi Disetujui (Mahasiswa)',
                'subjek_email' => 'Selamat! Konversi SKS Anda Telah Disetujui',
                'konten_email' => 'Halo {nama_mahasiswa}, permohonan konversi SKS Anda ke {nama_kampus} untuk program studi {nama_prodi} telah disetujui. Total SKS yang diakui adalah {sks_diakui} SKS. Silakan login ke dashboard untuk melihat rincian pemetaan mata kuliah dan langkah pendaftaran selanjutnya.',
                'konten_wa' => 'Halo {nama_mahasiswa}, konversi SKS Anda ke {nama_kampus} telah disetujui dengan total {sks_diakui} SKS diakui! Silakan cek email Anda untuk detail lebih lanjut. - KonverPro',
                'is_active' => true,
            ],
            [
                'kode_event' => 'topup_success',
                'nama_event' => 'Top Up Saldo Berhasil (Mitra PT)',
                'subjek_email' => 'Top Up Saldo KonverPro Berhasil',
                'konten_email' => 'Yth. Admin {nama_kampus}, permohonan top up saldo Anda sebesar {nominal} telah berhasil dikonfirmasi oleh Super Admin. Saldo aktif Anda saat ini adalah {saldo_aktif}. Terima kasih telah menggunakan KonverPro.',
                'konten_wa' => 'Yth. Admin {nama_kampus}, top up saldo sebesar {nominal} berhasil. Saldo aktif Anda sekarang {saldo_aktif}. - KonverPro',
                'is_active' => true,
            ],
            [
                'kode_event' => 'new_lead',
                'nama_event' => 'Lead Mahasiswa Baru (Mitra PT)',
                'subjek_email' => 'Ada Pendaftar Baru dari KonverPro!',
                'konten_email' => 'Yth. Admin {nama_kampus}, terdapat satu calon mahasiswa baru atas nama {nama_mahasiswa} yang tertarik mendaftar ke prodi {nama_prodi} melalui sistem KonverPro. Silakan login ke dashboard untuk melakukan follow up.',
                'konten_wa' => 'KonverPro Info: Ada lead pendaftar baru a.n {nama_mahasiswa} untuk prodi {nama_prodi}. Silakan cek dashboard admin Anda.',
                'is_active' => true,
            ],
        ];
    }
}
