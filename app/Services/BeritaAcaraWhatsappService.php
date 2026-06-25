<?php

namespace App\Services;

use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use Illuminate\Support\Facades\Http;

class BeritaAcaraWhatsappService
{
    private const MAX_FILE_BYTES = 4 * 1024 * 1024;

    public function __construct(private BeritaAcaraService $beritaAcara) {}

    public function send(Pendaftar $pendaftar): void
    {
        $apiKey = PengaturanGlobal::get('fonnte_api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('Fonnte API key belum dikonfigurasi.');
        }

        if (! is_string($pendaftar->no_whatsapp) || $pendaftar->no_whatsapp === '') {
            throw new \RuntimeException('Nomor WhatsApp mahasiswa belum tersedia.');
        }

        $document = $this->beritaAcara->renderPdf($pendaftar);
        if (strlen($document['content']) >= self::MAX_FILE_BYTES) {
            throw new \RuntimeException('Ukuran Berita Acara melebihi batas file WhatsApp 4 MB.');
        }

        $message = "Halo {$pendaftar->nama_lengkap},\n\n"
            ."Berikut Berita Acara hasil konversi SKS Anda di Universitas Siber Asia.\n"
            ."Nomor dokumen: {$document['nomor_ba']}\n"
            ."SKS diakui: {$pendaftar->total_sks_diakui} SKS.\n\n"
            .'Silakan simpan dokumen ini dan hubungi bagian akademik apabila membutuhkan penjelasan lebih lanjut.';

        $response = Http::withHeaders(['Authorization' => $apiKey])
            ->timeout(30)
            ->attach('file', $document['content'], $document['filename'])
            ->post('https://api.fonnte.com/send', [
                'target' => $pendaftar->no_whatsapp,
                'message' => $message,
                'filename' => $document['filename'],
                'countryCode' => '0',
            ]);

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || ($payload['status'] ?? false) !== true) {
            $reason = is_array($payload) && is_string($payload['reason'] ?? null)
                ? $payload['reason']
                : 'Respons Fonnte tidak berhasil.';
            throw new \RuntimeException($reason);
        }

        $pendaftar->update(['ba_wa_sent_at' => now()]);
    }
}
