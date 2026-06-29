<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ba_documents', function (Blueprint $table): void {
            $table->char('signature_hash', 64)->nullable()->after('document_hash');
        });
    }

    public function down(): void
    {
        Schema::table('ba_documents', function (Blueprint $table): void {
            $table->dropColumn('signature_hash');
        });
    }
};
