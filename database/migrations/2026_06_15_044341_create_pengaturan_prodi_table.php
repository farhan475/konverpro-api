<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_prodi', function (Blueprint $table) {
            $table->uuid('id_prodi')->primary();
            $table->foreign('id_prodi')->references('id')->on('prodi')->cascadeOnDelete();
            $table->string('min_nilai_huruf', 2)->default('C');
            $table->integer('max_konversi_sks_persen')->default(70);
            $table->string('format_no_ba', 100)->default('BA/{YEAR}/{NO}/{PRODI}');
            $table->enum('metode_pengakuan', ['direct', 'scale'])->default('direct');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_prodi');
    }
};
