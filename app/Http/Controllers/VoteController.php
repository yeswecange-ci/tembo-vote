<?php

namespace App\Http\Controllers;

use App\Enums\Phase;
use App\Models\GuestSession;
use App\Models\Photo;
use App\Models\Vote;
use App\Support\EventPhase;
use App\Support\Fingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoteController extends Controller
{
    /**
     * Un seul vote actif par session, changeable à volonté : updateOrCreate,
     * jamais d'insertion en doublon (contrainte UNIQUE en base en dernier
     * rempart). Le compteur dénormalisé bouge dans la même transaction.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var GuestSession $guestSession */
        $guestSession = $request->attributes->get('guestSession');
        $phase = EventPhase::current();

        if (! $phase->allowsVoting()) {
            throw ValidationException::withMessages([
                'photo_id' => $phase === Phase::Setup
                    ? 'Le vote n’est pas encore ouvert. Revenez au lancement de la soirée.'
                    : 'Les votes sont clos.',
            ]);
        }

        $validated = $request->validate([
            'photo_id' => ['required', 'string'],
        ], [
            'photo_id.required' => 'Touchez une photo de la galerie pour voter.',
        ]);

        $photo = Photo::query()->approved()->find($validated['photo_id']);

        if ($photo === null) {
            throw ValidationException::withMessages([
                'photo_id' => 'Cette photo n’est plus dans la galerie. Choisissez-en une autre.',
            ]);
        }

        DB::transaction(function () use ($guestSession, $photo, $request): void {
            $vote = Vote::query()
                ->where('guest_session_id', $guestSession->id)
                ->lockForUpdate()
                ->first();

            if ($vote !== null && $vote->photo_id === $photo->id) {
                return; // même photo : rien à changer
            }

            if ($vote !== null) {
                Photo::query()->whereKey($vote->photo_id)->where('votes_count', '>', 0)->decrement('votes_count');
            }

            Vote::query()->updateOrCreate(
                ['guest_session_id' => $guestSession->id],
                ['photo_id' => $photo->id, 'device_hash' => Fingerprint::device($request)],
            );

            Photo::query()->whereKey($photo->id)->increment('votes_count');
        });

        return response()->json(['vote' => $photo->id]);
    }
}
