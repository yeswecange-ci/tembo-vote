<?php

namespace App\Http\Controllers\Regie;

use App\Enums\Phase;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Photo;
use App\Services\AccessTokenService;
use App\Support\EventPhase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pilotage de la soirée : phase en un clic (le régisseur est pressé et dans
 * le noir) et QR d'accès affichable ici même si le mur LED tombe.
 */
class SoireeController extends Controller
{
    public function show(AccessTokenService $accessTokenService): View
    {
        $accessToken = $accessTokenService->current();

        return view('regie.soiree', [
            'phaseCourante' => EventPhase::current(),
            'accessToken' => $accessToken,
            'qr' => $accessTokenService->qrDataUri($accessToken),
            'pendingCount' => Photo::query()->pending()->count(),
            'publishedCount' => Photo::query()->approved()->count(),
        ]);
    }

    public function setPhase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phase' => ['required', Rule::enum(Phase::class)],
        ], [
            'phase.required' => 'Choisissez une phase.',
            'phase.enum' => 'Cette phase n’existe pas.',
        ]);

        $phase = Phase::from($validated['phase']);

        EventPhase::set($phase);

        AuditLog::write('phase.changed', $request->user()->name, 'setting', 'phase', [
            'phase' => $phase->value,
        ]);

        return redirect()->route('regie.soiree')
            ->with('succes', 'Phase de la soirée : '.$phase->label().'.');
    }
}
