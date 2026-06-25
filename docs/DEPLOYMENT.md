# Deployment KonverPro UNSIA

## Prasyarat

- PHP 8.2+ dengan `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, dan `xml`.
- MySQL 8+, Composer 2, Node.js 20+, dan pnpm.
- HTTPS untuk frontend dan API.
- Document root backend diarahkan ke folder `public`.

## Backend

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=KonverproDefaultsSeeder --force
php artisan optimize
```

Pastikan `storage` dan `bootstrap/cache` dapat ditulis oleh user web server. File transkrip dan arsip privat berada di `storage/app/private` dan tidak boleh disajikan langsung oleh web server.

## Frontend

```bash
pnpm install --frozen-lockfile
pnpm build
pnpm start
```

Set `NEXT_PUBLIC_API_BASE_URL=https://api-domain/` sebelum build.

## Environment Produksi

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://api-domain`
- `FRONTEND_URL=https://frontend-domain`
- `APP_TIMEZONE=Asia/Jakarta`
- `API_ALLOWED_ORIGINS=https://frontend-domain`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax` bila frontend dan API masih satu site.
- Gunakan kredensial database khusus aplikasi dengan hak minimum.
- Jangan menyimpan Sumopod, SMTP, atau Fonnte key di repository.
- Pastikan paket Fonnte yang digunakan mendukung pengiriman dokumen melalui binary file upload.
- Berita Acara dikirim sebagai PDF dan dibatasi kurang dari 4 MB oleh aplikasi.
- Ekstensi PHP `gd` wajib aktif untuk membentuk QR pengesahan Berita Acara.
- Buat akun Kaprodi produksi dengan password acak lalu hubungkan masing-masing akun ke prodi melalui menu Superadmin. Jangan memakai password demo dari `DatabaseSeeder`.
- Nama lengkap akun Kaprodi harus mencakup gelar karena identitas tersebut ditanam langsung ke QR pengesahan.
- `FRONTEND_URL` harus berupa origin publik frontend karena URL ini ditanam ke QR verifikasi Berita Acara dan tautan portal mahasiswa.
- Pastikan route publik `/verify/{id}` dan `/portal/{token}` dapat dibuka melalui HTTPS tanpa autentikasi staf.

## Operasional

- Jadwalkan `php artisan schedule:run` setiap menit bila scheduler digunakan.
- Jalankan worker queue secara permanen melalui process manager:
  `php artisan queue:work --queue=matching,notifications,default --tries=3 --timeout=120`.
- Pantau tabel `failed_jobs`; matching dan notifikasi eksternal tidak lagi dijalankan di request web.
- Lakukan uji pengiriman Berita Acara hanya ke nomor WhatsApp UAT sebelum kanal diaktifkan untuk pengguna.
- Backup database dan `storage/app/private` secara terjadwal.
- Pantau log Laravel, status queue, ruang disk, dan masa berlaku HTTPS.
- Jalankan `php artisan test`, `vendor/bin/phpstan analyse`, `pnpm lint`, `pnpm build`, dan `pnpm test:e2e` sebelum rilis.

## Rollback

1. Jalankan `php artisan down`.
2. Pulihkan release aplikasi sebelumnya.
3. Pulihkan database hanya jika migrasi rilis tidak kompatibel.
4. Jalankan `php artisan optimize:clear && php artisan optimize`.
5. Jalankan `php artisan up`.
