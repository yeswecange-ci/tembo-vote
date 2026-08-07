<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_pins', function (Blueprint $table) {
            $table->id();
            $table->char('code', 4);
            $table->timestamp('valid_from');
            // La validation cherche les codes non expirés : index sur la borne haute
            $table->timestamp('valid_until')->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_pins');
    }
};
