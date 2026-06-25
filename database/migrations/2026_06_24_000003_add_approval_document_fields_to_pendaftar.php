<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftar', function (Blueprint $table): void {
            $table->string('nomor_ba', 100)->nullable()->unique()->after('catatan_revisi');
            $table->timestamp('approved_at')->nullable()->after('nomor_ba');
        });
    }

    public function down(): void
    {
        Schema::table('pendaftar', function (Blueprint $table): void {
            $table->dropUnique(['nomor_ba']);
            $table->dropColumn(['nomor_ba', 'approved_at']);
        });
    }
};
