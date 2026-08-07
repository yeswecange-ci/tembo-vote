<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->string('actor');
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            // Hachée et non en clair : aucune donnée personnelle en clair en base
            $table->char('ip_hash', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
