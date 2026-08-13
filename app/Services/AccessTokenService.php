<?php

namespace App\Services;

use App\Models\AccessToken;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Jeton d'accès rotatif : un nouveau toutes les 5 minutes, chaque jeton
 * vivant 10 minutes — il y a donc toujours 2 jetons valides en glissement.
 * L'invité qui scanne pile au moment du changement n'est jamais rejeté, et
 * un QR photographié puis envoyé hors de la salle ne vaut plus rien au-delà
 * de cette fenêtre.
 */
class AccessTokenService
{
    /** Nom du paramètre d'URL porté par le QR — court pour un QR moins dense, donc lu de plus loin. */
    public const QUERY_PARAM = 't';

    /**
     * Le jeton courant, celui qu'affiche le mur LED.
     * Généré à la volée si la rotation est due : aucune dépendance à un cron,
     * c'est trop critique pour la soirée.
     */
    public function current(): AccessToken
    {
        $newest = AccessToken::query()->currentlyValid()->latest('valid_from')->first();

        if ($newest === null || $this->rotationIsDue($newest)) {
            return $this->rotate();
        }

        return $newest;
    }

    /**
     * Crée immédiatement un nouveau jeton, sans invalider ceux encore
     * dans leur fenêtre de validité.
     */
    public function rotate(): AccessToken
    {
        $lifetimeMinutes = (int) config('tembo.access.rotation_minutes') * (int) config('tembo.access.valid_tokens');

        return AccessToken::query()->create([
            'token' => Str::random((int) config('tembo.access.token_length')),
            'valid_from' => now(),
            'valid_until' => now()->addMinutes($lifetimeMinutes),
        ]);
    }

    /**
     * Un jeton scanné est accepté s'il correspond à n'importe lequel des
     * jetons encore valides (chevauchement compris).
     */
    public function verify(string $token): bool
    {
        // Garantit qu'un jeton courant existe avant de comparer : sans cela, le
        // premier invité arrivé après une longue inactivité serait rejeté à tort.
        $this->current();

        return AccessToken::query()->currentlyValid()->where('token', $token)->exists();
    }

    /**
     * QR d'accès en data URI SVG : aucune requête réseau depuis l'écran, et
     * mise en cache par jeton (un nouveau toutes les 5 minutes).
     */
    public function qrDataUri(AccessToken $accessToken): string
    {
        return Cache::remember('tembo.qr.'.$accessToken->token, 3600, function () use ($accessToken): string {
            $svg = (new Builder(
                writer: new SvgWriter,
                data: route('tembo.entree', [self::QUERY_PARAM => $accessToken->token]),
                // Le QR est lu de loin, parfois de biais, sur un mur LED
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 480,
                margin: 12,
            ))->build();

            return 'data:image/svg+xml;base64,'.base64_encode($svg->getString());
        });
    }

    private function rotationIsDue(AccessToken $newest): bool
    {
        return $newest->valid_from->addMinutes((int) config('tembo.access.rotation_minutes'))->isPast();
    }
}
