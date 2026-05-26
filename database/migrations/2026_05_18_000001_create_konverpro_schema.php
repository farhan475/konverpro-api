<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kampus', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kampus', 150);
            $table->string('email_utama', 100);
            $table->string('no_telp', 20)->nullable();
            $table->text('alamat_resmi')->nullable();
            $table->string('rektor_pimpinan', 100)->nullable();
            $table->string('website', 100)->nullable();
            $table->string('logo_path')->nullable();
            $table->enum('paket_layanan', ['Enterprise', 'Premium'])->default('Enterprise');
            $table->boolean('is_official_partner')->default(false);
            $table->decimal('tarif_internal_custom', 10, 2)->nullable();
            $table->decimal('tarif_lead_custom', 10, 2)->nullable();
            $table->decimal('saldo_aktif', 15, 2)->default(0);
            $table->enum('status_akun', ['active', 'pending', 'suspended'])->default('pending');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kampus')->nullable()->constrained('kampus')->cascadeOnDelete();
            $table->string('nama_lengkap', 100);
            $table->string('email', 100)->unique();
            $table->string('no_whatsapp', 20)->nullable();
            $table->string('password_hash');
            $table->enum('role', ['superadmin', 'admin_pt', 'staff', 'akademik', 'kaprodi']);
            $table->string('avatar_path')->nullable();
            $table->string('tanda_tangan_path')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_login')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index('id_kampus');
        });

        Schema::create('prodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kampus')->constrained('kampus')->cascadeOnDelete();
            $table->foreignId('id_kaprodi')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kode_prodi', 20)->nullable();
            $table->string('nama_prodi', 100);
            $table->enum('jenjang', ['D3', 'D4', 'S1', 'S2'])->default('S1');
            $table->decimal('biaya_pendaftaran', 10, 2)->default(0);
            $table->decimal('biaya_kuliah', 15, 2)->default(0);
            $table->string('file_kurikulum_path')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['id_kampus', 'id_kaprodi']);
        });

        Schema::create('pengaturan_global', function (Blueprint $table) {
            $table->string('setting_key', 50)->primary();
            $table->text('setting_value')->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('pengaturan_prodi', function (Blueprint $table) {
            $table->foreignId('id_prodi')->primary()->constrained('prodi')->cascadeOnDelete();
            $table->enum('min_akreditasi_asal', ['Unggul', 'Baik Sekali', 'Baik', 'A', 'B', 'C', 'Tanpa Akreditasi'])->default('B');
            $table->integer('max_usia_ijazah_tahun')->default(7);
            $table->integer('max_konversi_sks_persen')->default(70);
            $table->string('min_nilai_huruf', 2)->default('C');
            $table->decimal('min_ipk', 3, 2)->default(2.50);
            $table->string('format_no_ba', 100)->default('BA/{YEAR}/{NO}/{PRODI}');
            $table->enum('metode_pengakuan', ['direct', 'scale'])->default('direct');
        });

        Schema::create('pendaftar', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->foreignId('id_kampus')->constrained('kampus')->cascadeOnDelete();
            $table->foreignId('id_prodi')->constrained('prodi')->cascadeOnDelete();
            $table->string('nama_lengkap', 150);
            $table->string('email', 100)->nullable();
            $table->string('no_whatsapp', 20)->nullable();
            $table->string('asal_kampus', 150)->nullable();
            $table->string('file_transkrip_path')->nullable();
            $table->enum('jalur_masuk', ['walk_in', 'leads'])->default('walk_in');
            $table->enum('status', ['Baru', 'AI Processing', 'Review Akademik', 'Pending Kaprodi', 'Revisi', 'Approved', 'Rejected'])->default('Baru');
            $table->integer('total_sks_diakui')->default(0);
            $table->text('catatan_revisi')->nullable();
            $table->string('hash_ba_digital', 100)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['id_kampus', 'id_prodi', 'status']);
        });

        Schema::create('kurikulum_mk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_prodi')->constrained('prodi')->cascadeOnDelete();
            $table->string('kode_mk', 20)->nullable();
            $table->string('nama_mk', 150);
            $table->string('deskripsi_singkat')->nullable();
            $table->integer('sks');
            $table->integer('semester')->default(1);
            $table->enum('tipe_mk', ['Wajib', 'Pilihan'])->default('Wajib');
            $table->boolean('is_locked')->default(false);
            $table->index(['id_prodi', 'semester']);
        });

        Schema::create('transkrip_asal', function (Blueprint $table) {
            $table->id();
            $table->string('id_pendaftar', 50);
            $table->string('nama_mk_asal', 150);
            $table->integer('sks_asal')->default(0);
            $table->string('nilai_huruf_asal', 5)->nullable();
            $table->decimal('nilai_angka_asal', 5, 2)->nullable();
            $table->foreign('id_pendaftar')->references('id')->on('pendaftar')->cascadeOnDelete();
            $table->index('id_pendaftar');
        });

        Schema::create('hasil_konversi', function (Blueprint $table) {
            $table->id();
            $table->string('id_pendaftar', 50);
            $table->foreignId('id_mk_tujuan')->constrained('kurikulum_mk')->cascadeOnDelete();
            $table->foreignId('id_transkrip_asal')->nullable()->constrained('transkrip_asal')->nullOnDelete();
            $table->string('nilai_akhir_huruf', 5);
            $table->integer('sks_diakui');
            $table->enum('metode_pemetaan', ['AI', 'Manual', 'Manual Kaprodi'])->default('AI');
            $table->decimal('match_score', 5, 2)->nullable();
            $table->string('match_method', 50)->nullable();
            $table->string('match_reason')->nullable();
            $table->foreign('id_pendaftar')->references('id')->on('pendaftar')->cascadeOnDelete();
            $table->index(['id_pendaftar', 'id_mk_tujuan']);
            $table->index('match_score', 'idx_hasil_konversi_match_score');
        });

        Schema::create('transaksi_saldo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kampus')->constrained('kampus')->cascadeOnDelete();
            $table->enum('jenis_transaksi', ['topup', 'deduction_internal', 'deduction_leads']);
            $table->decimal('nominal', 15, 2);
            $table->string('bukti_transfer')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('catatan_admin')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['id_kampus', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_kampus')->nullable()->constrained('kampus')->cascadeOnDelete();
            $table->string('action', 100);
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['id_user', 'id_kampus']);
        });

        Schema::create('mk_referensi_ai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kurikulum_mk')->constrained('kurikulum_mk')->cascadeOnDelete();
            $table->string('keyword', 150);
            $table->string('keyword_normalized', 150);
            $table->unsignedTinyInteger('weight')->default(80);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id_kurikulum_mk', 'keyword_normalized'], 'uq_mk_referensi_keyword');
            $table->index('keyword_normalized', 'idx_mk_referensi_keyword');
            $table->index('is_active', 'idx_mk_referensi_active');
        });

        Schema::create('notifikasi_templates', function (Blueprint $table) {
            $table->id();
            $table->string('kode_event', 50)->unique();
            $table->string('nama_event', 100)->nullable();
            $table->string('subjek_email', 150)->nullable();
            $table->text('konten_email')->nullable();
            $table->text('konten_wa')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_templates');
        Schema::dropIfExists('mk_referensi_ai');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('transaksi_saldo');
        Schema::dropIfExists('hasil_konversi');
        Schema::dropIfExists('transkrip_asal');
        Schema::dropIfExists('kurikulum_mk');
        Schema::dropIfExists('pendaftar');
        Schema::dropIfExists('pengaturan_prodi');
        Schema::dropIfExists('pengaturan_global');
        Schema::dropIfExists('prodi');
        Schema::dropIfExists('users');
        Schema::dropIfExists('kampus');
    }
};
