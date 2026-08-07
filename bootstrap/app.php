<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Dispositif privé : noindex + HTTPS forcé sur toutes les réponses
        $middleware->append(\App\Http\Middleware\SetSecurityHeaders::class);

        $middleware->alias([
            'guest.session' => \App\Http\Middleware\EnsureGuestSession::class,
        ]);

        // Seuls les modérateurs utilisent le guard auth : vers la connexion régie
        $middleware->redirectGuestsTo(fn () => route('regie.connexion'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // L'envoi de photo (XHR) attend des erreurs de validation en JSON :
        // on respecte l'en-tête Accept, pas seulement le préfixe api/*
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*'),
        );
    })->create();
