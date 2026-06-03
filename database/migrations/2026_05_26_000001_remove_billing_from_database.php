<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Hapus tabel transaksi_saldo
        Schema::dropIfExists('transaksi_saldo');

        // Hapus kolom saldo_aktif dari tabel kampus
        Schema::table('kampus', function (Blueprint $table) {
            $table->dropColumn('saldo_aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan tabel transaksi_saldo
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

        // Kembalikan kolom saldo_aktif
        Schema::table('kampus', function (Blueprint $table) {
            $table->decimal('saldo_aktif', 15, 2)->default(0)->after('tarif_lead_custom');
        });
    }
};
