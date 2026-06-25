<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftar', function (Blueprint $table): void {
            $table->timestamp('ba_wa_sent_at')->nullable()->after('notif_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('pendaftar', function (Blueprint $table): void {
            $table->dropColumn('ba_wa_sent_at');
        });
    }
};
