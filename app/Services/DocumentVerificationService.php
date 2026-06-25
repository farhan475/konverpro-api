<?php

namespace App\Services;

use App\Models\BaDocument;
use App\Models\Pendaftar;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class DocumentVerificationService
{
    public function verificationText(Pendaftar $pendaftar): string
    {
        $pendaftar->loadMissing('prodi.kaprodi');

        $prodiName = $pendaftar->prodi?->nama_prodi;
        $kaprodiName = $pendaftar->prodi?->kaprodi?->nama_lengkap;

        if (! is_string($prodiName) || $prodiName === '' || ! is_string($kaprodiName) || $kaprodiName === '') {
            throw new \RuntimeException('Identitas Ketua Program Studi belum dikonfigurasi.');
        }

        $prodiName = preg_replace('/^PJJ\s+/i', '', $prodiName) ?? $prodiName;

        return "Dokumen ini telah diverifikasi, disetujui, dan diresmikan oleh Ketua Program Studi {$prodiName}, {$kaprodiName}";
    }

    public function qrDataUri(Pendaftar $pendaftar): string
    {
        $pendaftar->loadMissing('currentBaDocument');
        $document = $pendaftar->currentBaDocument;
        $documentId = $document instanceof BaDocument ? $document->id : null;
        if (! is_string($documentId)) {
            throw new \RuntimeException('Dokumen Berita Acara belum tersedia untuk diverifikasi.');
        }
        $configuredUrl = config('konverpro.frontend_url');
        $frontendUrl = rtrim(is_string($configuredUrl) ? $configuredUrl : 'http://localhost:3000', '/');

        $result = (new Builder(
            writer: new PngWriter,
            data: "{$frontendUrl}/verify/{$documentId}",
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 320,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        return $result->getDataUri();
    }
}
