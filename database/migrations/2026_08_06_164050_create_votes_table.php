<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            // UNIQUE : garantit un seul vote actif par session (updateOrCreate, jamais d'insertion en doublon)
            $table->foreignUlid('guest_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUlid('photo_id')->constrained()->cascadeOnDelete();
            // Index NON bloquant : sert uniquement à signaler les doublons potentiels
            // dans le back-office. Refuser le vote d'un invité à tort serait pire
            // qu'un vote en trop.
            $table->char('device_hash', 64)->index();
            // updated_at trace le dernier changement de vote, utile à la relecture humaine du Top 5
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
