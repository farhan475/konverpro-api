<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support ALTER ENUM.
        // Recreate the column with the new enum values.
        Schema::table('pendaftar', function (Blueprint $table) {
            $table->string('status_new')->default('Baru')->after('file_transkrip_pdf_path');
        });

        DB::table('pendaftar')->update(['status_new' => DB::raw('status')]);

        Schema::table('pendaftar', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('pendaftar', function (Blueprint $table) {
            $table->enum('status', [
                'Baru',
                'AI Processing',
                'Review Akademik',
                'Pending Kaprodi',
                'Revisi',
                'Approved',
                'Rejected',
            ])->default('Baru')->after('file_transkrip_pdf_path');
        });

        DB::table('pendaftar')->update(['status' => DB::raw('status_new')]);

        Schema::table('pendaftar', function (Blueprint $table) {
            $table->dropColumn('status_new');
        });
    }

    public function down(): void
    {
        // Reverse: remove 'Review Akademik' from enum
        Schema::table('pendaftar', function (Blueprint $table) {
            $table->string('status_new')->default('Baru')->after('file_transkrip_pdf_path');
        });

        // Move 'Review Akademik' entries back to 'Baru'
        DB::table('pendaftar')
            ->where('status', 'Review Akademik')
            ->update(['status_new' => 'Baru']);

        DB::table('pendaftar')
            ->where('status', '!=', 'Review Akademik')
            ->update(['status_new' => DB::raw('status')]);

        Schema::table('pendaftar', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('pendaftar', function (Blueprint $table) {
            $table->enum('status', [
                'Baru',
                'AI Processing',
                'Pending Kaprodi',
                'Revisi',
                'Approved',
                'Rejected',
            ])->default('Baru')->after('file_transkrip_pdf_path');
        });

        DB::table('pendaftar')->update(['status' => DB::raw('status_new')]);

        Schema::table('pendaftar', function (Blueprint $table) {
            $table->dropColumn('status_new');
        });
    }
};
