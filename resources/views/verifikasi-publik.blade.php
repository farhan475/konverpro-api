<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dokumen - KonverPro UNSIA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            max-width: 520px;
            width: 100%;
            padding: 40px 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .logo-icon {
            width: 40px; height: 40px;
            background: #031f37;
            color: #FDD824;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 18px;
        }
        .logo-text h1 {
            font-size: 18px;
            letter-spacing: 0.5px;
        }
        .logo-text p {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 24px;
        }
        .badge.valid { background: #dcfce7; color: #166534; }
        .badge.not-found { background: #f1f5f9; color: #475569; }
        .badge.tampered { background: #fef2f2; color: #991b1b; }
        .badge.revoked { background: #fef2f2; color: #991b1b; }
        .badge.replaced { background: #fffbeb; color: #92400e; }
        .badge.unverifiable { background: #f1f5f9; color: #475569; }
        .info { margin-top: 20px; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .info-label { color: #64748b; }
        .info-value { font-weight: 600; text-align: right; }
        .footer {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 11px;
            color: #94a3b8;
            text-align: center;
        }
        .error-detail {
            margin-top: 16px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 13px;
            color: #475569;
        }
        .hash-display {
            margin-top: 16px;
            font-size: 10px;
            color: #94a3b8;
            word-break: break-all;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <div class="logo-icon">K</div>
            <div class="logo-text">
                <h1>KonverPro</h1>
                <p>Sistem Konversi SKS UNSIA</p>
            </div>
        </div>

        @if ($status === 'NOT_FOUND')
            <div class="badge not-found">TIDAK DITEMUKAN</div>
            <p style="color: #64748b; font-size: 14px;">Dokumen dengan nomor ini tidak ditemukan dalam sistem. Periksa kembali nomor dokumen atau hubungi admin.</p>

        @elseif ($status === 'VALID')
            <div class="badge valid">VALID</div>
            <p style="color: #166534; font-size: 14px; font-weight: 600;">Dokumen ini telah diverifikasi dan sesuai dengan data asli.</p>

            <div class="info">
                <div class="info-row">
                    <span class="info-label">Nomor Dokumen</span>
                    <span class="info-value">{{ $document->document_number }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nama Mahasiswa</span>
                    <span class="info-value">{{ $pendaftar->nama_lengkap }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">NIM Asal</span>
                    <span class="info-value">{{ $pendaftar->nim_asal ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Program Studi Tujuan</span>
                    <span class="info-value">{{ $prodiTujuan }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tanggal Persetujuan</span>
                    <span class="info-value">{{ $document->approved_at ? $document->approved_at->locale('id')->translatedFormat('d F Y') : '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total SKS Diakui</span>
                    <span class="info-value">{{ $pendaftar->total_sks_diakui }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Versi Dokumen</span>
                    <span class="info-value">{{ $document->version }}</span>
                </div>
            </div>

        @elseif ($status === 'TAMPERED')
            <div class="badge tampered">DOKUMEN TELAH DIUBAH</div>
            <p style="color: #991b1b; font-size: 14px;">Hash dokumen tidak cocok dengan data yang tersimpan. Dokumen ini mungkin telah dimodifikasi setelah ditandatangani.</p>
            <div class="error-detail">
                <strong>Peringatan:</strong> Dokumen ini TIDAK dapat diverifikasi. Hubungi administrator UNSIA untuk informasi lebih lanjut.
            </div>

        @elseif ($status === 'REVOKED')
            <div class="badge revoked">DICABUT</div>
            <p style="color: #991b1b; font-size: 14px;">Dokumen ini telah dicabut dan tidak lagi berlaku.</p>
            @if ($document->revoked_reason)
                <div class="error-detail">
                    <strong>Alasan pencabutan:</strong> {{ $document->revoked_reason }}
                </div>
            @endif

        @elseif ($status === 'REPLACED')
            <div class="badge replaced">DIGANTIKAN</div>
            <p style="color: #92400e; font-size: 14px;">Dokumen ini telah digantikan oleh versi yang lebih baru.</p>

        @elseif ($status === 'UNVERIFIABLE')
            <div class="badge unverifiable">TIDAK DAPAT DIVERIFIKASI</div>
            <p style="color: #475569; font-size: 14px;">Dokumen ini diterbitkan sebelum sistem verifikasi digital diaktifkan. Silakan hubungi admin untuk verifikasi manual.</p>
        @endif

        <div class="footer">
            Dokumen ini dicetak otomatis oleh KonverPro UNSIA.
            @if ($document && $pendaftar)
                <div class="hash-display">
                    Signature Hash: {{ $document->signature_hash ?? 'Tidak tersedia' }}
                </div>
            @endif
        </div>
    </div>
</body>
</html>
