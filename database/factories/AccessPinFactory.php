<?php

namespace Database\Factories;

use App\Models\AccessPin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessPin>
 */
class AccessPinFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Un PIN vit deux fenêtres de rotation : 2 codes valides en glissement
        $lifetimeMinutes = config('tembo.pin.rotation_minutes') * config('tembo.pin.valid_codes');

        return [
            'code' => str_pad((string) $this->faker->numberBetween(0, 9999), 4, '0', STR_PAD_LEFT),
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
