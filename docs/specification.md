# KonverPro UNSIA — Perencanaan Teknis v4
> Sistem Konversi Kredit Internal Universitas Siber Asia  
> Revisi v4: disempurnakan dari analisis kode repo aktual + panduan desain frontend lengkap

---

## Ringkasan Eksekutif

KonverPro UNSIA adalah sistem konversi kredit internal yang dibangun khusus untuk Universitas Siber Asia. Direfactor dari SaaS multi-tenant menjadi aplikasi single-institution yang sederhana, mudah dioperasikan staf non-teknis, dan menghasilkan dokumen konversi yang dapat dipertanggungjawabkan secara akademik.

**Prinsip utama:** Tidak ada landing page. Tidak ada billing/saldo. Tidak ada multi-tenancy. Fokus pada alur konversi yang akurat dan tampilan yang bersih.

---

## 1. Keputusan Arsitektur Final

| Topik | Keputusan | Alasan |
|---|---|---|
| Auth | Laravel Sanctum (Bearer Token) | Natural untuk Next.js, tidak perlu CSRF dance |
| Primary Key | UUID di semua tabel | Aman dari enumerasi, cocok untuk dokumen resmi |
| Multi-tenancy | Dihapus total | Sistem hanya untuk UNSIA |
| Billing & saldo | Dihapus total | Tidak relevan untuk sistem internal |
| Role kurikulum | Digabung ke akademik | Mengurangi kompleksitas |
| Input transkrip | Template Excel (admin isi) + PDF arsip opsional | Data bersih, format terkontrol |
| Matching | Fuzzy (kamus sinonim) → Sumopod → Manual Kaprodi | Bertahap, hemat API call |
| Sumopod API key | Disimpan di `pengaturan_global` terenkripsi | Bisa diganti superadmin tanpa redeploy |
| Notifikasi | Email (SMTP) + WhatsApp (Fonnte) | Dua kanal, konfigurasi via UI superadmin |
| Kamus sinonim | Database + CRUD (superadmin & akademik) | Fleksibel tanpa deploy ulang |
| Landing page | Tidak ada | Sistem internal, langsung login |
| Token storage | localStorage (saat ini) → **httpOnly cookie** (target) | Lebih aman dari XSS |

---

## 2. Perubahan dari Repo Lama ke Versi Baru

### Yang Dihapus
| Item | Alasan |
|---|---|
| Tabel `kampus` | Single institution, diganti 1 baris `pengaturan_global` |
| Tabel `transaksi_saldo` | Tidak ada billing |
| Tabel `notifikasi_templates` | Template hardcode di `NotifikasiService` |
| Tabel `mk_referensi_ai` | Diganti `kamus_sinonim` yang lebih sederhana |
| Kolom `id_kampus` di semua tabel | Single institution |
| Kolom `biaya_pendaftaran`, `biaya_kuliah` di `prodi` | Tidak relevan |
| Kolom `jalur_masuk` di `pendaftar` | Tidak ada leads/walk-in |
| Controller `AdminPt/*` | Diganti `Admin/*` dengan scope terbatas |
| Controller `PublicMarketplace/*` | Tidak ada marketplace |
| Controller `Superadmin/MitraController` | Tidak ada mitra |
| Role `staff` | Tidak dibutuhkan |
| Role `admin_pt` | Diganti `admin` dengan scope terbatas |

### Yang Ditambahkan
| Item | Keterangan |
|---|---|
| Tabel `kamus_sinonim` | Pengganti mk_referensi_ai, lebih sederhana |
| Tabel `audit_logs` | Catat semua aksi penting |
| Model `KamusSinonim` | Baru |
| Model `AuditLog` | Baru |
| Kolom `created_by` di `pendaftar` | Untuk lacak admin yang input |
| Kolom `notif_sent_at` di `pendaftar` | Untuk lacak notifikasi |
| Kolom `file_transkrip_excel_path` di `pendaftar` | Pisah dari PDF |
| Kolom `asal_prodi` di `pendaftar` | Baru |
| Kolom `nim_asal` di `pendaftar` | Baru |
| `ExcelParserService` | Parsing template Excel |
| `FuzzyMatcherService` | Matching berbasis Levenshtein + Jaro-Winkler |
| `SumopodService` | HTTP client ke OpenAI-compatible API |
| `MatchingService` | Orkestrator alur matching |
| `AuditService` | Catat aksi ke audit_logs |
| `NotifikasiService` | Kirim email + WA via Fonnte |
| PHP Enums | `RoleEnum`, `StatusPendaftarEnum` |
| Trait `ApiResponse` | Format response konsisten |

