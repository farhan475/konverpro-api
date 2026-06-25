<?php

namespace App\Services;

use App\Mail\DynamicNotificationMail;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifikasiService
{
    public function send(Pendaftar $pendaftar, string $status): bool
    {
        $template = $this->getTemplate($status, $pendaftar);
        if (! $template) {
            return false;
        }

        $emailAktif = PengaturanGlobal::get('notif_email_aktif', 'false');
        $waAktif = PengaturanGlobal::get('notif_wa_aktif', 'false');
        $sent = false;

        if ($emailAktif === 'true') {
            if ($status === 'Revisi') {
                $pendaftar->loadMissing('creator');
                if ($pendaftar->creator && $pendaftar->creator->email) {
                    if ($this->sendEmail($pendaftar->creator->email, $template['subject'], $template['body'])) {
                        $sent = true;
                    }
                }
            } elseif ($pendaftar->email) {
                if ($this->sendEmail($pendaftar->email, $template['subject'], $template['body'])) {
                    $sent = true;
                }
            }
        }

        if ($waAktif === 'true' && $pendaftar->no_whatsapp && $status !== 'Revisi') {
            $fonnteKey = PengaturanGlobal::get('fonnte_api_key');
            if ($fonnteKey) {
                if ($this->sendFonnte($pendaftar->no_whatsapp, $template['wa'], $fonnteKey)) {
                    $sent = true;
                }
            }
        }

        if ($sent) {
            $pendaftar->update(['notif_sent_at' => now()]);
        }

        return $sent;
    }

    private function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            $this->configureMailerFromSettings();
            Mail::to($to)->send(new DynamicNotificationMail($subject, $body));

            return true;
        } catch (\Exception $e) {
            Log::warning('NotificationService: Gagal kirim email', ['to' => $to, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function configureMailerFromSettings(): void
    {
        $host = PengaturanGlobal::get('smtp_host');
        if ($host === '') {
            return;
        }

        $port = (int) PengaturanGlobal::get('smtp_port', '587');
        $username = PengaturanGlobal::get('smtp_username');
        $password = PengaturanGlobal::get('smtp_password');
        $fromName = PengaturanGlobal::get('smtp_from_name', 'KonverPro UNSIA');
        $configuredFrom = config('mail.from.address');
        $fromAddress = $username !== '' ? $username : (is_string($configuredFrom) ? $configuredFrom : 'noreply@unsia.ac.id');

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', $username !== '' ? $username : null);
        Config::set('mail.mailers.smtp.password', $password !== '' ? $password : null);
        Config::set('mail.mailers.smtp.encryption', $port === 465 ? 'ssl' : 'tls');
        Config::set('mail.from.address', $fromAddress);
        Config::set('mail.from.name', $fromName);
    }

    private function sendFonnte(string $phone, string $message, string $apiKey): bool
    {
        try {
            Http::withHeaders(['Authorization' => $apiKey])
                ->timeout(10)
                ->post('https://api.fonnte.com/send', [
                    'target' => $phone,
                    'message' => $message,
                ])
                ->throw();

            return true;
        } catch (\Exception $e) {
            Log::warning('NotificationService: Gagal kirim WA', ['phone' => $phone, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array{subject: string, body: string, wa: string}|null
     */
    private function getTemplate(string $status, Pendaftar $pendaftar): ?array
    {
        $institusi = PengaturanGlobal::get('nama_institusi', 'Universitas Siber Asia');
        $nama = $pendaftar->nama_lengkap;
        $sks = $pendaftar->total_sks_diakui;
        $catatan = $pendaftar->catatan_revisi ?? '-';
        $prodi = $pendaftar->relationLoaded('prodi') ? $pendaftar->prodi?->nama_prodi : '-';

        return match ($status) {
            'Approved' => [
                'subject' => 'Selamat! Konversi SKS Anda Telah Disetujui',
                'body' => nl2br(e("Halo {$nama}\n\nPermohonan konversi SKS Anda ke {$institusi} telah disetujui.\nProdi tujuan: {$prodi}\nTotal SKS diakui: {$sks} SKS\n\nSilakan hubungi bagian akademik untuk langkah selanjutnya.")),
                'wa' => "Halo {$nama}, konversi SKS Anda ke {$institusi} telah disetujui ({$sks} SKS). - KonverPro",
            ],
            'Rejected' => [
                'subject' => 'Update Permohonan Konversi SKS',
                'body' => nl2br(e("Halo {$nama}\n\nMohon maaf, permohonan konversi SKS Anda ditolak.\nAlasan: {$catatan}\n\nSilakan hubungi bagian akademik untuk informasi lebih lanjut.")),
                'wa' => "Halo {$nama}, mohon maaf permohonan konversi SKS Anda ditolak. Alasan: {$catatan}. - KonverPro",
            ],
            'Revisi' => [
                'subject' => 'Permintaan Revisi Konversi SKS - '.$nama,
                'body' => nl2br(e("Halo Admin\n\nTerdapat permintaan revisi dari Kaprodi untuk berkas konversi mahasiswa: {$nama}.\nCatatan Revisi: {$catatan}\n\nSilakan login ke sistem untuk melakukan perbaikan data.")),
                'wa' => '',
            ],
            default => null,
        };
    }
}
