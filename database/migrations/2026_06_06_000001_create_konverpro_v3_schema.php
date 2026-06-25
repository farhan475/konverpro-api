<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Pengaturan Global (Flat key-value)
        Schema::create('pengaturan_global', function (Blueprint $table) {
            $table->string('setting_key', 50)->primary();
            $table->text('setting_value')->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        // 2. Users (UUID, single institution)
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_lengkap', 100);
            $table->string('email', 100)->unique();
            $table->string('no_whatsapp', 20)->nullable();
            $table->string('password');
            $table->enum('role', ['superadmin', 'admin', 'akademik', 'kaprodi']);
            $table->string('avatar_path')->nullable();
            $table->string('tanda_tangan_path')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });

        // 3. Prodi (UUID, Single Institution)
        Schema::create('prodi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_kaprodi')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kode_prodi', 20)->nullable();
            $table->string('nama_prodi', 100);
            $table->enum('jenjang', ['D3', 'D4', 'S1', 'S2'])->default('S1');
            $table->timestamps();
        });

        // 4. Pendaftar (UUID, New Statuses)
        Schema::create('pendaftar', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_prodi')->constrained('prodi')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_lengkap', 150);
            $table->string('nim_asal', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('no_whatsapp', 20)->nullable();
            $table->string('asal_kampus', 150)->nullable();
            $table->string('asal_prodi', 150)->nullable();
            $table->string('file_transkrip_excel_path')->nullable();
            $table->string('file_transkrip_pdf_path')->nullable();
            $table->enum('status', [
                'Baru',
                'AI Processing',
                'Pending Kaprodi',
                'Revisi',
                'Approved',
                'Rejected',
            ])->default('Baru');
            $table->integer('total_sks_diakui')->default(0);
            $table->text('catatan_revisi')->nullable();
            $table->string('hash_ba_digital', 100)->nullable();
            $table->timestamp('notif_sent_at')->nullable();
            $table->timestamps();

            $table->index(['id_prodi', 'status']);
        });

        // 5. Kurikulum MK (UUID)
        Schema::create('kurikulum_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_prodi')->constrained('prodi')->cascadeOnDelete();
            $table->string('kode_mk', 20)->nullable();
            $table->string('nama_mk', 150);
            $table->text('deskripsi_singkat')->nullable();
            $table->integer('sks');
            $table->integer('semester')->default(1);
            $table->enum('tipe_mk', ['Wajib', 'Pilihan'])->default('Wajib');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->index(['id_prodi', 'semester']);
        });

        // 6. Transkrip Asal (UUID)
        Schema::create('transkrip_asal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_pendaftar')->constrained('pendaftar')->cascadeOnDelete();
            $table->string('nama_mk_asal', 150);
            $table->integer('sks_asal')->default(0);
            $table->string('nilai_huruf_asal', 5)->nullable();
            $table->decimal('nilai_angka_asal', 5, 2)->nullable();
            $table->timestamps();

            $table->index('id_pendaftar');
        });

        // 7. Hasil Konversi (UUID, Methods v3)
        Schema::create('hasil_konversi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_pendaftar')->constrained('pendaftar')->cascadeOnDelete();
            $table->foreignUuid('id_mk_tujuan')->nullable()->constrained('kurikulum_mk')->nullOnDelete();
            $table->foreignUuid('id_transkrip_asal')->nullable()->constrained('transkrip_asal')->nullOnDelete();
            $table->string('nilai_akhir_huruf', 5)->nullable();
            $table->integer('sks_diakui')->default(0);
            $table->enum('metode_pemetaan', ['Fuzzy', 'Sumopod', 'Manual Kaprodi'])->nullable();
            $table->decimal('match_score', 5, 2)->nullable();
            $table->text('match_reason')->nullable();
            $table->boolean('is_unmatched')->default(false);
            $table->timestamps();

            $table->index('id_pendaftar');
        });

        // 8. Kamus Sinonim (New Table)
        Schema::create('kamus_sinonim', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kata_utama', 200);
            $table->string('sinonim', 200);
            $table->string('keterangan', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kata_utama', 'sinonim']);
            $table->index('sinonim');
        });

        // 9. Audit Logs (UUID, single institution)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_user')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 50)->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index('id_user');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('kamus_sinonim');
        Schema::dropIfExists('hasil_konversi');
        Schema::dropIfExists('transkrip_asal');
        Schema::dropIfExists('kurikulum_mk');
        Schema::dropIfExists('pendaftar');
        Schema::dropIfExists('prodi');
        Schema::dropIfExists('users');
        Schema::dropIfExists('pengaturan_global');
    }
};
