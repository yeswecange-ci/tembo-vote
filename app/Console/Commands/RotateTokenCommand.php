<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AccessTokenService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tembo:rotate-token')]
#[Description("Force la rotation du jeton d'accès (les jetons encore valides le restent) — à lancer si un QR a fuité")]
class RotateTokenCommand extends Command
{
    public function handle(AccessTokenService $accessTokenService): int
    {
        $accessToken = $accessTokenService->rotate();

        AuditLog::query()->create([
            'action' => 'token.rotated',
            'actor' => 'console',
            'target_type' => 'access_token',
            'target_id' => (string) $accessToken->id,
        ]);

        $this->info('Nouveau jeton généré : les écrans afficheront le nouveau QR au prochain rafraîchissement.');
        $this->line("Valide de {$accessToken->valid_from->format('H:i')} à {$accessToken->valid_until->format('H:i')} ({$accessToken->valid_until->diffForHumans()}).");

        return self::SUCCESS;
    }
}
