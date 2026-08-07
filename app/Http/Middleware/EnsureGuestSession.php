<?php

namespace App\Http\Middleware;

use App\Models\GuestSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protège toutes les routes invité : sans session active, retour à la
 * saisie du code avec un message qui dit quoi faire.
 */
class EnsureGuestSession
{
    /** Nom du cookie de session invité (chiffré et signé par le framework). */
    public const COOKIE = 'tembo_session';

    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = $request->cookie(self::COOKIE);

        $guestSession = is_string($sessionId) ? GuestSession::query()->find($sessionId) : null;

        if ($guestSession === null || ! $guestSession->isActive()) {
            // Le polling attend du JSON : un 401 explicite plutôt qu'une
            // redirection HTML que le client ne saurait pas interpréter
            if ($request->expectsJson()) {
                abort(401, 'Session expirée. Rechargez la page et saisissez le code affiché sur l’écran.');
            }

            return redirect()
                ->route('tembo.pin')
                ->withoutCookie(self::COOKIE)
                ->with('message', $guestSession === null
                    ? 'Saisissez le code affiché sur l’écran de la salle pour accéder à la soirée.'
                    : 'Votre session a expiré. Saisissez le code affiché sur l’écran pour revenir.');
        }

        $request->attributes->set('guestSession', $guestSession);

        return $next($request);
    }
}
