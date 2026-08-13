<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La session garde la trace du jeton qui l'a ouverte : si un QR photographié
 * fuite, la régie peut retrouver et révoquer d'un coup toutes les sessions
 * nées de cette fenêtre-là.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_sessions', function (Blueprint $table) {
            $table->renameColumn('pin_used', 'token_used');
        });

        Schema::table('guest_sessions', function (Blueprint $table) {
            $table->string('token_used', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('guest_sessions', function (Blueprint $table) {
            $table->renameColumn('token_used', 'pin_used');
        });

        Schema::table('guest_sessions', function (Blueprint $table) {
            $table->char('pin_used', 4)->change();
        });
    }
};
