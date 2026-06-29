<?php

namespace App\Http\Controllers;

use App\Models\BaDocument;
use App\Models\Pendaftar;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifikasiPublikController extends Controller
{
    public function show(Request $request, string $nomor): View
    {
        $nomor = urldecode($nomor);

        /** @var BaDocument|null $document */
        $document = BaDocument::query()
            ->where('document_number', $nomor)
            ->with('pendaftar.prodi.kaprodi')
            ->first();

        if (! $document || ! $document->pendaftar) {
            return view('verifikasi-publik', [
                'status' => 'NOT_FOUND',
                'document' => null,
                'pendaftar' => null,
            ]);
        }

        /** @var Pendaftar $pendaftar */
        $pendaftar = $document->pendaftar;

        // Recompute hash from live data
        $prodiTujuan = $pendaftar->prodi
            ? trim(($pendaftar->prodi->jenjang ?? '') . ' ' . ($pendaftar->prodi->nama_prodi ?? ''))
            : '';

        $computedHash = hash('sha256', implode('|', [
            $document->document_number,
            $pendaftar->nama_lengkap,
            $pendaftar->nim_asal ?? '',
            $prodiTujuan,
            $document->approved_at->format('Y-m-d'),
            (string) $pendaftar->total_sks_diakui,
        ]));

        if ($document->status === 'revoked') {
            $status = 'REVOKED';
        } elseif ($document->status === 'replaced') {
            $status = 'REPLACED';
        } elseif ($document->signature_hash && hash_equals($document->signature_hash, $computedHash)) {
            $status = 'VALID';
        } elseif ($document->signature_hash) {
            $status = 'TAMPERED';
        } else {
            // Legacy document without signature_hash — can't verify
            $status = 'UNVERIFIABLE';
        }

        $kaprodi = $pendaftar->prodi?->kaprodi;

        return view('verifikasi-publik', [
            'status' => $status,
            'document' => $document,
            'pendaftar' => $pendaftar,
            'kaprodiNama' => $kaprodi ? $kaprodi->nama_lengkap : '-',
            'prodiTujuan' => $prodiTujuan,
        ]);
    }
}
