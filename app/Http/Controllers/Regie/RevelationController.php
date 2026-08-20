<?php

namespace App\Http\Controllers\Regie;

use App\Enums\Phase;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Vote;
use App\Support\EventPhase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * La vraie protection anti-triche du dispositif : sans QR nominatif, aucun
 * contrôle automatique n'est fiable à 100 %, mais une relecture humaine du
 * Top 5 l'est. Un humain valide le classement avant que le prix ne parte
 * sur scène.
 */
class RevelationController extends Controller
{
    public function show(): View
    {
        $top = $this->topFive();

        // Votes suspects : une même empreinte d'appareil derrière plusieurs
        // sessions invité. Depuis le multi-vote, compter les votes d'une même
        // empreinte ne signale plus rien — c'est devenu le comportement normal.
        // Ce qui reste anormal, c'est un téléphone qui s'est ouvert plusieurs
        // droits de vote. L'index ne bloque jamais : il signale, l'humain juge.
        $duplicatedHashes = Vote::query()
            ->select('device_hash')
            ->groupBy('device_hash')
            ->havingRaw('count(distinct guest_session_id) > 1')
            ->pluck('device_hash');

        $suspectCounts = $duplicatedHashes->isEmpty() ? collect() : Vote::query()
            ->whereIn('photo_id', $top->pluck('id'))
            ->whereIn('device_hash', $duplicatedHashes)
            ->selectRaw('photo_id, count(*) as total')
            ->groupBy('photo_id')
            ->pluck('total', 'photo_id');

        $validatedWinnerId = Setting::getValue('winner_photo_id');
        $validatedAt = Setting::getValue('ranking_validated_at');

        return view('regie.revelation', [
            'phaseCourante' => EventPhase::current(),
            'top' => $top,
            'suspectCounts' => $suspectCounts,
            'pendingCount' => Photo::query()->pending()->count(),
            'validatedWinner' => $validatedWinnerId !== null ? Photo::query()->find($validatedWinnerId) : null,
            'validatedAt' => $validatedAt !== null ? Carbon::parse($validatedAt) : null,
            'validatedBy' => Setting::getValue('ranking_validated_by'),
        ]);
    }

    public function validateRanking(Request $request): RedirectResponse
    {
        if (EventPhase::current() !== Phase::Frozen) {
            return redirect()->route('regie.revelation')
                ->with('erreur', 'Le classement bouge encore. Passez d’abord la soirée en « Votes clos », puis validez.');
        }

        $winner = $this->topFive()->first();

        if ($winner === null) {
            return redirect()->route('regie.revelation')
                ->with('erreur', 'Aucune photo en ligne : il n’y a pas de classement à valider.');
        }

        Setting::setValue('winner_photo_id', $winner->id);
        Setting::setValue('ranking_validated_at', now()->toDateTimeString());
        Setting::setValue('ranking_validated_by', $request->user()->name);

        AuditLog::write('ranking.validated', $request->user()->name, 'photo', $winner->id, [
            'top' => $this->topFive()->map(fn (Photo $photo): array => [
                'photo_id' => $photo->id,
                'nom' => $photo->display_name,
                'votes' => $photo->votes_count,
            ])->all(),
        ]);

        return redirect()->route('regie.revelation')
            ->with('succes', "Classement validé. Gagnant : {$winner->display_name}.");
    }

    public function launchReveal(Request $request): RedirectResponse
    {
        if (Setting::getValue('winner_photo_id') === null) {
            return redirect()->route('regie.revelation')
                ->with('erreur', 'Validez d’abord le classement final : c’est la relecture humaine qui protège la remise du prix.');
        }

        EventPhase::set(Phase::Reveal);

        AuditLog::write('reveal.launched', $request->user()->name, 'photo', Setting::getValue('winner_photo_id'));

        return redirect()->route('regie.revelation')
            ->with('succes', 'Révélation lancée sur l’écran.');
    }

    /**
     * @return Collection<int, Photo>
     */
    private function topFive()
    {
        return Photo::query()
            ->approved()
            ->orderByDesc('votes_count')
            ->oldest()
            ->limit(5)
            ->get();
    }
}
