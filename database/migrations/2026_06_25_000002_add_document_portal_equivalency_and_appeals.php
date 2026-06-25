<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ba_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_pendaftar')->constrained('pendaftar')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('document_number', 100);
            $table->char('document_hash', 64);
            $table->string('status', 20)->default('final');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->uuid('replaced_by_id')->nullable();
            $table->timestamps();

            $table->unique(['id_pendaftar', 'version']);
            $table->index(['status', 'approved_at']);
        });

        Schema::table('ba_documents', function (Blueprint $table): void {
            $table->foreign('replaced_by_id')->references('id')->on('ba_documents')->nullOnDelete();
        });

        Schema::table('pendaftar', function (Blueprint $table): void {
            $table->foreignUuid('current_ba_document_id')->nullable()->after('hash_ba_digital')
                ->constrained('ba_documents')->nullOnDelete();
            $table->text('portal_token')->nullable()->after('current_ba_document_id');
            $table->char('portal_token_hash', 64)->nullable()->unique()->after('portal_token');
        });

        Schema::create('course_equivalencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('asal_kampus', 150);
            $table->string('asal_prodi', 150)->nullable();
            $table->string('nama_mk_asal', 150);
            $table->string('normalized_key', 255);
            $table->foreignUuid('id_mk_tujuan')->constrained('kurikulum_mk')->cascadeOnDelete();
            $table->unsignedInteger('sks_diakui');
            $table->text('alasan')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('usage_count')->default(1);
            $table->foreignUuid('last_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['normalized_key', 'id_mk_tujuan']);
            $table->index(['is_active', 'valid_until']);
        });

        Schema::create('pendaftar_appeals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_pendaftar')->constrained('pendaftar')->cascadeOnDelete();
            $table->text('reason');
            $table->text('additional_information')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->text('resolution_notes')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE hasil_konversi MODIFY metode_pemetaan ENUM('Referensi','Fuzzy','Sumopod','Manual Kaprodi') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE hasil_konversi MODIFY metode_pemetaan ENUM('Fuzzy','Sumopod','Manual Kaprodi') NULL");
        Schema::dropIfExists('pendaftar_appeals');
        Schema::dropIfExists('course_equivalencies');
        Schema::table('pendaftar', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_ba_document_id');
            $table->dropUnique(['portal_token_hash']);
            $table->dropColumn(['portal_token', 'portal_token_hash']);
        });
        Schema::dropIfExists('ba_documents');
    }
};
