<?php

namespace Database\Factories;

use App\Models\AccessToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccessToken>
 */
class AccessTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Un jeton vit deux fenêtres de rotation : 2 jetons valides en glissement
        $lifetimeMinutes = config('tembo.access.rotation_minutes') * config('tembo.access.valid_tokens');

        return [
            'token' => Str::random((int) config('tembo.access.token_length')),
            'valid_from' => now(),
            'valid_until' => now()->addMinutes($lifetimeMinutes),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'valid_from' => now()->subHours(2),
            'valid_until' => now()->subHour(),
        ]);
    }
}
