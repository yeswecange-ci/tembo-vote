<?php

namespace App\Http\Controllers;

use App\Enums\Phase;
use App\Enums\PhotoStatus;
use App\Http\Requests\StorePhotoRequest;
use App\Models\AuditLog;
use App\Models\GuestSession;
use App\Models\Photo;
use App\Services\PhotoService;
use App\Support\EventPhase;
use App\Support\GalleryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PhotoController extends Controller
{
    /**
     * Écran unique du parcours photo : capture si l'invité peut publier,
     * sinon le statut de sa photo ou l'explication de la fermeture.
     */
    public function create(Request $request): View
    {
        /** @var GuestSession $guestSession */
        $guestSession = $request->attributes->get('guestSession');
        $photo = $guestSession->photo;
        $phase = EventPhase::current();

        // Le statut d'une photo envoyée reste consultable dans toutes les phases
        if ($photo !== null && ($photo->status !== PhotoStatus::Rejected || ! $phase->allowsPublishing())) {
            return view('tembo.ma-photo', ['photo' => $photo, 'phase' => $phase]);
        }

        if (! $phase->allowsPublishing()) {
            return view('tembo.publication-fermee', ['phase' => $phase]);
        }

        // Une photo refusée peut être reprise : l'invité garde son unique droit de publication
        return view('tembo.publier', ['photoRefusee' => $photo]);
    }

    public function store(StorePhotoRequest $request, PhotoService $photoService): JsonResponse
    {
        /** @var GuestSession $guestSession */
        $guestSession = $request->attributes->get('guestSession');
        $phase = EventPhase::current();

        if (! $phase->allowsPublishing()) {
            throw ValidationException::withMessages([
                'photo' => $phase === Phase::Setup
                    ? 'La publication n’est pas encore ouverte. Revenez au lancement de la soirée.'
                    : 'La publication est terminée pour cette soirée.',
            ]);
        }

        $existing = $guestSession->photo;

        if ($existing !== null && $existing->status !== PhotoStatus::Rejected) {
            throw ValidationException::withMessages([
                'photo' => 'Vous avez déjà publié votre photo pour cette soirée.',
            ]);
        }

        $photo = $photoService->publish($guestSession, $request->file('photo'), [
            'display_name' => $request->validated('display_name'),
            // Consentement d'affichage donné par l'envoi lui-même : la mention
            // légale est lue au-dessus du bouton, il n'y a plus de case à cocher
            'consent_event' => true,
            // Le consentement de réutilisation n'est plus demandé à l'invité :
            // la colonne reste en base (purge, export) et vaut toujours false.
            'consent_reuse' => false,
        ]);

        session()->flash('photo_envoyee', true);

        return response()->json(['redirect' => route('photos.create')]);
    }

    /**
     * Retrait sur demande : l'invité retire sa propre photo de la galerie,
     * pendant ou après la soirée (obligation du brief). Après l'événement,
     * le personnel peut aussi retirer depuis la régie.
     */
    public function selfRemove(Request $request): RedirectResponse
    {
        /** @var GuestSession $guestSession */
        $guestSession = $request->attributes->get('guestSession');
        $photo = $guestSession->photo;

        if ($photo === null || $photo->status === PhotoStatus::Rejected) {
            return redirect()->route('photos.create');
        }

        $photo->update([
            'status' => PhotoStatus::Rejected,
            'reject_reason' => config('tembo.removal_reason'),
            'moderated_at' => now(),
        ]);

        AuditLog::write('photo.removed', 'invité (retrait sur demande)', 'photo', $photo->id);
        GalleryCache::invalidate();

        session()->flash('photo_retiree', true);

        return redirect()->route('photos.create');
    }

    /**
     * Diffusion des images exclusivement par route signée : le disque est
     * privé, aucune URL devinable, les photos refusées ne fuient jamais.
     */
    public function image(Photo $photo, string $variante): BinaryFileResponse
    {
        $path = $variante === 'plein' ? $photo->path : $photo->thumb_path;

        abort_unless(Storage::exists($path), 404);

        return response()->file(Storage::path($path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
