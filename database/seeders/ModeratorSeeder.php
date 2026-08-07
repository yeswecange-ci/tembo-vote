<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ModeratorSeeder extends Seeder
{
    /**
     * Les 2 seuls comptes du back-office (brief : Christian et Hadassa).
     * Aucune inscription publique n'existe.
     */
    public function run(): void
    {
        $password = config('tembo.moderator_password');

        if (blank($password)) {
            throw new RuntimeException(
                'TEMBO_MODERATOR_PASSWORD est vide : définissez-le dans le .env avant de seeder les modérateurs.'
            );
        }

        $moderators = [
            ['name' => 'Christian', 'email' => 'christian@tembo-vote.app'],
            ['name' => 'Hadassa', 'email' => 'hadassa@tembo-vote.app'],
        ];

        foreach ($moderators as $moderator) {
            User::query()->updateOrCreate(
                ['email' => $moderator['email']],
                [
                    'name' => $moderator['name'],
                    'password' => Hash::make($password),
                ]
            );
        }
    }
}
