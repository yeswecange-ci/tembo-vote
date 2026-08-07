<?php

namespace Database\Factories;

use App\Enums\PhotoStatus;
use App\Models\GuestSession;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ulid = strtolower((string) Str::ulid());

        return [
            'guest_session_id' => GuestSession::factory(),
            'display_name' => $this->faker->firstName(),
            'path' => "tembo/photos/{$ulid}.jpg",
            'thumb_path' => "tembo/thumbs/{$ulid}.jpg",
            'status' => PhotoStatus::Pending,
            'consent_event' => true,
            'consent_reuse' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PhotoStatus::Approved,
            'moderated_by' => User::factory(),
            'moderated_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'produit absent'): static
    {
        return $this->state(fn (): array => [
            'status' => PhotoStatus::Rejected,
            'reject_reason' => $reason,
            'moderated_by' => User::factory(),
            'moderated_at' => now(),
        ]);
    }
}
