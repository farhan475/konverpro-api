<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Berita Acara Konversi - {{ $pendaftar->id }}</title>
    <style>
        body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.5; color: #000; }
        .kop-surat { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .kop-surat h1 { font-size: 16pt; margin: 0; text-transform: uppercase; }
        .kop-surat p { font-size: 10pt; margin: 2px 0; }
        .title { text-align: center; font-weight: bold; text-decoration: underline; font-size: 14pt; margin-bottom: 5px; }
        .subtitle { text-align: center; font-weight: bold; font-size: 11pt; margin-bottom: 20px; }
        .info-table { margin-bottom: 20px; width: 100%; }
        .info-table td { vertical-align: top; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 5px; }
        .data-table th { background-color: #f2f2f2; text-transform: uppercase; }
        .footer { margin-top: 50px; width: 100%; }
        .footer td { text-align: center; width: 50%; }
        .signature-box { border: 1px dashed #ccc; height: 80px; width: 150px; margin: 10px auto; position: relative; }
        .digital-hash { font-family: monospace; font-size: 8pt; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="kop-surat">
        <table width="100%">
            <tr>
                <td width="80%">
                    <h1>{{ $kampus->nama_kampus }}</h1>
                    <p>{{ $kampus->alamat_resmi }}</p>
                    <p>Telepon: {{ $kampus->no_telp }} | Website: {{ $kampus->website }}</p>
                </td>
                <td width="20%" style="text-align: right;">
                    <div style="width: 60px; height: 60px; border: 1px solid #ccc; line-height: 60px; text-align: center; font-size: 8pt; color: #999;">LOGO</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">BERITA ACARA HASIL KONVERSI NILAI</div>
    <div class="subtitle">NOMOR: {{ date('Y') }}/BA-KPV/{{ $pendaftar->id }}</div>

    <p>Pada hari ini, tanggal <strong>{{ \Carbon\Carbon::parse($pendaftar->created_at)->translatedFormat('d F Y') }}</strong>, telah dilakukan proses validasi konversi nilai mahasiswa dengan rincian sebagai berikut:</p>

    <table class="info-table">
        <tr>
            <td width="30%">Nama Mahasiswa</td>
            <td width="2%">:</td>
            <td><strong>{{ strtoupper($pendaftar->nama_lengkap) }}</strong></td>
        </tr>
        <tr>
            <td>ID Pendaftar</td>
            <td>:</td>
            <td>{{ $pendaftar->id }}</td>
        </tr>
        <tr>
            <td>Asal Perguruan Tinggi</td>
            <td>:</td>
            <td>{{ strtoupper($pendaftar->asal_kampus) }}</td>
        </tr>
        <tr>
            <td>Program Studi Tujuan</td>
            <td>:</td>
            <td>{{ $prodi->jenjang }} - {{ $prodi->nama_prodi }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Mata Kuliah Diakui</th>
                <th width="10%">SKS</th>
                <th width="10%">Nilai</th>
                <th>Mata Kuliah Asal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($hasil as $index => $h)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ $h->nama_mk_tujuan }}</td>
                <td style="text-align: center;">{{ $h->sks_diakui }}</td>
                <td style="text-align: center;"><strong>{{ $h->nilai_akhir_huruf }}</strong></td>
                <td style="font-style: italic;">{{ $h->nama_mk_asal }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold;">
                <td colspan="2" style="text-align: right;">TOTAL SKS DIAKUI</td>
                <td style="text-align: center;">{{ $pendaftar->total_sks_diakui }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <table class="footer">
        <tr>
            <td>
                MAHASISWA BERSANGKUTAN,<br><br><br><br><br>
                <strong>{{ strtoupper($pendaftar->nama_lengkap) }}</strong>
            </td>
            <td>
                KAMPUS, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}<br>
                Ketua Program Studi,<br>
                <div class="signature-box">
                    <span style="font-size: 8pt; color: #2ecc71; font-weight: bold; position: absolute; top: 40%; left: 10%;">SIGNED DIGITALLY</span>
                </div>
                <strong>{{ strtoupper($kampus->rektor_pimpinan ?: 'KAPRODI') }}</strong>
                <div class="digital-hash">Secure Hash: {{ substr($pendaftar->hash_ba_digital, -12) }}</div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 50px; font-size: 8pt; text-align: center; color: #999; font-style: italic; border-top: 1px solid #eee; padding-top: 10px;">
        Dokumen ini sah dan diterbitkan secara elektronik oleh Sistem KonverPro. Segala bentuk manipulasi data pada dokumen ini dapat ditelusuri melalui sistem audit internal.
    </div>
</body>
</html>