### Yang Direfactor
| Item | Perubahan |
|---|---|
| Primary key semua tabel | `id()` → `uuid('id')->primary()` |
| `EnsureUserRole` middleware | Rename → `EnsureRole`, logika diperbarui |
| Routes `admin-pt/*` | Diganti `admin/*` |
| `LoginController` | Sesuaikan dengan UUID, hapus `id_kampus` |
| `ValidasiController` | Hapus bulk-process jalur_masuk, sesuaikan logika |
| `AntreanController` | Sesuaikan dengan alur Excel baru |
| Semua Models | Hapus `id_kampus`, tambah UUID, tambah relasi baru |

---

## 3. Role & Hak Akses

### superadmin
- CRUD semua user (semua role)
- CRUD prodi UNSIA
- CRUD kamus sinonim
- Konfigurasi sistem: threshold fuzzy, Sumopod key, SMTP, Fonnte key
- Audit log
- Laporan & statistik global

### admin
- Input data mahasiswa + upload Excel transkrip
- Upload PDF transkrip asli (arsip saja, tidak diproses sistem)
- Download template Excel
- Lihat status permohonan yang dia input

### akademik
- Dashboard antrean (pendaftar status Baru)
- Review data hasil parsing Excel
- Trigger proses matching
- CRUD mata kuliah kurikulum (semua prodi)
- CRUD kamus sinonim

### kaprodi
- Lihat hasil konversi untuk prodi yang dia pimpin
- Override mapping manual per mata kuliah
- Approve / Revisi / Reject permohonan
- Upload & hapus tanda tangan digital
- Laporan konversi prodinya

---

## 4. Prodi UNSIA (Seed Data)

| Nama Prodi | Jenjang |
|---|---|
| PJJ Informatika | S1 |
| PJJ Sistem Informasi | S1 |
| PJJ Manajemen | S1 |
| PJJ Akuntansi | S1 |
| PJJ Komunikasi | S1 |
| PJJ Teknologi Informasi | S1 |

---

## 5. Skema Database Final

### users
```sql
id                UUID PRIMARY KEY
nama_lengkap      VARCHAR(100)
email             VARCHAR(100) UNIQUE
no_whatsapp       VARCHAR(20) NULLABLE
password_hash     VARCHAR
role              ENUM('superadmin','admin','akademik','kaprodi')
avatar_path       VARCHAR NULLABLE
tanda_tangan_path VARCHAR NULLABLE
status            ENUM('active','inactive') DEFAULT 'active'
last_login        TIMESTAMP NULLABLE
created_at        TIMESTAMP
```

### prodi
```sql
id           UUID PRIMARY KEY
id_kaprodi   UUID NULLABLE FK(users) ON DELETE SET NULL
kode_prodi   VARCHAR(20) NULLABLE
nama_prodi   VARCHAR(100)
jenjang      ENUM('D3','D4','S1','S2') DEFAULT 'S1'
created_at   TIMESTAMP
```

### pendaftar
```sql
id                        UUID PRIMARY KEY
id_prodi                  UUID FK(prodi) ON DELETE CASCADE
created_by                UUID FK(users) ON DELETE SET NULL
nama_lengkap              VARCHAR(150)
nim_asal                  VARCHAR(50) NULLABLE
email                     VARCHAR(100) NULLABLE
no_whatsapp               VARCHAR(20) NULLABLE
asal_kampus               VARCHAR(150) NULLABLE
asal_prodi                VARCHAR(150) NULLABLE
file_transkrip_excel_path VARCHAR NULLABLE
file_transkrip_pdf_path   VARCHAR NULLABLE
status                    ENUM('Baru','AI Processing','Review Akademik',
                               'Pending Kaprodi','Revisi','Approved','Rejected')
                          DEFAULT 'Baru'
total_sks_diakui          INTEGER DEFAULT 0
catatan_revisi            TEXT NULLABLE
hash_ba_digital           VARCHAR(100) NULLABLE
notif_sent_at             TIMESTAMP NULLABLE
created_at                TIMESTAMP

INDEX (id_prodi, status)
```

