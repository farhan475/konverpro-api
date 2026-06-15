<!DOCTYPE html>
<html>
<head>
    <title>Berita Acara Konversi SKS</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .title { font-size: 16px; font-bold: bold; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 5px; vertical-align: top; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .data-table th { background-color: #f2f2f2; font-size: 11px; text-transform: uppercase; }
        .footer { margin-top: 50px; }
        .signature-box { float: right; width: 250px; text-align: center; }
        .signature-space { height: 80px; }
        .hash { font-family: monospace; font-size: 10px; color: #666; margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $institusi }}</div>
        <div>BERITA ACARA PENYETARAAN / KONVERSI MATA KULIAH</div>
        <div>Nomor: {{ $nomor_ba }}</div>
    </div>

    <table class="info-table">
        <tr>
            <td width="150">Nama Mahasiswa</td>
            <td width="10">:</td>
            <td><strong>{{ $pendaftar->nama_lengkap }}</strong></td>
        </tr>
        <tr>
            <td>NIM Asal</td>
            <td>:</td>
            <td>{{ $pendaftar->nim_asal ?? '-' }}</td>
        </tr>
        <tr>
            <td>Asal Kampus / Prodi</td>
            <td>:</td>
            <td>{{ $pendaftar->asal_kampus }} / {{ $pendaftar->asal_prodi }}</td>
        </tr>
        <tr>
            <td>Program Studi Tujuan</td>
            <td>:</td>
            <td>{{ $pendaftar->prodi->nama_prodi }} ({{ $pendaftar->prodi->jenjang }})</td>
        </tr>
    </table>

    <p>Berdasarkan hasil evaluasi dan verifikasi dokumen akademik, maka ditetapkan konversi mata kuliah sebagai berikut:</p>

    <table class="data-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Mata Kuliah Asal</th>
                <th>SKS</th>
                <th>Nilai</th>
                <th>Mata Kuliah Tujuan (UNSIA)</th>
                <th>SKS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($hasil as $index => $item)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ $item->transkripAsal->nama_mk_asal }}</td>
                <td style="text-align: center;">{{ $item->transkripAsal->sks_asal }}</td>
                <td style="text-align: center;">{{ $item->transkripAsal->nilai_huruf_asal }}</td>
                <td>{{ $item->mkTujuan->nama_mk ?? 'N/A' }}</td>
                <td style="text-align: center;">{{ $item->sks_diakui }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold;">
                <td colspan="5" style="text-align: right;">TOTAL SKS DIAKUI</td>
                <td style="text-align: center;">{{ $pendaftar->total_sks_diakui }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <div style="float: left; width: 300px;">
            <p>Jakarta, {{ date('d F Y') }}<br>Mahasiswa,</p>
            <div class="signature-space"></div>
            <p>( {{ $pendaftar->nama_lengkap }} )</p>
        </div>
        
        <div class="signature-box">
            <p>Mengetahui,<br>Ketua Program Studi,</p>
            <div class="signature-space">
                @if($signature)
                    <img src="{{ $signature }}" style="max-height: 70px;">
                @endif
            </div>
            <p><strong>( {{ $pendaftar->prodi->kaprodi->nama_lengkap ?? '..........................' }} )</strong></p>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="hash">
        Digital Signature Hash: {{ $pendaftar->hash_ba_digital }}<br>
        Dicetak otomatis oleh KonverPro UNSIA pada {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>
