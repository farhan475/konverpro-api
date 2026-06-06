<?php

namespace App\Services;

use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\DynamicNotificationMail;

class NotificationService
{
    public function send(Pendaftar $pendaftar, string $status): void
    {
        $configs = PengaturanGlobal::whereIn('setting_key', [
            'notif_email_aktif',
            'notif_wa_aktif',
            'fonnte_api_key',
            'nama_institusi'
        ])->pluck('setting_value', 'setting_key');

        $template = $this->getTemplate($status, [
            'nama_mahasiswa' => $pendaftar->nama_lengkap,
            'nama_institusi' => isset($configs['nama_institusi']) ? strval($configs['nama_institusi']) : 'Universitas Siber Asia',
            'sks_diakui' => strval($pendaftar->total_sks_diakui),
            'catatan' => strval($pendaftar->catatan_revisi),
        ]);

        if (!$template) return;

        // Email
        if (isset($configs['notif_email_aktif']) && $configs['notif_email_aktif'] === 'true' && $pendaftar->email) {
            try {
                Mail::to($pendaftar->email)->send(new DynamicNotificationMail($template['subject'], $template['email']));
            } catch (\Exception $e) {
                Log::error("Failed to send email to {$pendaftar->email}: " . $e->getMessage());
            }
        }

        // WhatsApp
        if (isset($configs['notif_wa_aktif']) && $configs['notif_wa_aktif'] === 'true' && $pendaftar->no_whatsapp && isset($configs['fonnte_api_key'])) {
            $this->sendFonnte($pendaftar->no_whatsapp, $template['wa'], strval($configs['fonnte_api_key']));
        }
    }

    /**
     * @param string $status
     * @param array<string, string> $data
     * @return array<string, string>|null
     */
    private function getTemplate(string $status, array $data): ?array
    {
        $templates = [
            'Approved' => [
                'subject' => 'Selamat! Konversi SKS Anda Telah Disetujui',
                'email' => "Halo {$data['nama_mahasiswa']}, permohonan konversi SKS Anda ke {$data['nama_institusi']} telah disetujui. Total SKS yang diakui adalah {$data['sks_diakui']} SKS. Silakan cek dashboard untuk detail.",
                'wa' => "Halo {$data['nama_mahasiswa']}, konversi SKS Anda ke {$data['nama_institusi']} telah disetujui dengan total {$data['sks_diakui']} SKS diakui! - KonverPro"
            ],
            'Rejected' => [
                'subject' => 'Update Permohonan Konversi SKS',
                'email' => "Mohon maaf {$data['nama_mahasiswa']}, permohonan konversi SKS Anda ditolak dengan alasan: {$data['catatan']}.",
                'wa' => "Halo {$data['nama_mahasiswa']}, permohonan konversi SKS Anda ke {$data['nama_institusi']} ditolak. Alasan: {$data['catatan']}. - KonverPro"
            ],
            'Revisi' => [
                'subject' => 'Permintaan Revisi Konversi SKS',
                'email' => "Halo {$data['nama_mahasiswa']}, terdapat permintaan revisi untuk data konversi Anda. Catatan: {$data['catatan']}.",
                'wa' => "Halo {$data['nama_mahasiswa']}, berkas konversi Anda perlu direvisi. Catatan: {$data['catatan']}. - KonverPro"
            ]
        ];

        return $templates[$status] ?? null;
    }

    private function sendFonnte(string $phone, string $message, string $apiKey): void
    {
        try {
            Http::withHeaders(['Authorization' => $apiKey])
                ->post('https://api.fonnte.com/send', [
                    'target' => $phone,
                    'message' => $message,
                ]);
        } catch (\Exception $e) {
            Log::error("Fonnte WA Error: " . $e->getMessage());
        }
    }
}
