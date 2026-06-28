<?php

namespace Database\Seeders;

use App\Models\BaTemplate;
use Illuminate\Database\Seeder;

class BaTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $css = <<<'CSS'
<style>
    @page { margin: 22px 28px; }
    body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 9.2px; line-height: 1.32; }
    .kop { border-bottom: 3px solid #0f172a; padding-bottom: 8px; margin-bottom: 10px; }
    .muted { color: #64748b; }
    .title { text-align: center; margin: 10px 0; }
    .title h1 { font-size: 15px; text-decoration: underline; margin: 0 0 4px; }
    .panel { border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 6px; padding: 8px; margin: 8px 0 12px; }
    .info td { border: none; padding: 2px 4px; }
    .score { text-align: center; border-left: 2px solid #cbd5e1; padding-left: 12px; }
    .score strong { display: block; color: #16a34a; font-size: 29px; line-height: 1; }
    .section { font-size: 10px; font-weight: bold; margin: 10px 0 5px; padding-left: 7px; border-left: 4px solid #16a34a; }
    .section.warning { border-left-color: #ea580c; }
    table { width: 100%; border-collapse: collapse; }
    th { border: 1px solid #94a3b8; background: #f1f5f9; padding: 4px; font-size: 7.5px; text-transform: uppercase; text-align: center; }
    td { border: 1px solid #cbd5e1; padding: 3px 5px; vertical-align: top; }
    .center { text-align: center; }
    .right { text-align: right; }
    .mono { font-family: DejaVu Sans Mono, monospace; }
    .semester { background: #e2e8f0; border: 1px solid #94a3b8; border-bottom: none; padding: 3px 7px; font-weight: bold; text-transform: uppercase; }
    .total-box { background: #fffbeb; border: 1px solid #f59e0b; padding: 6px; font-weight: bold; margin-top: 6px; }
    .summary-box { border: 1px solid #cbd5e1; background: #fff; padding: 6px; text-align: center; }
    .summary-value { display: block; font-size: 15px; font-weight: bold; color: #031f37; }
    .summary-label { display: block; margin-top: 2px; color: #64748b; font-size: 7px; text-transform: uppercase; }
    .not-recognized { background: #fff7ed; }
    .signature { page-break-before: always; padding-top: 22px; page-break-inside: avoid; }
    .verification-box { text-align: center; font-size: 8px; }
    .verification-box p { margin: 2px 0; }
    .verification-qr { width: 118px; height: 118px; margin: 7px auto; }
    .hash { margin-top: 5px; padding-top: 4px; border-top: 1px dashed #cbd5e1; font-size: 6.5px; line-height: 1.2; color: #64748b; }
</style>
CSS;

        $header = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Konversi SKS</title>
    {$css}
</head>
<body>

<div class="kop">
    <table>
        <tr>
            <td style="width: 70px; border: none;">
                <img src="{{LOGO_URL}}" style="width:52px;height:52px;object-fit:contain;">
            </td>
            <td style="border: none;">
                <div style="font-size: 18px; font-weight: bold; text-transform: uppercase;">{{INSTITUSI}}</div>
                <div>Program Studi {{PRODI_TUJUAN}}</div>
                <div class="muted">Dokumen resmi hasil penyetaraan dan konversi mata kuliah</div>
            </td>
        </tr>
    </table>
</div>

<div class="title">
    <h1>BERITA ACARA KONVERSI SKS</h1>
    <div>Nomor: {{NOMOR_BA}}</div>
</div>

<p>
    Pada hari ini, tanggal <strong>{{TANGGAL_BA}}</strong>, berdasarkan hasil evaluasi akademik terhadap transkrip nilai
    mahasiswa pindahan/alih jenjang, Ketua Program Studi menetapkan rincian konversi mata kuliah sebagai berikut:
</p>

<div class="panel">
    <table>
        <tr>
            <td style="border: none;">
                <table class="info">
                    <tr>
                        <td style="width: 145px;"><strong>Nama Mahasiswa</strong></td>
                        <td style="width: 8px;">:</td>
                        <td><strong>{{NAMA_MHS}}</strong></td>
                    </tr>
                    <tr>
                        <td><strong>NIM Asal</strong></td>
                        <td>:</td>
                        <td>{{NIM_ASAL}}</td>
                    </tr>
                    <tr>
                        <td><strong>Perguruan Tinggi Asal</strong></td>
                        <td>:</td>
                        <td>{{PT_ASAL}}</td>
                    </tr>
                    <tr>
                        <td><strong>Program Studi Asal</strong></td>
                        <td>:</td>
                        <td>{{PRODI_ASAL}}</td>
                    </tr>
                    <tr>
                        <td><strong>Program Studi Tujuan</strong></td>
                        <td>:</td>
                        <td>{{PRODI_TUJUAN}}</td>
                    </tr>
                </table>
            </td>
            <td class="score" style="width: 120px; border: none;">
                <strong>{{TOTAL_SKS_DIAKUI}}</strong>
                <span class="muted">SKS Diakui</span>
            </td>
        </tr>
    </table>
</div>

<table style="margin-bottom: 8px;">
    <tr>
        <td class="summary-box" style="width: 25%;">
            <span class="summary-value">{{TOTAL_SKS_ASAL}}</span>
            <span class="summary-label">SKS Transkrip Asal</span>
        </td>
        <td class="summary-box" style="width: 25%;">
            <span class="summary-value" style="color: #16a34a;">{{TOTAL_SKS_DIAKUI}}</span>
            <span class="summary-label">SKS Diakui UNSIA</span>
        </td>
        <td class="summary-box not-recognized" style="width: 25%;">
            <span class="summary-value" style="color: #ea580c;">{{TOTAL_BELUM}}</span>
            <span class="summary-label">SKS Asal Belum Diakui</span>
        </td>
        <td class="summary-box" style="width: 25%;">
            <span class="summary-value">{{TOTAL_SISA}}</span>
            <span class="summary-label">Sisa SKS Kurikulum</span>
        </td>
    </tr>
</table>

<div class="section">A. DAFTAR MATA KULIAH YANG DIAKUI</div>
<table>
    <thead>
        <tr>
            <th style="width: 28px;">No</th>
            <th style="width: 78px;">Kode</th>
            <th>Mata Kuliah Tujuan UNSIA</th>
            <th style="width: 42px;">SKS</th>
            <th style="width: 44px;">Nilai</th>
            <th>Mata Kuliah Asal</th>
        </tr>
    </thead>
    <tbody>
        {{TABLE_DIAKUI}}
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="right"><strong>Total SKS Diakui</strong></td>
            <td class="center"><strong>{{TOTAL_SKS_DIAKUI}}</strong></td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

<div class="section warning">B. SKS TRANSKRIP ASAL YANG BELUM DIAKUI</div>
<p class="muted">
    Bagian ini menunjukkan mata kuliah asal yang belum memiliki padanan, atau selisih SKS asal yang tidak dapat diakui.
</p>
<table>
    <thead>
        <tr>
            <th style="width: 28px;">No</th>
            <th>Mata Kuliah Asal</th>
            <th style="width: 48px;">SKS Asal</th>
            <th style="width: 52px;">Diakui</th>
            <th style="width: 64px;">Belum Diakui</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        {{TABLE_BELUM_DIAKUI}}
    </tbody>
</table>

<div class="section warning">C. RENCANA MATA KULIAH SEMESTER SELANJUTNYA</div>
<p class="muted">
    Mahasiswa wajib mengambil mata kuliah berikut pada semester kurikulum terkait agar seluruh beban studi program tujuan terpenuhi.
</p>

{{TABLE_SEMESTER}}

<div class="total-box">
    <table>
        <tr>
            <td style="border: none;"><strong>Total Sisa SKS yang Harus Ditempuh</strong></td>
            <td class="right" style="border: none; color: #b45309;"><strong>{{TOTAL_SISA}} SKS</strong></td>
        </tr>
    </table>
</div>

<p class="muted" style="margin-top: 5px;">
    Catatan: penempatan semester mengikuti struktur kurikulum program studi. Jadwal aktual tetap menyesuaikan penawaran kelas dan arahan akademik.
</p>
HTML;

        $footer = <<<HTML
<div class="signature">
    <div class="title" style="margin-bottom: 18px;">
        <h1>LEMBAR PENGESAHAN DAN VERIFIKASI</h1>
        <div>Berita Acara Nomor: {{NOMOR_BA}}</div>
        <div>Versi dokumen: {{VERSI_DOKUMEN}}</div>
    </div>

    <div class="panel">
        <table class="info">
            <tr>
                <td style="width: 145px;"><strong>Nama Mahasiswa</strong></td>
                <td style="width: 8px;">:</td>
                <td><strong>{{NAMA_MHS}}</strong></td>
            </tr>
            <tr>
                <td><strong>NIM Asal</strong></td>
                <td>:</td>
                <td>{{NIM_ASAL}}</td>
            </tr>
            <tr>
                <td><strong>Program Studi Tujuan</strong></td>
                <td>:</td>
                <td>{{PRODI_TUJUAN}}</td>
            </tr>
            <tr>
                <td><strong>Tanggal Persetujuan</strong></td>
                <td>:</td>
                <td>{{TANGGAL_BA}}</td>
            </tr>
        </table>
    </div>

    <table style="margin: 14px 0 26px;">
        <tr>
            <td class="summary-box">
                <span class="summary-value">{{TOTAL_SKS_ASAL}}</span>
                <span class="summary-label">SKS Transkrip Asal</span>
            </td>
            <td class="summary-box">
                <span class="summary-value" style="color: #16a34a;">{{TOTAL_SKS_DIAKUI}}</span>
                <span class="summary-label">SKS Diakui</span>
            </td>
            <td class="summary-box not-recognized">
                <span class="summary-value" style="color: #ea580c;">{{TOTAL_BELUM}}</span>
                <span class="summary-label">SKS Belum Diakui</span>
            </td>
            <td class="summary-box">
                <span class="summary-value">{{TOTAL_SISA}}</span>
                <span class="summary-label">Sisa Kurikulum</span>
            </td>
        </tr>
    </table>

    <p style="text-align: center; margin-bottom: 18px;">
        Dokumen ini telah diperiksa dan disahkan oleh Ketua Program Studi sebagai hasil akhir evaluasi konversi SKS.
    </p>

    <table>
        <tr>
            <td style="width: 60%; border: none;"></td>
            <td class="verification-box" style="border: none;">
                <p>Jakarta, {{TANGGAL_BA}}<br><strong>Ketua Program Studi</strong></p>
                <img class="verification-qr" src="{{VERIFICATION_QR}}" alt="QR verifikasi dokumen" style="width:118px;height:118px;margin:7px auto;">
                <p><strong>{{NAMA_KAPRODI}}</strong></p>
                <p class="verification-text">Pindai QR untuk melihat pernyataan pengesahan digital.</p>
            </td>
        </tr>
    </table>
</div>

<div class="hash">
    <strong>VERIFIKASI DOKUMEN DIGITAL</strong><br>
    Digital Signature Hash: {{HASH_DIGITAL}}<br>
    Nomor Dokumen: {{NOMOR_DOKUMEN}}<br>
    Disetujui: {{TANGGAL_DISETUJUI}}<br>
    Dicetak otomatis oleh KonverPro UNSIA pada {{TANGGAL_DICETAK}}<br><br>
    QR membuka halaman verifikasi publik untuk dokumen ini.<br>
    Hash di atas merepresentasikan snapshot data konversi pada saat persetujuan. Perubahan setelah persetujuan akan menghasilkan hash yang berbeda.
</div>
</body>
</html>
HTML;

        BaTemplate::create([
            'name' => 'Default',
            'content_header' => $header,
            'content_footer' => $footer,
            'logo_url' => config('app.url') . '/images/logo_unsia.png',
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