### kurikulum_mk
```sql
id                UUID PRIMARY KEY
id_prodi          UUID FK(prodi) ON DELETE CASCADE
kode_mk           VARCHAR(20) NULLABLE
nama_mk           VARCHAR(150)
deskripsi_singkat VARCHAR NULLABLE
sks               INTEGER
semester          INTEGER DEFAULT 1
tipe_mk           ENUM('Wajib','Pilihan') DEFAULT 'Wajib'
is_locked         BOOLEAN DEFAULT false

INDEX (id_prodi, semester)
```

### transkrip_asal
```sql
id               UUID PRIMARY KEY
id_pendaftar     UUID FK(pendaftar) ON DELETE CASCADE
nama_mk_asal     VARCHAR(150)
sks_asal         INTEGER DEFAULT 0
nilai_huruf_asal VARCHAR(5) NULLABLE
nilai_angka_asal DECIMAL(5,2) NULLABLE

INDEX (id_pendaftar)
```

### hasil_konversi
```sql
id                UUID PRIMARY KEY
id_pendaftar      UUID FK(pendaftar) ON DELETE CASCADE
id_mk_tujuan      UUID FK(kurikulum_mk) ON DELETE CASCADE
id_transkrip_asal UUID NULLABLE FK(transkrip_asal) ON DELETE SET NULL
nilai_akhir_huruf VARCHAR(5)
sks_diakui        INTEGER
metode_pemetaan   ENUM('Fuzzy','Sumopod','Manual Kaprodi')
match_score       DECIMAL(5,2) NULLABLE
match_reason      TEXT NULLABLE

INDEX (id_pendaftar, id_mk_tujuan)
INDEX (match_score)
```

### kamus_sinonim
```sql
id          UUID PRIMARY KEY
kata_utama  VARCHAR(200)
sinonim     VARCHAR(200)
keterangan  VARCHAR(255) NULLABLE
is_active   BOOLEAN DEFAULT true
created_by  UUID NULLABLE FK(users) ON DELETE SET NULL
created_at  TIMESTAMP
updated_at  TIMESTAMP

UNIQUE (kata_utama, sinonim)
INDEX (sinonim)
```

### pengaturan_global
```sql
setting_key    VARCHAR(50) PRIMARY KEY
setting_value  TEXT NULLABLE
updated_at     TIMESTAMP
```

Key wajib:
| setting_key | Contoh nilai | Dienkripsi? |
|---|---|---|
| nama_institusi | Universitas Siber Asia | Tidak |
| fuzzy_threshold_auto | 80 | Tidak |
| fuzzy_threshold_sumopod | 50 | Tidak |
| sumopod_api_key | sk-xxx | **Ya** |
| sumopod_model | gpt-4o-mini | Tidak |
| sumopod_base_url | https://api.sumopod.com/v1 | Tidak |
| smtp_host | smtp.gmail.com | Tidak |
| smtp_port | 587 | Tidak |
| smtp_username | noreply@unsia.ac.id | Tidak |
| smtp_password | xxx | **Ya** |
| smtp_from_name | KonverPro UNSIA | Tidak |
| fonnte_api_key | xxx | **Ya** |
| notif_email_aktif | true | Tidak |
| notif_wa_aktif | true | Tidak |

### audit_logs
```sql
id           UUID PRIMARY KEY
id_user      UUID NULLABLE FK(users) ON DELETE SET NULL
action       VARCHAR(100)
subject_type VARCHAR(50) NULLABLE
subject_id   VARCHAR(36) NULLABLE
details      TEXT NULLABLE
ip_address   VARCHAR(45) NULLABLE
created_at   TIMESTAMP

INDEX (id_user)
INDEX (action)
```

### pengaturan_prodi
```sql
id_prodi                UUID PRIMARY KEY FK(prodi) ON DELETE CASCADE
min_nilai_huruf         VARCHAR(2) DEFAULT 'C'
max_konversi_sks_persen INTEGER DEFAULT 70
format_no_ba            VARCHAR(100) DEFAULT 'BA/{YEAR}/{NO}/{PRODI}'
metode_pengakuan        ENUM('direct','scale') DEFAULT 'direct'
```

