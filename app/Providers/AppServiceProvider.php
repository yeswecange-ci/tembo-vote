<?php

namespace App\Providers;

use App\Http\Middleware\EnsureGuestSession;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Les URL générées (routes signées comprises) doivent être en https en production
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // 3 envois de photo par minute et par session : au-delà, message clair
        RateLimiter::for('uploads', function (Request $request) {
            $limits = config('tembo.rate_limits.upload');

            return Limit::perMinutes((int) $limits['decay_minutes'], (int) $limits['attempts'])
                ->by('upload:'.($request->cookie(EnsureGuestSession::COOKIE) ?: $request->ip()))
                ->response(fn () => response()->json([
                    'message' => 'Trop d’envois rapprochés. Patientez une minute, puis réessayez.',
                ], 429));
        });

        // 10 votes par minute et par session : changer d'avis reste possible,
        // marteler le bouton non
        RateLimiter::for('votes', function (Request $request) {
            $limits = config('tembo.rate_limits.vote');

            return Limit::perMinutes((int) $limits['decay_minutes'], (int) $limits['attempts'])
                ->by('vote:'.($request->cookie(EnsureGuestSession::COOKIE) ?: $request->ip()))
                ->response(fn () => response()->json([
                    'message' => 'Trop de votes rapprochés. Patientez un instant, votre dernier vote est bien enregistré.',
                ], 429));
        });
    }
}
