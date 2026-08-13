<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le code à 4 chiffres disparaît : plus personne ne le saisit, l'entrée se
 * fait par le seul QR de l'écran. Le jeton devient long et opaque.
 * La table est recréée plutôt qu'altérée : elle ne contient que des jetons
 * qui vivent 10 minutes, il n'y a rien à conserver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('access_pins');

        Schema::create('access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->timestamp('valid_from');
            // La validation cherche les jetons non expirés : index sur la borne haute
            $table->timestamp('valid_until')->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_tokens');

        Schema::create('access_pins', function (Blueprint $table) {
            $table->id();
            $table->char('code', 4);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->index();
            $table->timestamp('created_at');
        });
    }
};
