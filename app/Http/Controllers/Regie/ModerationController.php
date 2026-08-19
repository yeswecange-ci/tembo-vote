<?php

namespace App\Http\Controllers\Regie;

use App\Enums\PhotoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectPhotoRequest;
use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\Vote;
use App\Support\GalleryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Poste le plus sollicité de la soirée : mobile-first, une photo à la fois,
 * la plus ancienne en premier.
 */
class ModerationController extends Controller
{
    public function queue(): View
    {
        return view('regie.moderation', [
            'photo' => Photo::query()->pending()->oldest()->first(),
            'pendingCount' => Photo::query()->pending()->count(),
            'rejectReasons' => config('tembo.reject_reasons'),
        ]);
    }

    public function published(): View
    {
        return view('regie.publiees', [
            'photos' => Photo::query()->approved()->latest('moderated_at')->get(),
            'pendingCount' => Photo::query()->pending()->count(),
        ]);
    }

    public function approve(Request $request, Photo $photo): RedirectResponse
    {
        $request->validate(['verrou' => ['required', 'string']]);

        // Verrou anti-collision : la mise à jour n'aboutit que si la photo n'a
        // pas bougé depuis son affichage (updated_at en condition, brief Module 3)
        $updated = Photo::query()
            ->whereKey($photo->id)
            ->where('status', PhotoStatus::Pending->value)
            ->where('updated_at', $request->input('verrou'))
            ->update([
                'status' => PhotoStatus::Approved->value,
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return redirect()->route('regie.moderation')
                ->with('collision', 'Cette photo vient d’être traitée par l’autre modérateur. Voici la suivante.');
        }

        AuditLog::write('photo.approved', $request->user()->name, 'photo', $photo->id);

        // La photo entre dans la galerie : le cache du polling est périmé
        GalleryCache::invalidate();

        return redirect()->route('regie.moderation')
            ->with('succes', "Photo de {$photo->display_name} publiée.");
    }

    public function reject(RejectPhotoRequest $request, Photo $photo): RedirectResponse
    {
        $updated = Photo::query()
            ->whereKey($photo->id)
            ->where('status', PhotoStatus::Pending->value)
            ->where('updated_at', $request->validated('verrou'))
            ->update([
                'status' => PhotoStatus::Rejected->value,
                'reject_reason' => $request->validated('reason'),
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return redirect()->route('regie.moderation')
                ->with('collision', 'Cette photo vient d’être traitée par l’autre modérateur. Voici la suivante.');
        }

        AuditLog::write('photo.rejected', $request->user()->name, 'photo', $photo->id, [
            'reason' => $request->validated('reason'),
        ]);

        return redirect()->route('regie.moderation')
            ->with('succes', "Photo de {$photo->display_name} refusée.");
    }

    /**
     * Retrait d'une photo déjà en ligne, à tout moment : obligation du brief,
     * un invité peut demander le retrait pendant la soirée.
     */
    public function remove(Request $request, Photo $photo): RedirectResponse
    {
        $updated = DB::transaction(function () use ($request, $photo): int {
            $updated = Photo::query()
                ->whereKey($photo->id)
                ->where('status', PhotoStatus::Approved->value)
                ->update([
                    'status' => PhotoStatus::Rejected->value,
                    'reject_reason' => config('tembo.removal_reason'),
                    'moderated_by' => $request->user()->id,
                    'moderated_at' => now(),
                    'updated_at' => now(),
                    'votes_count' => 0,
                ]);

            if ($updated > 0) {
                // Les votes partent avec la photo : sinon leurs auteurs gardent
                // un vote mort — ils croient avoir voté — et le total affiché
                // sur le mur LED reste gonflé par des votes sans destinataire.
                Vote::query()->where('photo_id', $photo->id)->delete();
            }

            return $updated;
        });

        if ($updated === 0) {
            return redirect()->route('regie.publiees')
                ->with('collision', 'Cette photo n’était déjà plus en ligne.');
        }

        AuditLog::write('photo.removed', $request->user()->name, 'photo', $photo->id);

        // La photo sort de la galerie : invalidation immédiate du cache
        GalleryCache::invalidate();

        return redirect()->route('regie.publiees')
            ->with('succes', "Photo de {$photo->display_name} retirée de la galerie.");
    }

    /**
     * Polling 5 s de la file : compteur en attente + la photo affichée
     * est-elle toujours à traiter ?
     */
    public function state(Request $request): JsonResponse
    {
        $currentId = $request->query('photo');

        return response()->json([
            'pending' => Photo::query()->pending()->count(),
            'currentStillPending' => is_string($currentId)
                ? Photo::query()->whereKey($currentId)->pending()->exists()
                : null,
        ]);
    }
}
