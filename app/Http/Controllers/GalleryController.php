<?php

namespace App\Http\Controllers;

use App\Models\GuestSession;
use App\Models\Photo;
use App\Support\EventPhase;
use App\Support\GalleryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class GalleryController extends Controller
{
    private const PAGE_SIZE = 30;

    /** Page galerie : l'état initial est rendu côté serveur, Alpine prend le relais. */
    public function page(Request $request): View
    {
        /** @var GuestSession $guestSession */
        $guestSession = $request->attributes->get('guestSession');

        $photos = GalleryCache::photos();
        $initiales = array_reverse(array_slice($photos, -self::PAGE_SIZE));
        $vote = $guestSession->vote;

        return view('tembo.galerie', [
            'phase' => EventPhase::current(),
            'photosInitiales' => $initiales,
            'complet' => count($photos) <= self::PAGE_SIZE,
            'monVote' => $vote?->photo_id,
            // Le nom accompagne l'id : la barre fixe doit pouvoir nommer un
            // vote porté sur une photo absente des 30 premières chargées.
            'monVoteNom' => $vote?->photo?->display_name,
            'maPhotoId' => $guestSession->photo?->id,
        ]);
    }

    /**
     * Polling de la galerie (3 s) et défilement infini, servis depuis le
     * cache. ETag + 304 : le même corps n'est jamais retransmis 6000 fois
     * par heure.
     */
    public function index(Request $request): JsonResponse|Response
    {
        $apres = $request->query('apres');
        $avant = $request->query('avant');
        $tout = $request->boolean('tout');
        $phase = EventPhase::current();

        // La phase entre dans l'ETag : sans elle, la clôture des votes serait
        // masquée par un 304 et les téléphones déjà ouverts continueraient
        // d'inviter au vote.
        $etag = 'W/"galerie-'.GalleryCache::version().'-'.$phase->value.'-'.md5((string) $apres.'|'.(string) $avant.'|'.(int) $tout).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        $photos = GalleryCache::photos();
        $complet = true;

        if ($tout) {
            // Recherche par prénom : le client filtre sur la galerie entière
        } elseif (is_string($apres) && $apres !== '') {
            // Nouvelles photos depuis le curseur (polling)
            $photos = array_values(array_filter($photos, fn (array $photo): bool => strcmp($photo['curseur'], $apres) > 0));
        } elseif (is_string($avant) && $avant !== '') {
            // Page précédente (défilement vers le bas)
            $photos = array_values(array_filter($photos, fn (array $photo): bool => strcmp($photo['curseur'], $avant) < 0));
            $complet = count($photos) <= self::PAGE_SIZE;
            $photos = array_slice($photos, -self::PAGE_SIZE);
        } else {
            $complet = count($photos) <= self::PAGE_SIZE;
            $photos = array_slice($photos, -self::PAGE_SIZE);
        }

        return response()->json([
            // Plus récentes d'abord, comme dans la grille
            'photos' => array_reverse($photos),
            'complet' => $complet,
            // La phase voyage avec la galerie : un téléphone resté ouvert sait
            // en 3 secondes que les votes sont clos, sans recharger la page.
            'peutVoter' => $phase->allowsVoting(),
            // Le polling ne fait pas qu'ajouter : une photo retirée doit quitter
            // les grilles déjà affichées, sans attendre un rechargement.
            'retirees' => GalleryCache::removed(),
        ])->withHeaders([
            'ETag' => $etag,
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Classement invité : le Top 5 dans l'ordre, sans les totaux de votes —
     * les chiffres ne vivent que sur le mur LED (anti effet de meute).
     */
    public function ranking(Request $request): View
    {
        $top = Photo::query()
            ->approved()
            ->orderByDesc('votes_count')
            ->oldest()
            ->limit(5)
            ->get();

        return view('tembo.classement', [
            'top' => $top,
            'phase' => EventPhase::current(),
        ]);
    }
}
