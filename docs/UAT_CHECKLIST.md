# Checklist UAT KonverPro UNSIA

## Persiapan

- Gunakan database UAT terpisah dari produksi.
- Buat satu akun untuk setiap role.
- Gunakan data mahasiswa dummy tanpa informasi pribadi nyata.
- Isi minimal satu kurikulum lengkap untuk prodi yang diuji.

## Skenario

- Login, logout, sesi kedaluwarsa, dan penolakan akses lintas role.
- Admin mengunduh template, mengunggah Excel valid, Excel rusak, dan PDF opsional.
- Akademik mengoreksi data, menjalankan matching, dan mengirim hasil ke Kaprodi.
- Kaprodi melakukan override, approve, revisi, reject, dan bulk approve.
- Unduh BA dan periksa nomor, versi, identitas, SKS, nilai, QR pengesahan, serta hash.
- Pindai QR dan pastikan halaman publik menunjukkan dokumen final, dicabut, atau diganti secara akurat.
- Cabut dan ganti BA dummy, lalu pastikan versi lama tetap dapat diverifikasi sebagai riwayat.
- Buka portal mahasiswa, unduh BA final, dan kirim permohonan evaluasi ulang.
- Akademik menerima atau menolak evaluasi ulang serta menyimpan catatan keputusan.
- Pastikan keputusan final Kaprodi membentuk master ekuivalensi dan matching berikutnya dapat memakai metode `Referensi`.
- Unduh laporan CSV dan periksa angka dashboard.
- Periksa metrik waktu proses, override manual, pengiriman WA, permohonan evaluasi ulang, dan data unmatched.
- Periksa notification center pada keempat role dan pastikan notifikasi tidak dapat dibaca user lain.
- Pastikan matching masuk queue `matching` dan email/WhatsApp masuk queue `notifications`.
- Simpan konfigurasi dengan secret yang dimask.
- Jalankan audit aksesibilitas desktop dan mobile untuk halaman login serta dashboard.
- Uji Email dan WhatsApp hanya ke alamat atau nomor khusus UAT.

## Kriteria Lulus

- Tidak ada error 500, redirect loop, atau akses data lintas prodi.
- Total SKS dan status konsisten antara frontend, API, laporan, dan BA.
- File private hanya dapat diakses user yang berwenang.
- Semua temuan memiliki severity, langkah reproduksi, bukti, dan keputusan perbaikan.
- Pemilik proses Akademik dan TI memberikan persetujuan tertulis sebelum produksi.
