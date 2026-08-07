<?php

namespace App\Support;

use App\Enums\Phase;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Phase courante de la soirée, stockée dans settings et mise en cache
 * quelques secondes : lue à chaque requête invité et à chaque polling,
 * elle ne change que sur action du régisseur.
 */
class EventPhase
{
    private const CACHE_KEY = 'tembo.phase';

    private const CACHE_SECONDS = 5;

    public static function current(): Phase
    {
        $value = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_SECONDS,
            fn (): string => Setting::getValue('phase', config('tembo.default_phase')),
        );

        return Phase::tryFrom($value) ?? Phase::Setup;
    }

    public static function set(Phase $phase): void
    {
        Setting::setValue('phase', $phase->value);
        Cache::forget(self::CACHE_KEY);
    }
}
