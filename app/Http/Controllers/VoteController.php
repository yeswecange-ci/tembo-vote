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
     * Décision client du 20/08/2026 : l'invité vote pour autant de photos qu'il
     * veut, une seule fois par photo, et jamais pour la sienne. Un second appui
     * sur la même photo retire le vote — sur une grille tactile, un appui
     * malencontreux doit pouvoir se défaire. Le compteur dénormalisé bouge dans
     * la même transaction que le vote.
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

        if ($photo->guest_session_id === $guestSession->id) {
            throw ValidationException::withMessages([
                'photo_id' => 'Vous ne pouvez pas voter pour votre propre photo. Choisissez celle d’un autre invité.',
            ]);
        }

        $vote = DB::transaction(function () use ($guestSession, $photo, $request): bool {
            // Verrou sur la session et non sur le vote : la ligne à créer
            // n'existe pas encore, et deux appuis simultanés sur la même photo
            // se présenteraient sinon en même temps devant l'index unique.
            GuestSession::query()->whereKey($guestSession->id)->lockForUpdate()->first();

            $existant = Vote::query()
                ->where('guest_session_id', $guestSession->id)
                ->where('photo_id', $photo->id)
                ->first();

            if ($existant !== null) {
                $existant->delete();
                Photo::query()->whereKey($photo->id)->where('votes_count', '>', 0)->decrement('votes_count');

                return false;
            }

            Vote::query()->create([
                'guest_session_id' => $guestSession->id,
                'photo_id' => $photo->id,
                'device_hash' => Fingerprint::device($request),
            ]);

            Photo::query()->whereKey($photo->id)->increment('votes_count');

            return true;
        });

        return response()->json([
            'photo_id' => $photo->id,
            // true : vote enregistré · false : vote retiré
            'vote' => $vote,
        ]);
    }
}
