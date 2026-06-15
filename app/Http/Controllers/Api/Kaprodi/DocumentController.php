<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function downloadBa(Pendaftar $pendaftar): Response
    {
        if ($pendaftar->status !== 'Approved') {
            abort(403, 'Hanya permohonan yang disetujui yang dapat mengunduh Berita Acara.');
        }

        $pendaftar->load(['prodi.kaprodi', 'prodi.pengaturan', 'hasilKonversi.transkripAsal', 'hasilKonversi.mkTujuan']);

        $institusi = PengaturanGlobal::get('nama_institusi', 'Universitas Siber Asia');
        $nomorBa = $this->generateNomorBa($pendaftar);
        
        $signaturePath = $pendaftar->prodi->kaprodi->tanda_tangan_path ?? null;
        $signatureBase64 = null;
        
        if ($signaturePath && Storage::disk('private')->exists($signaturePath)) {
            $type = pathinfo($signaturePath, PATHINFO_EXTENSION);
            $data = Storage::disk('private')->get($signaturePath);
            $signatureBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $html = view('pdf.berita-acara', [
            'pendaftar' => $pendaftar,
            'hasil' => $pendaftar->hasilKonversi()->where('is_unmatched', false)->get(),
            'institusi' => $institusi,
            'nomor_ba' => $nomorBa,
            'signature' => $signatureBase64
        ])->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "Berita_Acara_{$pendaftar->nim_asal}_{$pendaftar->nama_lengkap}.pdf";
        
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function generateNomorBa(Pendaftar $pendaftar): string
    {
        $pengaturan = $pendaftar->prodi->pengaturan;
        $format = $pengaturan->format_no_ba ?? 'BA/{YEAR}/{NO}/{PRODI}';
        
        $year = date('Y');
        $prodiCode = $pendaftar->prodi->kode_prodi ?? 'UNKNOWN';
        
        // Simpel increment based on approved count in this year/prodi
        $count = Pendaftar::where('id_prodi', $pendaftar->id_prodi)
            ->where('status', 'Approved')
            ->whereYear('updated_at', $year)
            ->count();
            
        $no = str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);

        return str_replace(['{YEAR}', '{NO}', '{PRODI}'], [$year, $no, $prodiCode], $format);
    }
}
