<?php

namespace Database\Factories;

use App\Models\GuestSession;
use App\Models\Photo;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guest_session_id' => GuestSession::factory(),
            'photo_id' => Photo::factory(),
            'device_hash' => hash('sha256', $this->faker->unique()->uuid()),
        ];
    }
}
