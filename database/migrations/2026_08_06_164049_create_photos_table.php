<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('guest_session_id')->constrained()->cascadeOnDelete();
            // Contenu utilisateur : modéré au même titre que la photo (24 caractères max)
            $table->string('display_name', 24);
            $table->string('path');
            $table->string('thumb_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('reject_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->boolean('consent_event');
            $table->boolean('consent_reuse')->default(false);
            // Dénormalisé : un COUNT global toutes les 2 s pendant 5 h n'est pas viable.
            // Mis à jour dans la même transaction que chaque vote.
            $table->unsignedInteger('votes_count')->default(0);
            // updated_at sert de verrou anti-collision entre les deux modérateurs
            $table->timestamps();

            // La file de modération et la galerie filtrent par statut puis trient par date
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
