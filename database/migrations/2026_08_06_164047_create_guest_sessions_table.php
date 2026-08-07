<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Empreintes SHA-256 : jamais d'IP ni de User-Agent en clair en base
            $table->char('device_hash', 64)->index();
            $table->char('ip_hash', 64);
            $table->char('pin_used', 4);
            $table->timestamp('created_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
    }
};
