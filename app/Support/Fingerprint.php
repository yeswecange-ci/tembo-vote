<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Empreintes anonymisées : jamais d'IP ni de User-Agent en clair en base.
 * L'APP_KEY entre dans le hachage pour empêcher toute corrélation depuis
 * une plage d'IP connue si la base fuyait.
 */
class Fingerprint
{
    public static function device(Request $request): string
    {
        return hash('sha256', $request->ip().'|'.$request->userAgent().'|'.config('app.key'));
    }

    public static function ip(Request $request): string
    {
        return hash('sha256', $request->ip().'|'.config('app.key'));
    }
}
