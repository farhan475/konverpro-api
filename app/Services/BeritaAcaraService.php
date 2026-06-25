<?php

namespace App\Services;

use App\Enums\StatusPendaftarEnum;
use App\Models\BaDocument;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Models\PengaturanProdi;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;

class BeritaAcaraService
{
    public function __construct(private DocumentVerificationService $verification) {}

    /**
     * @return array{content: string, filename: string, nomor_ba: string}
     */
    public function renderPdf(Pendaftar $pendaftar): array
    {
        $pendaftar->load([
            'prodi.kaprodi',
            'prodi.pengaturan',
            'prodi.kurikulumMk',
            'transkripAsal',
            'hasilKonversi.transkripAsal',
            'hasilKonversi.mkTujuan',
            'currentBaDocument',
        ]);

        $currentDocument = $pendaftar->currentBaDocument;
        $document = $currentDocument instanceof BaDocument ? $currentDocument : null;
        $nomorBa = $document instanceof BaDocument ? $document->document_number : $pendaftar->nomor_ba;
        if (! is_string($nomorBa) || $nomorBa === '') {
            throw new \RuntimeException('Nomor Berita Acara belum tersedia.');
        }

        $hasilDiakui = $pendaftar->hasilKonversi
            ->where('is_unmatched', false)
            ->filter(fn ($item) => $item->mkTujuan !== null);
        $hasilByTranskrip = $pendaftar->hasilKonversi->keyBy('id_transkrip_asal');
        $sksBelumDiakui = $pendaftar->transkripAsal
            ->map(function ($transkrip) use ($hasilByTranskrip): array {
                $hasil = $hasilByTranskrip->get($transkrip->id);
                $sksDiakui = $hasil && ! $hasil->is_unmatched && $hasil->mkTujuan
                    ? min((int) $transkrip->sks_asal, (int) $hasil->sks_diakui)
                    : 0;
                $reason = $hasil && is_string($hasil->match_reason) && $hasil->match_reason !== ''
                    ? $hasil->match_reason
                    : 'Tidak ditemukan padanan mata kuliah tujuan.';

                return [
                    'nama_mk_asal' => $transkrip->nama_mk_asal,
                    'sks_asal' => (int) $transkrip->sks_asal,
                    'nilai_huruf_asal' => $transkrip->nilai_huruf_asal,
                    'sks_diakui' => $sksDiakui,
                    'sks_belum_diakui' => max(0, (int) $transkrip->sks_asal - $sksDiakui),
                    'alasan' => $reason,
                ];
            })
            ->filter(fn (array $item): bool => $item['sks_belum_diakui'] > 0)
            ->values();

        $verificationText = $this->verification->verificationText($pendaftar);
        $verificationQr = $this->verification->qrDataUri($pendaftar);
        $institusi = (string) PengaturanGlobal::get('nama_institusi', 'Universitas Siber Asia');
        $totalSksAsal = $pendaftar->transkripAsal->reduce(
            fn (int $total, $item): int => $total + (int) $item->sks_asal,
            0
        );
        $totalSksBelumDiakui = $sksBelumDiakui->reduce(
            fn (int $total, array $item): int => $total + $item['sks_belum_diakui'],
            0
        );

        $html = view('pdf.berita-acara', [
            'pendaftar' => $pendaftar,
            'hasil' => $hasilDiakui,
            'sks_belum_diakui' => $sksBelumDiakui,
            'total_sks_asal' => $totalSksAsal,
            'total_sks_belum_diakui' => $totalSksBelumDiakui,
            'institusi' => $institusi,
            'nomor_ba' => $nomorBa,
            'verification_text' => $verificationText,
            'verification_qr' => $verificationQr,
            'document' => $document,
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safeNim = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($pendaftar->nim_asal ?? $pendaftar->id));

        return [
            'content' => $dompdf->output(),
            'filename' => "Berita_Acara_{$safeNim}.pdf",
            'nomor_ba' => $nomorBa,
        ];
    }

    /**
     * @return array{nomor_ba: string, approved_at: Carbon, hash_ba_digital: string}
     */
    public function approvalDocumentData(Pendaftar $pendaftar): array
    {
        $approvedAt = now();
        $nomorBa = $this->generateNomor($pendaftar, (int) $approvedAt->format('Y'));
        $pendaftar->loadMissing('hasilKonversi');

        $snapshot = [
            'pendaftar_id' => $pendaftar->id,
            'nomor_ba' => $nomorBa,
            'approved_at' => $approvedAt->toIso8601String(),
            'hasil' => $pendaftar->hasilKonversi
                ->sortBy('id')
                ->map(fn ($item) => [
                    'id_transkrip_asal' => $item->id_transkrip_asal,
                    'id_mk_tujuan' => $item->id_mk_tujuan,
                    'sks_diakui' => $item->sks_diakui,
                    'nilai' => $item->nilai_akhir_huruf,
                ])
                ->values()
                ->all(),
        ];

        return [
            'nomor_ba' => $nomorBa,
            'approved_at' => $approvedAt,
            'hash_ba_digital' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
        ];
    }

    public function createDocument(Pendaftar $pendaftar, string $approvedBy): BaDocument
    {
        $maxVersion = $pendaftar->baDocuments()->max('version');
        $version = (is_numeric($maxVersion) ? (int) $maxVersion : 0) + 1;
        $document = BaDocument::create([
            'id_pendaftar' => $pendaftar->id,
            'version' => $version,
            'document_number' => (string) $pendaftar->nomor_ba,
            'document_hash' => (string) $pendaftar->hash_ba_digital,
            'status' => 'final',
            'approved_by' => $approvedBy,
            'approved_at' => $pendaftar->approved_at ?? now(),
        ]);
        $pendaftar->update(['current_ba_document_id' => $document->id]);
        $pendaftar->setRelation('currentBaDocument', $document);

        return $document;
    }

    public function replaceDocument(Pendaftar $pendaftar, string $approvedBy): BaDocument
    {
        $currentDocument = $pendaftar->currentBaDocument;
        $previous = $currentDocument instanceof BaDocument ? $currentDocument : null;
        $data = $this->approvalDocumentData($pendaftar);
        $pendaftar->update($data);
        $pendaftar->refresh();
        $replacement = $this->createDocument($pendaftar, $approvedBy);

        if ($previous) {
            $previous->update([
                'status' => 'replaced',
                'replaced_by_id' => $replacement->id,
            ]);
        }

        return $replacement;
    }

    private function generateNomor(Pendaftar $pendaftar, int $year): string
    {
        $pendaftar->loadMissing('prodi.pengaturan');
        /** @var PengaturanProdi|null $pengaturan */
        $pengaturan = $pendaftar->prodi?->pengaturan;
        $format = $pengaturan->format_no_ba ?? 'BA/{YEAR}/{NO}/{PRODI}';
        $prodiCode = $pendaftar->prodi->kode_prodi ?? 'UNKNOWN';

        $sequence = Pendaftar::query()
            ->where('id_prodi', $pendaftar->id_prodi)
            ->where('status', StatusPendaftarEnum::APPROVED)
            ->whereYear('approved_at', $year)
            ->count() + 1;

        return str_replace(
            ['{YEAR}', '{NO}', '{PRODI}'],
            [(string) $year, str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), $prodiCode],
            $format
        );
    }
}
