<?php

namespace Database\Factories;

use App\Models\GuestSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuestSession>
 */
class GuestSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_hash' => hash('sha256', $this->faker->unique()->uuid()),
            'ip_hash' => hash('sha256', $this->faker->ipv4()),
            'token_used' => Str::random((int) config('tembo.access.token_length')),
            'expires_at' => now()->addHours(8),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subHour()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
