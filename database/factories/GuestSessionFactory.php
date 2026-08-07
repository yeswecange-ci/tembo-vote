<?php

namespace Database\Factories;

use App\Models\GuestSession;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'pin_used' => str_pad((string) $this->faker->numberBetween(0, 9999), 4, '0', STR_PAD_LEFT),
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
