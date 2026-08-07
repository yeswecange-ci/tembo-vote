<?php

namespace Database\Seeders;

use App\Models\AccessPin;
use Illuminate\Database\Seeder;

class AccessPinSeeder extends Seeder
{
    /**
     * Garantit qu'un PIN est utilisable dès l'installation, sans attendre
     * la première rotation.
     */
    public function run(): void
    {
        if (AccessPin::query()->currentlyValid()->exists()) {
            return;
        }

        $lifetimeMinutes = config('tembo.pin.rotation_minutes') * config('tembo.pin.valid_codes');

        AccessPin::query()->create([
            'code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'valid_from' => now(),
            'valid_until' => now()->addMinutes($lifetimeMinutes),
        ]);
    }
}
