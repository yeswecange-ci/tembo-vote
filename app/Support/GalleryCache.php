<?php

namespace App\Support;

use App\Models\Photo;
use Illuminate\Support\Facades\Cache;

/**
 * La galerie est servie depuis le cache et non depuis la base : 100 à 300
 * invités la sondent toutes les 3 secondes pendant 5 heures. Le cache est
 * invalidé à chaque validation ou retrait de photo, et la version sert
 * d'ETag pour répondre 304 sans retransmettre le même corps.
 */
class GalleryCache
{
    private const VERSION_KEY = 'tembo.gallery.version';

    private const LIST_KEY = 'tembo.gallery.photos';

    /** Le TTL reste court devant la durée de vie des URL signées (6 h). */
    private const LIST_TTL_SECONDS = 300;

    public static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn (): int => 1);
    }

    public static function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
        Cache::forget(self::LIST_KEY);
    }

    /**
     * Photos publiées, triées du plus ancien au plus récent — le curseur
     * (created_at + id) est lexicographiquement croissant.
     *
     * @return array<int, array{id: string, nom: string, vignette: string, curseur: string}>
     */
    public static function photos(): array
    {
        return Cache::remember(self::LIST_KEY, self::LIST_TTL_SECONDS, function (): array {
            return Photo::query()
                ->approved()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (Photo $photo): array => [
                    'id' => $photo->id,
                    'nom' => $photo->display_name,
                    'vignette' => $photo->signedImageUrl('vignette'),
                    'curseur' => $photo->created_at->format('Y-m-d H:i:s').'|'.$photo->id,
                ])
                ->all();
        });
    }
}
