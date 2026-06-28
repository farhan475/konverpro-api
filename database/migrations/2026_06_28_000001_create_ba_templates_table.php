<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ba_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->text('content_header')->nullable()->comment('HTML for page 1 (kop, identitas, tabel)');
            $table->text('content_footer')->nullable()->comment('HTML for page 2 (pengesahan, QR, hash)');
            $table->string('logo_url', 255)->nullable()->default('/images/logo_unsia.png');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ba_templates');
    }
};
