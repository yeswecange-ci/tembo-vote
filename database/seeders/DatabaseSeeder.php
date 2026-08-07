<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ModeratorSeeder::class,
            AccessPinSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
