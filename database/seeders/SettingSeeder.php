<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Initialise la phase de soirée sans jamais écraser une phase déjà
     * choisie : re-seeder en pleine soirée ne doit pas remettre le
     * dispositif en préparation.
     */
    public function run(): void
    {
        if (Setting::getValue('phase') === null) {
            Setting::setValue('phase', config('tembo.default_phase'));
        }
    }
}