---

## 6. Alur Konversi End-to-End

```
[ADMIN]
  1. Download template Excel dari sistem
  2. Isi data mahasiswa + transkrip (bisa banyak mahasiswa per file)
  3. Upload Excel + PDF arsip opsional
  4. Sistem parsing → buat record pendaftar + transkrip_asal
  5. Status otomatis: "Baru"

[AKADEMIK]
  1. Lihat dashboard antrean (status "Baru")
  2. Review data hasil parsing, koreksi jika perlu
  3. Pastikan prodi tujuan sudah tepat
  4. Klik "Proses Matching" → status: "AI Processing"

[SISTEM — MatchingService]
  Untuk setiap baris transkrip_asal:

  a. Normalisasi: lowercase, trim, cek kamus_sinonim (sinonim → kata_utama)
  b. Fuzzy matching vs semua kurikulum_mk prodi tujuan (Levenshtein + Jaro-Winkler)
  c. Skor >= 80% → simpan (metode: Fuzzy)
  d. Skor 50-79% → kirim ke Sumopod → ya: simpan (metode: Sumopod) / tidak: unmatched
  e. Skor < 50% atau unmatched → tandai perlu review manual
  Selesai → status: "Pending Kaprodi"
  Error Sumopod → fallback ke manual, tidak throw exception

[KAPRODI]
  1. Lihat daftar validasi prodinya
  2. Review tabel MK asal ↔ MK tujuan + skor + metode + alasan
  3. Override manual jika diperlukan
  4. Approve → hitung total SKS → generate hash BA → status: "Approved"
     → notifikasi email + WA ke mahasiswa
  5. Revisi → isi catatan → status: "Revisi" → notif ke admin
  6. Reject → isi alasan → status: "Rejected" → notif ke mahasiswa
```

---

## 7. Template Excel

**Sheet 1: Data Mahasiswa**
| Kolom | Wajib | Keterangan |
|---|---|---|
| nim_asal | Ya | |
| nama_lengkap | Ya | |
| asal_kampus | Ya | |
| asal_prodi | Ya | |
| prodi_tujuan | Ya | Harus persis sesuai nama prodi di sistem |
| email | Tidak | Untuk notifikasi |
| no_whatsapp | Tidak | Format: 628xxx |

**Sheet 2: Transkrip**
| Kolom | Wajib | Keterangan |
|---|---|---|
| nim_asal | Ya | Kunci relasi ke Sheet 1 |
| nama_mk_asal | Ya | |
| sks_asal | Ya | |
| nilai_huruf_asal | Ya | A / B+ / B / C+ / C / D / E |
| nilai_angka_asal | Tidak | Skala 0–100 atau 0–4 |

---

## 8. Notifikasi

| Trigger | Penerima | Kanal |
|---|---|---|
| Status → Approved | Mahasiswa | Email + WA |
| Status → Rejected | Mahasiswa | Email + WA |
| Status → Revisi | Admin (yang input) | Email |

Kondisi: Email hanya dikirim jika kolom `email` terisi. WA hanya dikirim jika `no_whatsapp` terisi dan `notif_wa_aktif = true`.

---

## 9. Struktur API

Format response konsisten:
```json
{ "success": true, "message": "...", "data": {}, "meta": { "page": 1, "total": 50 } }
```

### Auth
```
POST  /api/auth/login
GET   /api/auth/me
POST  /api/auth/logout
```

### Superadmin
```
GET/POST/PUT/DELETE  /api/superadmin/users/{id?}
GET/POST/PUT/DELETE  /api/superadmin/prodi/{id?}
GET/POST/PUT/DELETE  /api/superadmin/kamus-sinonim/{id?}
GET/PUT              /api/superadmin/config
GET                  /api/superadmin/audit
GET                  /api/superadmin/laporan
GET                  /api/superadmin/dashboard
```

### Admin
```
GET   /api/admin/dashboard
GET   /api/admin/template-excel
POST  /api/admin/pendaftar
GET   /api/admin/pendaftar
GET   /api/admin/pendaftar/{id}
```

