<?php

namespace Database\Seeders;

use App\Services\AccessTokenService;
use Illuminate\Database\Seeder;

class AccessTokenSeeder extends Seeder
{
    /**
     * Garantit qu'un jeton d'accès est utilisable dès l'installation, sans
     * attendre la première rotation : le service en crée un s'il n'y en a pas.
     */
    public function run(): void
    {
        app(AccessTokenService::class)->current();
    }
}
