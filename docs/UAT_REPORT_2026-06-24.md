# Laporan UAT Otomatis KonverPro - diperbarui 25 Juni 2026

## Ruang Lingkup

UAT menggunakan tiga mahasiswa dummy pada PJJ Informatika:

- UAT001: matching, override Kaprodi, approve, dan unduh BA.
- UAT002: matching, revisi, koreksi Akademik, matching ulang, dan approve.
- UAT003: matching dan reject.

Tidak ada kredensial atau pengiriman live ke Sumopod, SMTP, maupun Fonnte.

## Cakupan Role

### Superadmin

- CRUD user, prodi, pengaturan prodi, dan kamus sinonim.
- Meninjau, mencari, mengaktifkan, dan menonaktifkan master ekuivalensi.
- Update konfigurasi global.
- Dashboard, metrik mutu operasional, audit log, laporan JSON/CSV, dan notification center.

### Admin

- Download template Excel.
- Upload workbook multi-mahasiswa.
- List, pencarian, filter, detail, dan download berkas privat.
- Membagikan tautan portal hasil konversi kepada mahasiswa terkait.
- Menerima notifikasi revisi, approve, dan reject.

### Akademik

- Dashboard, antrean, koreksi mahasiswa/transkrip.
- CRUD kurikulum dan kamus sinonim.
- Dispatch matching, review hasil, dan konfirmasi ke Kaprodi.
- Siklus revisi dan matching ulang.
- Meninjau serta memutuskan permohonan evaluasi ulang dari mahasiswa.

### Kaprodi

- Dashboard, list/detail validasi, override mapping.
- Approve, revisi, reject, dan batas SKS.
- Pengesahan BA menggunakan QR otomatis tanpa unggahan gambar tanda tangan.
- QR membuka halaman verifikasi publik yang menampilkan status dan versi dokumen.
- Dokumen final dapat dicabut atau diganti dengan versi baru tanpa menghapus riwayat.
- Laporan JSON/CSV, download BA, dan antrean pengiriman BA ke WhatsApp mahasiswa.
- BA menampilkan SKS asal yang belum diakui dan rencana mata kuliah per semester yang masih harus ditempuh.
- Enam akun Kaprodi resmi diuji dengan pembatasan akses ke program studi masing-masing.

## Hasil

- UAT workflow lengkap: lulus, termasuk sampel mata kuliah yang tidak diakui dan rencana studi lanjutan.
- Seluruh backend: 34 tes, 383 assertion.
- Pengiriman dokumen Fonnte: lulus menggunakan HTTP mock tanpa pesan live.
- QR keenam Kaprodi berhasil dipindai ulang oleh decoder dan membuka URL verifikasi dokumen yang tepat.
- Kalimat pengesahan publik menampilkan program studi, nama, dan gelar Kaprodi terkait.
- Portal mahasiswa, evaluasi ulang, lifecycle dokumen, dan penggunaan ulang ekuivalensi lulus pada feature test.
- PHPStan: 0 error.
- ESLint dan Next.js production build: lulus.
- Browser E2E: 22 skenario lulus pada desktop dan mobile, termasuk login keenam Kaprodi resmi.
- Audit Axe: tidak ada pelanggaran aksesibilitas berlevel serious atau critical pada login dan dashboard.
- BA dummy dirender menjadi dua halaman A4 dan diperiksa visual: rincian akademik pada halaman pertama serta pengesahan dan verifikasi pada halaman kedua.
- Nomor BA, waktu persetujuan, dan hash disimpan permanen.
- Poin analisis capaian pembelajaran, versioning kurikulum/degree audit, dan role komite penilai sengaja ditunda sampai keputusan Project Manager.

## Pekerjaan Khusus Menjelang Deployment

- Isi dan uji kredensial Sumopod, SMTP, dan Fonnte pada environment UAT resmi.
- Uji pengiriman hanya ke email/nomor UAT yang disetujui.
- Konfigurasikan process manager untuk queue worker.
- Gunakan database UAT/production terpisah, HTTPS, backup, dan monitoring.
- Minta persetujuan final format BA dari pemilik proses akademik UNSIA.
