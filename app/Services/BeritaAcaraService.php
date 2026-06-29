<?php

namespace App\Services;

use App\Enums\StatusPendaftarEnum;
use App\Models\BaDocument;
use App\Models\BaTemplate;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Models\PengaturanProdi;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BeritaAcaraService
{
    public function __construct(
        private DocumentVerificationService $verification,
    ) {}

    /**
     * Render BA from DB template. Falls back to legacy blade view if no template is configured.
     *
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

        $template = BaTemplate::getDefault();

        // fallback: legacy blade view
        if (! $template) {
            return $this->renderPdfLegacy($pendaftar, $document, $nomorBa);
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

        $tanggalPersetujuan = $pendaftar->approved_at ?? now();
        $tanggal = $tanggalPersetujuan->locale('id')->translatedFormat('d F Y');
        $prodiTujuan = $pendaftar->prodi
            ? trim(($pendaftar->prodi->jenjang ?? '') . ' ' . ($pendaftar->prodi->nama_prodi ?? ''))
            : '-';
        $kaprodi = $pendaftar->prodi?->kaprodi?->nama_lengkap ?? '..........................';

        // compute remaining kurikulum
        $hasilIds = $hasilDiakui->pluck('id_mk_tujuan')->filter()->values();
        $kurikulumSisa = $pendaftar->prodi?->kurikulumMk
            ? $pendaftar->prodi->kurikulumMk->whereNotIn('id', $hasilIds)->sortBy(['semester', 'nama_mk'])
            : collect();
        $totalSisa = $kurikulumSisa->sum('sks');

        // lookup map
        $replacer = [
            'LOGO_URL' => $template->logo_url ?? (config('app.url') . '/images/logo_unsia.png'),
            'INSTITUSI' => e($institusi),
            'PRODI_TUJUAN' => e($prodiTujuan),
            'NOMOR_BA' => e($nomorBa),
            'TANGGAL_BA' => e($tanggal),
            'NAMA_MHS' => e($pendaftar->nama_lengkap),
            'NIM_ASAL' => e($pendaftar->nim_asal ?? '-'),
            'PT_ASAL' => e($pendaftar->asal_kampus ?? '-'),
            'PRODI_ASAL' => e($pendaftar->asal_prodi ?? '-'),
            'TOTAL_SKS_ASAL' => (string) $totalSksAsal,
            'TOTAL_SKS_DIAKUI' => (string) ($pendaftar->total_sks_diakui ?? 0),
            'TOTAL_BELUM' => (string) $totalSksBelumDiakui,
            'TOTAL_SISA' => (string) $totalSisa,
            'TABLE_DIAKUI' => $this->renderTableDiakui($hasilDiakui),
            'TABLE_BELUM_DIAKUI' => $this->renderTableBelum($sksBelumDiakui),
            'TABLE_SEMESTER' => $this->renderTableSemester($kurikulumSisa),
            'VERIFICATION_QR' => $verificationQr,
            'NAMA_KAPRODI' => e($kaprodi),
            'HASH_DIGITAL' => e($pendaftar->hash_ba_digital ?? '-'),
            'NOMOR_DOKUMEN' => e($pendaftar->nomor_ba ?? '-'),
            'TANGGAL_DISETUJUI' => $tanggalPersetujuan->format('Y-m-d H:i:s') . ' WIB',
            'TANGGAL_DICETAK' => now()->format('Y-m-d H:i:s') . ' WIB',
            'VERSI_DOKUMEN' => $document ? (string) $document->version : '1',
        ];

        $html = $this->applyPlaceholders($template->content_header, $replacer);
        $html .= $this->applyPlaceholders($template->content_footer, $replacer);

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

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
     * Fallback: render using legacy blade view.
     */
    private function renderPdfLegacy(Pendaftar $pendaftar, ?BaDocument $document, string $nomorBa): array
    {
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

    private function renderTableDiakui($hasilDiakui): string
    {
        if ($hasilDiakui->isEmpty()) {
            return '<tr><td colspan="6" class="center muted">Tidak ada mata kuliah yang diakui.</td></tr>';
        }

        $rows = '';
        foreach ($hasilDiakui as $index => $item) {
            $rows .= '<tr>'
                . '<td class="center">' . ($index + 1) . '</td>'
                . '<td class="center mono">' . e($item->mkTujuan?->kode_mk ?? '-') . '</td>'
                . '<td><strong>' . e($item->mkTujuan?->nama_mk ?? '-') . '</strong></td>'
                . '<td class="center"><strong>' . e((string) $item->sks_diakui) . '</strong></td>'
                . '<td class="center"><strong>' . e($item->nilai_akhir_huruf ?? $item->transkripAsal?->nilai_huruf_asal ?? '-') . '</strong></td>'
                . '<td>' . e($item->transkripAsal?->nama_mk_asal ?? '-') . '</td>'
                . '</tr>';
        }

        return $rows;
    }

    private function renderTableBelum($sksBelumDiakui): string
    {
        if ($sksBelumDiakui->isEmpty()) {
            return '<tr><td colspan="6" class="center muted">Seluruh SKS transkrip asal yang memenuhi ketentuan telah diakui.</td></tr>';
        }

        $rows = '';
        foreach ($sksBelumDiakui as $index => $item) {
            $rows .= '<tr>'
                . '<td class="center">' . ($index + 1) . '</td>'
                . '<td><strong>' . e($item['nama_mk_asal']) . '</strong> <span class="muted">(' . e($item['nilai_huruf_asal'] ?? '-') . ')</span></td>'
                . '<td class="center">' . $item['sks_asal'] . '</td>'
                . '<td class="center">' . $item['sks_diakui'] . '</td>'
                . '<td class="center"><strong>' . $item['sks_belum_diakui'] . '</strong></td>'
                . '<td>' . e($item['alasan']) . '</td>'
                . '</tr>';
        }

        return $rows;
    }

    private function renderTableSemester($kurikulumSisa): string
    {
        if ($kurikulumSisa->isEmpty()) {
            return '<div class="panel center"><strong>Seluruh SKS kurikulum telah terpenuhi.</strong></div>';
        }

        $html = '';
        foreach ($kurikulumSisa->groupBy('semester') as $semester => $items) {
            $html .= '<div class="semester">Semester ' . e((string) $semester) . ' - Total ' . $items->sum('sks') . ' SKS</div>';
            $html .= '<table style="margin-bottom: 6px;"><tbody>';
            foreach ($items as $i => $mk) {
                $html .= '<tr>'
                    . '<td class="center" style="width: 28px;">' . ($i + 1) . '</td>'
                    . '<td class="center mono" style="width: 78px;">' . e($mk->kode_mk ?? '-') . '</td>'
                    . '<td>' . e($mk->nama_mk) . '</td>'
                    . '<td class="center" style="width: 42px;"><strong>' . e((string) $mk->sks) . '</strong></td>'
                    . '</tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html;
    }

    private function applyPlaceholders(?string $content, array $replacer): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        $search = array_map(fn ($key) => '{{' . $key . '}}', array_keys($replacer));

        return str_replace($search, array_values($replacer), $content);
    }

    public function approvalDocumentData(Pendaftar $pendaftar): array
    {
        $approvedAt = now();
        $nomorBa = $this->generateNomor($pendaftar, (int) $approvedAt->format('Y'));
        $pendaftar->loadMissing('hasilKonversi');

        // Signature hash per spec (for public verification)
        $prodiTujuan = $pendaftar->prodi
            ? trim(($pendaftar->prodi->jenjang ?? '') . ' ' . ($pendaftar->prodi->nama_prodi ?? ''))
            : '';
        $signatureHash = hash('sha256', implode('|', [
            $nomorBa,
            $pendaftar->nama_lengkap,
            $pendaftar->nim_asal ?? '',
            $prodiTujuan,
            $approvedAt->format('Y-m-d'),
            (string) $pendaftar->hasilKonversi()->where('is_unmatched', false)->sum('sks_diakui'),
        ]));

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
            'signature_hash' => $signatureHash,
        ];
    }

    public function createDocument(Pendaftar $pendaftar, string $approvedBy, ?string $signatureHash = null): BaDocument
    {
        $maxVersion = $pendaftar->baDocuments()->max('version');
        $version = (is_numeric($maxVersion) ? (int) $maxVersion : 0) + 1;
        $document = BaDocument::create([
            'id_pendaftar' => $pendaftar->id,
            'version' => $version,
            'document_number' => (string) $pendaftar->nomor_ba,
            'document_hash' => (string) $pendaftar->hash_ba_digital,
            'signature_hash' => $signatureHash,
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
        $replacement = $this->createDocument($pendaftar, $approvedBy, $data['signature_hash'] ?? null);

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
        $format = $pengaturan?->format_no_ba ?? 'BA/{YEAR}/{NO}/{PRODI}';
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
