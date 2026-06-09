<?php

namespace App\Services;

use App\Mail\DynamicNotificationMail;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function send(Pendaftar $pendaftar, string $status): void
    {
        $template = $this->getTemplate($status, $pendaftar);
        if (!$template) return;

        $emailAktif = PengaturanGlobal::get('notif_email_aktif', 'false');
        $waAktif    = PengaturanGlobal::get('notif_wa_aktif', 'false');

        if ($emailAktif === 'true' && $pendaftar->email) {
            $this->sendEmail($pendaftar->email, $template['subject'], $template['email']);
        }

        if ($waAktif === 'true' && $pendaftar->no_whatsapp) {
            // Baca via ::get() agar didekripsi
            $fonnteKey = PengaturanGlobal::get('fonnte_api_key');
            if ($fonnteKey) {
                $this->sendFonnte($pendaftar->no_whatsapp, $template['wa'], $fonnteKey);
            }
        }

        $pendaftar->update(['notif_sent_at' => now()]);
    }

    private function sendEmail(string $to, string $subject, string $body): void
    {
        try {
            Mail::to($to)->send(new DynamicNotificationMail($subject, $body));
        } catch (\Exception $e) {
            Log::warning('NotificationService: Gagal kirim email', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendFonnte(string $phone, string $message, string $apiKey): void
    {
        try {
            Http::withHeaders(['Authorization' => $apiKey])
                ->timeout(10)
                ->post('https://api.fonnte.com/send', [
                    'target'  => $phone,
                    'message' => $message,
                ]);
        } catch (\Exception $e) {
            Log::warning('NotificationService: Gagal kirim WA', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{subject: string, email: string, wa: string}|null
     */
    private function getTemplate(string $status, Pendaftar $pendaftar): ?array
    {
        $institusi = PengaturanGlobal::get('nama_institusi', 'Universitas Siber Asia');
        $nama      = $pendaftar->nama_lengkap;
        $sks       = $pendaftar->total_sks_diakui;
        $catatan   = $pendaftar->catatan_revisi ?? '-';

        return match ($status) {
            'Approved' => [
                'subject' => 'Selamat! Konversi SKS Anda Telah Disetujui',
                'email'   => "Halo {$nama},\n\nPermohonan konversi SKS Anda ke {$institusi} telah disetujui.\n"
                           . "Total SKS diakui: {$sks} SKS\n\nSilakan hubungi bagian akademik untuk langkah selanjutnya.",
                'wa'      => "Halo {$nama}, konversi SKS Anda ke {$institusi} telah *disetujui* dengan total {$sks} SKS. - KonverPro",
            ],
            'Rejected' => [
                'subject' => 'Update Permohonan Konversi SKS',
                'email'   => "Halo {$nama},\n\nMohon maaf, permohonan konversi SKS Anda ditolak.\nAlasan: {$catatan}\n\n"
                           . "Silakan hubungi bagian akademik untuk informasi lebih lanjut.",
                'wa'      => "Halo {$nama}, mohon maaf permohonan konversi SKS Anda ditolak. Alasan: {$catatan}. - KonverPro",
            ],
            'Revisi' => [
                'subject' => 'Permintaan Revisi Konversi SKS',
                'email'   => "Halo {$nama},\n\nTerdapat permintaan revisi untuk berkas konversi Anda.\nCatatan: {$catatan}\n\n"
                           . "Silakan hubungi admin yang menginput data Anda.",
                'wa'      => "Halo {$nama}, berkas konversi Anda perlu direvisi. Catatan: {$catatan}. - KonverPro",
            ],
            default => null,
        };
    }
}