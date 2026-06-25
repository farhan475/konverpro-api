<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->unique('nama_prodi', 'prodi_nama_unique');
            $table->unique('kode_prodi', 'prodi_kode_unique');
        });

        Schema::table('kurikulum_mk', function (Blueprint $table) {
            $table->unique(['id_prodi', 'kode_mk'], 'kurikulum_prodi_kode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kurikulum_mk', function (Blueprint $table) {
            $table->dropUnique('kurikulum_prodi_kode_unique');
        });

        Schema::table('prodi', function (Blueprint $table) {
            $table->dropUnique('prodi_nama_unique');
            $table->dropUnique('prodi_kode_unique');
        });
    }
};
