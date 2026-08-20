<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Décision client du 20/08/2026 : un invité vote pour autant de photos qu'il
 * veut, mais une seule fois par photo. L'unicité passe donc de
 * guest_session_id à (guest_session_id, photo_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Le nouvel index d'abord : il commence par guest_session_id, il peut
        // donc porter la clé étrangère que l'ancien index unique portait seul —
        // MySQL refuserait de supprimer celui-ci en premier.
        Schema::table('votes', function (Blueprint $table) {
            $table->unique(['guest_session_id', 'photo_id']);
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropUnique('votes_guest_session_id_unique');
        });
    }

    /**
     * Retour au vote unique, volontairement sans suppression automatique : si
     * des invités ont déjà voté plusieurs fois, l'index unique refuse de se
     * reposer et le rollback échoue. Mieux vaut un échec bruyant que des votes
     * effacés en silence.
     */
    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->unique('guest_session_id');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropUnique('votes_guest_session_id_photo_id_unique');
        });
    }
};
