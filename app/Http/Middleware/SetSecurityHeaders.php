<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dispositif privé : aucune page ne doit être indexée, archivée ou servie
 * en HTTP clair en production.
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
