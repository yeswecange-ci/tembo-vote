<?php

namespace App\Services;

use App\Models\AccessPin;

/**
 * PIN rotatif d'accès : un nouveau code toutes les 20 minutes, chaque code
 * vivant 40 minutes — il y a donc toujours 2 codes valides en glissement.
 * L'invité qui scanne pile au moment du changement n'est jamais rejeté.
 */
class PinService
{
    /**
     * Le PIN courant, celui affiché sur le mur LED.
     * Généré à la volée si la rotation est due : aucune dépendance à un cron,
     * c'est trop critique pour la soirée.
     */
    public function current(): AccessPin
    {
        $newest = AccessPin::query()->currentlyValid()->latest('valid_from')->first();

        if ($newest === null || $this->rotationIsDue($newest)) {
            return $this->rotate();
        }

        return $newest;
    }

    /**
     * Crée immédiatement un nouveau code, sans invalider les codes encore
     * dans leur fenêtre de validité.
     */
    public function rotate(): AccessPin
    {
        $lifetimeMinutes = (int) config('tembo.pin.rotation_minutes') * (int) config('tembo.pin.valid_codes');

        // Jamais deux codes valides identiques : un code saisi doit
        // correspondre à une seule fenêtre, sans ambiguïté.
        do {
            $code = str_pad((string) random_int(0, 9999), (int) config('tembo.pin.length'), '0', STR_PAD_LEFT);
        } while (AccessPin::query()->currentlyValid()->where('code', $code)->exists());

        return AccessPin::query()->create([
            'code' => $code,
            'valid_from' => now(),
            'valid_until' => now()->addMinutes($lifetimeMinutes),
        ]);
    }

    /**
     * Un code saisi est accepté s'il correspond à n'importe lequel des codes
     * encore valides (chevauchement compris).
     */
    public function verify(string $code): bool
    {
        // Garantit qu'un code courant existe avant de comparer : sans cela, le
        // premier invité arrivé après une longue inactivité serait rejeté à tort.
        $this->current();

        return AccessPin::query()->currentlyValid()->where('code', $code)->exists();
    }

    private function rotationIsDue(AccessPin $newest): bool
    {
        return $newest->valid_from->addMinutes((int) config('tembo.pin.rotation_minutes'))->isPast();
    }
}