### Akademik
```
GET    /api/akademik/dashboard
GET    /api/akademik/antrean
GET    /api/akademik/antrean/{id}
POST   /api/akademik/antrean/{id}/proses
GET/POST/PUT/DELETE  /api/akademik/kurikulum/{id?}
GET/POST/PUT/DELETE  /api/akademik/kamus-sinonim/{id?}
```

### Kaprodi
```
GET    /api/kaprodi/dashboard
GET    /api/kaprodi/validasi
GET    /api/kaprodi/validasi/{id}
PUT    /api/kaprodi/validasi/{id}
GET    /api/kaprodi/laporan
GET/POST/DELETE  /api/kaprodi/tanda-tangan
```

---

## 10. Struktur Folder Backend (Laravel)

```
app/
├── Enums/
│   ├── RoleEnum.php
│   └── StatusPendaftarEnum.php
├── Http/
│   ├── Controllers/Api/
│   │   ├── Auth/LoginController.php
│   │   ├── Superadmin/{Dashboard,User,Prodi,KamusSinonim,Config,Laporan,Audit}Controller.php
│   │   ├── Admin/{Dashboard,Pendaftar}Controller.php
│   │   ├── Akademik/{Dashboard,Antrean,Kurikulum,KamusSinonim}Controller.php
│   │   └── Kaprodi/{Dashboard,Validasi,Laporan,TandaTangan}Controller.php
│   ├── Middleware/
│   │   ├── EnsureRole.php
│   │   └── ApplySecurityHeaders.php
│   └── Requests/
│       ├── StorePendaftarRequest.php
│       ├── ProcessValidasiRequest.php
│       └── StoreKamusSinonimRequest.php
├── Models/
│   ├── User.php, Prodi.php, Pendaftar.php
│   ├── KurikulumMk.php, TranskripAsal.php, HasilKonversi.php
│   ├── KamusSinonim.php, PengaturanGlobal.php
│   ├── PengaturanProdi.php, AuditLog.php
├── Services/
│   ├── MatchingService.php
│   ├── FuzzyMatcherService.php
│   ├── SumopodService.php
│   ├── ExcelParserService.php
│   ├── NotifikasiService.php
│   └── AuditService.php
└── Traits/
    └── ApiResponse.php
```

---

## 11. Design System Frontend

### 11.1 Filosofi Desain

**Minimalis, bukan kosong.** Setiap elemen hadir karena fungsinya, bukan dekorasi. Whitespace digunakan secara strategis untuk memberi napas dan kejelasan hierarki.

**Konsisten di semua role.** Sidebar, navbar, card, table, dan badge menggunakan komponen yang sama. Perbedaan antar role hanya pada menu dan data, bukan pada gaya visual.

**Mobile-first tapi tidak mengorbankan desktop.** Layout responsif dengan bottom navigation di mobile dan sidebar di desktop.

---

### 11.2 Color Palette

```css
:root {
  /* Primary — Biru institusi */
  --blue-900: #031f37;   /* navbar, sidebar, CTA utama */
  --blue-700: #094E8B;   /* hover state, gradient */
  --blue-100: #dbeafe;   /* background badge biru muda */
  --blue-50:  #eff6ff;   /* background highlight */

  /* Accent — Kuning energik */
  --yellow:   #FDD824;   /* highlight aktif, badge penting, aksen */
  --yellow-bg:#fffbeb;   /* background kuning sangat muda */

  /* Neutral — Putih & Abu */
  --white:    #ffffff;   /* background card, form */
  --gray-50:  #F8FAFC;   /* background halaman */
  --gray-100: #f1f5f9;   /* border lembut, divider */
  --gray-200: #e2e8f0;   /* border card */
  --gray-400: #94a3b8;   /* label, placeholder */
  --gray-600: #475569;   /* teks sekunder */
  --gray-900: #0f172a;   /* teks utama */

  /* Status */
  --green:    #16a34a;   /* Approved */
  --red:      #dc2626;   /* Rejected */
  --orange:   #ea580c;   /* Revisi, pending */
  --purple:   #7c3aed;   /* AI Processing */
}
```

