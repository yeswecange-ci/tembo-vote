<?php

namespace App\Http\Controllers;

use App\Enums\Phase;
use App\Http\Middleware\EnsureGuestSession;
use App\Models\GuestSession;
use App\Services\AccessTokenService;
use App\Support\EventPhase;
use App\Support\Fingerprint;
use App\Support\GalleryCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class GuestAccessController extends Controller
{
    /**
     * Unique porte d'entrée de la soirée : le QR de l'écran porte un jeton
     * rotatif, le scanner ouvre la session et mène droit à l'action du
     * moment. Rien à saisir, jamais.
     */
    public function enter(Request $request, AccessTokenService $accessTokenService): Response|RedirectResponse
    {
        // Une session déjà active n'a rien à rescanner : zéro friction
        $sessionId = $request->cookie(EnsureGuestSession::COOKIE);

        if (is_string($sessionId) && GuestSession::query()->find($sessionId)?->isActive()) {
            return $this->landing();
        }

        $token = $request->query(AccessTokenService::QUERY_PARAM);

        if (is_string($token) && $accessTokenService->verify($token)) {
            return $this->openGuestSession($request, $token);
        }

        // Sans jeton valide : QR périmé (capture d'écran, lien transmis) ou
        // arrivée directe sur l'URL. Le seul recours est de rescanner l'écran.
        return response()->view('tembo.qr-invalide', [
            'message' => session('message') ?? (is_string($token) && $token !== ''
                ? 'Ce QR code n’est plus valide : il change toutes les quelques minutes.'
                : 'Cette page s’ouvre en scannant le QR code affiché sur l’écran de la salle.'),
        ], 403);
    }

    /** Crée la session invité et pose le cookie signé. */
    private function openGuestSession(Request $request, string $token): RedirectResponse
    {
        $guestSession = GuestSession::query()->create([
            'device_hash' => Fingerprint::device($request),
            'ip_hash' => Fingerprint::ip($request),
            'token_used' => $token,
            'expires_at' => $this->sessionExpiry(),
        ]);

        return $this->landing()->withCookie(cookie(
            name: EnsureGuestSession::COOKIE,
            value: $guestSession->id,
            minutes: max(1, (int) now()->diffInMinutes($guestSession->expires_at)),
            secure: app()->isProduction(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    /**
     * Le scan mène directement à ce qui est possible à cet instant — publier
     * pendant la publication, voter pendant le vote. Un écran de moins entre
     * le QR et la photo envoyée. Hors de ces deux phases, l'accueil explique
     * où en est la soirée.
     */
    private function landing(): RedirectResponse
    {
        return redirect()->route(match (EventPhase::current()) {
            Phase::Open => 'photos.create',
            Phase::VoteOnly => 'galerie.index',
            default => 'tembo.accueil',
        });
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
