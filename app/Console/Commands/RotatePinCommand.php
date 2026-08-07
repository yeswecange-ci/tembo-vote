<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\PinService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tembo:rotate-pin')]
#[Description("Génère immédiatement un nouveau code PIN d'accès (les codes encore valides le restent)")]
class RotatePinCommand extends Command
{
    public function handle(PinService $pinService): int
    {
        $pin = $pinService->rotate();

        AuditLog::query()->create([
            'action' => 'pin.rotated',
            'actor' => 'console',
            'target_type' => 'access_pin',
            'target_id' => (string) $pin->id,
        ]);

        $this->info("Nouveau code : {$pin->code}");
        $this->line("Valide de {$pin->valid_from->format('H:i')} à {$pin->valid_until->format('H:i')} ({$pin->valid_until->diffForHumans()}).");

        return self::SUCCESS;
    }
}