**Aturan penggunaan warna:**
- `--blue-900` → elemen aktif/utama: navbar, tombol primer, sidebar aktif
- `--yellow` → aksen: indikator aktif di nav, badge status penting, hover CTA
- `--white` → semua card, form, modal
- `--gray-50` → background halaman
- Jangan campur warna kuning dengan teks putih (kontras buruk)

---

### 11.3 Tipografi

```css
/* Font sudah ada di repo: Geist Sans */
font-family: var(--font-geist-sans), ui-sans-serif, system-ui, sans-serif;

/* Scale */
--text-xs:   0.75rem;    /* 12px — label, badge */
--text-sm:   0.875rem;   /* 14px — teks normal */
--text-base: 1rem;       /* 16px — body */
--text-lg:   1.125rem;   /* 18px — subheading */
--text-xl:   1.25rem;    /* 20px — heading section */
--text-2xl:  1.5rem;     /* 24px — heading halaman */
--text-3xl:  1.875rem;   /* 30px — angka statistik */
```

**Aturan tipografi:**
- Heading halaman: `text-2xl font-bold text-gray-900`
- Label form: `text-xs font-semibold text-gray-500 uppercase tracking-wide`
- Nilai statistik: `text-3xl font-bold text-gray-900`
- Teks body: `text-sm text-gray-600`
- Hindari `font-black` yang berlebihan — cukup `font-bold` atau `font-semibold`

---

### 11.4 Komponen UI

#### Navbar (Top, fixed)
```
Background  : --blue-900 (solid, bukan opacity)
Height      : 64px
Konten      : Logo + nama sistem | Nav links (desktop) | User info + logout
Mobile      : Logo + hamburger / avatar
Border      : border-b border-white/10
Shadow      : shadow-lg
```

#### Sidebar (Desktop only, untuk halaman dengan banyak sub-menu)
```
Width       : 240px
Background  : white
Border      : border-r border-gray-100
Item aktif  : bg-blue-50, text-blue-900, border-l-2 border-blue-900
Item normal : text-gray-500, hover:bg-gray-50
```
> Catatan: Di repo saat ini menggunakan nav di atas. Pertahankan pattern ini (top nav), lebih simpel.

#### Bottom Navigation (Mobile)
```
Background  : white
Border      : border-t border-gray-100
Height      : 64px (+ safe area)
Icon aktif  : text-blue-900, dengan dot kuning di bawah
Icon normal : text-gray-400
```

#### Card
```css
.card {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: 16px;        /* rounded-2xl */
  padding: 20px;              /* p-5 */
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.08);
  transform: translateY(-1px);
}
```

#### Stat Card
```
Layout  : icon kiri, angka + label kanan
Border  : border-l-4 dengan warna status (biru, kuning, hijau, merah)
Angka   : text-3xl font-bold
Label   : text-xs text-gray-400 uppercase
```

#### Tombol
```css
/* Primer */
.btn-primary {
  background: var(--blue-900);
  color: white;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
}
.btn-primary:hover { background: var(--blue-700); }

/* Sekunder */
.btn-secondary {
  background: white;
  border: 1px solid var(--gray-200);
  color: var(--gray-700);
}

/* Danger */
.btn-danger {
  background: white;
  border: 1px solid #fca5a5;
  color: var(--red);
}
.btn-danger:hover { background: #fef2f2; }
```

#### Input Form
```css
.input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--gray-200);
  border-radius: 10px;
  font-size: 14px;
  background: var(--gray-50);
}
.input:focus {
  outline: none;
  border-color: var(--blue-700);
  background: white;
  box-shadow: 0 0 0 3px rgba(9, 78, 139, 0.1);
}
```

#### Badge Status
```
Approved        : bg-green-50   text-green-700   border-green-200
Rejected        : bg-red-50     text-red-700     border-red-200
Revisi          : bg-orange-50  text-orange-700  border-orange-200
Pending Kaprodi : bg-blue-50    text-blue-700    border-blue-200  (pulse)
AI Processing   : bg-purple-50  text-purple-700  border-purple-200 (pulse)
Baru            : bg-gray-100   text-gray-600    border-gray-200
```

#### Table
```
Header    : bg-gray-50, text-xs uppercase text-gray-400, border-b
Row       : border-b border-gray-50, hover:bg-gray-50
Cell      : text-sm text-gray-700
Rounded   : rounded-2xl overflow-hidden, border border-gray-100
```

