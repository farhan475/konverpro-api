<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_user')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 150);
            $table->text('message');
            $table->string('action_url')->nullable();
            $table->string('subject_type', 50)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['id_user', 'read_at', 'created_at'], 'internal_notifications_user_read_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_notifications');
    }
};
