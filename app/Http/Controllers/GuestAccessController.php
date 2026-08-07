<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureGuestSession;
use App\Models\GuestSession;
use App\Services\PinService;
use App\Support\EventPhase;
use App\Support\Fingerprint;
use App\Support\GalleryCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class GuestAccessController extends Controller
{
    public function showPinForm(Request $request, PinService $pinService): View|RedirectResponse
    {
        // Une session déjà active saute l'écran du code : zéro friction
        $sessionId = $request->cookie(EnsureGuestSession::COOKIE);

        if (is_string($sessionId) && GuestSession::query()->find($sessionId)?->isActive()) {
            return redirect()->route('tembo.accueil');
        }

        // Accès direct : le QR affiché sur l'écran embarque le code rotatif.
        // Scanner = entrer, sans rien saisir. La saisie manuelle est le repli
        // (QR statique des totems, code lu sur l'écran).
        $code = $request->query('code');

        if (is_string($code) && preg_match('/^\d{4}$/', $code) === 1) {
            $throttleKey = 'pin:'.Fingerprint::ip($request);

            if (! RateLimiter::tooManyAttempts($throttleKey, (int) config('tembo.rate_limits.pin.attempts'))) {
                if ($pinService->verify($code)) {
                    RateLimiter::clear($throttleKey);

                    return $this->openGuestSession($request, $code);
                }

                RateLimiter::hit($throttleKey, (int) config('tembo.rate_limits.pin.decay_minutes') * 60);
            }

            return redirect()->route('tembo.pin')
                ->with('message', 'Ce QR code a expiré. Scannez celui actuellement affiché sur l’écran, ou saisissez le code.');
        }

        return view('tembo.pin');
    }

    public function verifyPin(Request $request, PinService $pinService): RedirectResponse
    {
        // 4 chiffres se brute-forcent en quelques secondes : blocage par IP,
        // avec le délai restant annoncé plutôt qu'un refus muet.
        $throttleKey = 'pin:'.Fingerprint::ip($request);
        $maxAttempts = (int) config('tembo.rate_limits.pin.attempts');

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($throttleKey) / 60));

            return back()->withErrors([
                'code' => "Trop de tentatives. Patientez {$minutes} min, puis réessayez avec le code affiché sur l’écran.",
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:4'],
        ], [
            'code.required' => 'Saisissez le code à 4 chiffres affiché sur l’écran de la salle.',
            'code.digits' => 'Le code comporte exactement 4 chiffres.',
        ]);

        if (! $pinService->verify($validated['code'])) {
            RateLimiter::hit($throttleKey, (int) config('tembo.rate_limits.pin.decay_minutes') * 60);

            return back()->withErrors([
                'code' => 'Ce code n’est pas ou plus valide. Vérifiez le code actuellement affiché sur l’écran de la salle.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        return $this->openGuestSession($request, $validated['code']);
    }

    /** Crée la session invité et pose le cookie signé — QR direct et saisie manuelle confondus. */
    private function openGuestSession(Request $request, string $code): RedirectResponse
    {
        $guestSession = GuestSession::query()->create([
            'device_hash' => Fingerprint::device($request),
            'ip_hash' => Fingerprint::ip($request),
            'pin_used' => $code,
            'expires_at' => $this->sessionExpiry(),
        ]);

        return redirect()
            ->route('tembo.accueil')
            ->withCookie(cookie(
                name: EnsureGuestSession::COOKIE,
                value: $guestSession->id,
                minutes: max(1, (int) now()->diffInMinutes($guestSession->expires_at)),
                secure: app()->isProduction(),
                httpOnly: true,
                sameSite: 'lax',
            ));
    }

    public function home(Request $request): View
    {
        /** @var GuestSession $guestSession */
        $guestSession = $request->attributes->get('guestSession');

        return view('tembo.accueil', [
            'phase' => EventPhase::current(),
            'photo' => $guestSession->photo,
            'vote' => $guestSession->vote?->photo,
            'publishedCount' => count(GalleryCache::photos()),
        ]);
    }

    /**
     * Toutes les sessions expirent à l'heure fixée en config (le 15/08 à
     * 06:00, la soirée du 14 se terminant après minuit). Si cette date est
     * déjà passée (développement, répétition), la session vit 8 heures
     * plutôt que de naître morte.
     */
    private function sessionExpiry(): Carbon
    {
        $configured = Carbon::parse(config('tembo.session_expires_at'), config('app.timezone'));

        return $configured->isFuture() ? $configured : now()->addHours(8);
    }
}