#### Hero Section (di atas setiap halaman utama)
```
Background  : --blue-900 gradient ke --blue-700
Text        : white
Padding     : 24px
Height      : auto (tidak perlu fixed)
Konten      : Nama halaman + deskripsi singkat
Dekorasi    : Subtle — 1 lingkaran blur kuning di sudut, tipis
```
> Sederhanakan dari versi repo saat ini. Hilangkan clock realtime, terlalu ramai.

---

### 11.5 Layout Per Halaman

#### Halaman Login
```
Background  : --blue-900
Card        : 2 kolom (kiri: branding, kanan: form)
             Di mobile: hanya form
Branding    : Logo + nama sistem + tagline
Form        : Email, Password, tombol masuk
Tidak ada   : "Lupa password", registrasi, social login
```

#### Dashboard (semua role)
```
Layout      : Hero section (judul) → Stat cards → Konten utama
Stat cards  : Grid 2 kolom (mobile) / 4 kolom (desktop)
Konten      : Tabel antrean / aktivitas terbaru
```

#### Halaman List (pendaftar, kurikulum, users, dll)
```
Layout      : Header (judul + tombol tambah/filter) → Tabel/Card list
Filter      : Search input + dropdown status (simpel, 1 baris)
Kosong      : Ilustrasi sederhana + teks "Belum ada data"
Pagination  : Simpel (Prev / 1 2 3 / Next)
```

#### Halaman Detail Validasi (Kaprodi)
```
Layout      : Info mahasiswa → Tabel matching (2 kolom) → Tombol keputusan
Tabel       : MK Asal | SKS | MK Tujuan | Skor | Metode | Override
Override    : Dropdown inline di baris tabel (bukan modal terpisah)
Tombol      : Approve (hijau) | Revisi (oranye) | Reject (merah)
```

---

### 11.6 Struktur Folder Frontend (Next.js)

```
src/
├── app/
│   ├── layout.tsx                     -- root layout, font
│   ├── globals.css                    -- CSS variables + base styles
│   ├── page.tsx                       -- redirect ke /login
│   ├── login/
│   │   └── page.tsx
│   ├── superadmin/
│   │   ├── layout.tsx                 -- layout dengan top nav + sidebar
│   │   ├── page.tsx                   -- dashboard
│   │   ├── users/page.tsx
│   │   ├── prodi/page.tsx
│   │   ├── kamus-sinonim/page.tsx
│   │   ├── config/page.tsx
│   │   ├── audit/page.tsx
│   │   └── laporan/page.tsx
│   ├── admin/
│   │   ├── layout.tsx
│   │   ├── page.tsx                   -- dashboard
│   │   ├── pendaftar/page.tsx         -- list
│   │   └── pendaftar/upload/page.tsx  -- form upload Excel
│   ├── akademik/
│   │   ├── layout.tsx
│   │   ├── page.tsx                   -- dashboard
│   │   ├── antrean/page.tsx
│   │   ├── antrean/[id]/page.tsx      -- detail + trigger matching
│   │   ├── kurikulum/page.tsx
│   │   └── kamus-sinonim/page.tsx
│   └── kaprodi/
│       ├── layout.tsx
│       ├── page.tsx                   -- dashboard
│       ├── validasi/page.tsx
│       ├── validasi/[id]/page.tsx     -- detail + override + keputusan
│       ├── validasi/[id]/print/page.tsx
│       ├── laporan/page.tsx
│       └── tanda-tangan/page.tsx
├── components/
│   ├── ui/
│   │   ├── Button.tsx
│   │   ├── Input.tsx
│   │   ├── Badge.tsx
│   │   ├── Card.tsx
│   │   ├── Table.tsx
│   │   ├── Modal.tsx
│   │   ├── Skeleton.tsx
│   │   └── Spinner.tsx
│   ├── layout/
│   │   ├── TopNav.tsx                 -- navbar atas (shared, per-role props)
│   │   ├── BottomNav.tsx              -- bottom nav mobile
│   │   └── PageHeader.tsx             -- hero section per halaman (simplified)
│   └── shared/
│       ├── StatusBadge.tsx
│       ├── StatCard.tsx
│       ├── DataTable.tsx
│       ├── EmptyState.tsx
│       └── ConfirmDialog.tsx
├── lib/
│   ├── api.ts                         -- Axios instance + interceptor
│   ├── auth.ts                        -- auth helper (getUser, getToken, logout)
│   ├── hooks/
│   │   ├── useAuthGuard.ts
│   │   └── useUser.ts
│   └── types.ts                       -- TypeScript interfaces
└── public/
    └── logo.svg
```

---

### 11.7 Perubahan UI dari Repo Saat Ini

| Elemen | Sekarang (repo) | Target (v4) |
|---|---|---|
| Font weight heading | `font-black` terlalu berat | `font-bold` atau `font-semibold` |
| Hero section | Ada clock realtime, terlalu ramai | Cukup judul + deskripsi |
| Uppercase teks | Terlalu banyak ALL CAPS | Hanya untuk label, badge, nav item |
| Token storage | localStorage | httpOnly cookie (via Next.js middleware) |
| Tracking letter | `tracking-[0.28em]` ekstrem | `tracking-wide` atau `tracking-wider` |
| Warna border card | `border-l-4` berbeda per stat | Pertahankan — bagus dan informatif |
| Notif bell | Ada tapi kosong | Tetap ada, connect ke API |
| Bulk approve | Ada di kaprodi | Pertahankan — berguna |
| Jalur masuk badge | Walk-In / Leads | Hapus — tidak relevan |

---

## 12. Urutan Pengerjaan

### Backend
1. Migrasi database baru (hapus tabel lama, buat sesuai schema v4)
2. Enums: `RoleEnum`, `StatusPendaftarEnum`
3. Models + relasi + cast (UUID, hapus id_kampus)
4. Seeder: prodi UNSIA, superadmin default, kamus sinonim awal
5. Middleware `EnsureRole` (refactor dari `EnsureUserRole`)
6. Trait `ApiResponse`
7. Form Request classes
8. Auth: `LoginController` (Sanctum, UUID)
9. `ExcelParserService` + endpoint upload admin
10. `FuzzyMatcherService` + `SumopodService` + `MatchingService`
11. Controllers Superadmin (user, prodi, kamus, config, laporan, audit)
12. Controllers Admin (pendaftar)
13. Controllers Akademik (antrean, kurikulum, kamus)
14. Controllers Kaprodi (validasi, laporan, tanda tangan)
15. `NotifikasiService` (email SMTP + WA Fonnte)
16. `AuditService` + integrasi di semua controller

### Frontend
1. Cleanup: hapus folder `admin-pt`, `admin_pt`, `kurikulum` standalone, `PublicMarketplace`
2. Setup: globals.css (CSS variables baru), Axios instance, auth helper
3. Komponen UI dasar: Button, Input, Badge, Card, Table, Skeleton, Modal
4. Komponen layout: TopNav, BottomNav, PageHeader
5. Halaman login (sudah bagus, minor cleanup)
6. Layout superadmin + semua halaman superadmin
7. Layout admin + upload Excel + list pendaftar
8. Layout akademik + antrean + kurikulum + kamus sinonim
9. Layout kaprodi + validasi + detail validasi + laporan

---

## 13. Catatan Teknis Penting

**Sumopod** kompatibel OpenAI format (`/v1/chat/completions`). Key dibaca dari `pengaturan_global` saat runtime. Jika error → fallback ke manual, tidak throw exception ke user.

**Kamus sinonim** — `FuzzyMatcherService` normalisasi dari kedua sisi (sinonim→kata_utama dan sebaliknya) sebelum membandingkan. Lebih bersih daripada menyimpan dua baris per pasangan.

**File storage** — semua file di `storage/app/private`. Akses via endpoint Laravel dengan middleware auth. Jangan expose path langsung ke client.

**Token storage** — target migrasi dari localStorage ke httpOnly cookie via Next.js middleware untuk keamanan lebih baik dari XSS.

**Threshold fuzzy** — bisa diubah superadmin via UI. Berlaku untuk proses matching berikutnya, tidak retroaktif.

**Notifikasi kondisional** — email hanya jika kolom `email` terisi, WA hanya jika `no_whatsapp` terisi DAN `notif_wa_aktif = true`.
