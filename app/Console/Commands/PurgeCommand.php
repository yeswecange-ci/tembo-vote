<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\GuestSession;
use App\Models\Photo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('tembo:purge {--jours= : Ancienneté minimale en jours (défaut : config tembo.purge_after_days)} {--force : Exécuter sans demander de confirmation}')]
#[Description('Supprime photos et sessions au-delà de la durée retenue, en conservant les photos avec consentement de réutilisation')]
class PurgeCommand extends Command
{
    public function handle(): int
    {
        $jours = (int) ($this->option('jours') ?: config('tembo.purge_after_days'));
        $limite = now()->subDays($jours);

        // Photos supprimables : trop anciennes ET sans consentement de réutilisation
        $photos = Photo::query()
            ->where('consent_reuse', false)
            ->where('created_at', '<', $limite)
            ->get();

        // Sessions supprimables : expirées avant la limite et sans photo à conserver
        $sessions = GuestSession::query()
            ->where('expires_at', '<', $limite)
            ->whereDoesntHave('photo', function ($query) use ($limite) {
                $query->where(fn ($q) => $q->where('consent_reuse', true)->orWhere('created_at', '>=', $limite));
            })
            ->get();

        $conservees = Photo::query()->where('consent_reuse', true)->count();

        $this->line("Photos à supprimer : {$photos->count()} · Sessions à supprimer : {$sessions->count()} · Photos conservées (consentement) : {$conservees}");

        if ($photos->isEmpty() && $sessions->isEmpty()) {
            $this->info('Rien à purger.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Confirmer la purge ? Cette suppression est définitive.')) {
            $this->line('Purge annulée.');

            return self::SUCCESS;
        }

        foreach ($photos as $photo) {
            Storage::delete([$photo->path, $photo->thumb_path]);
            $photo->delete(); // les votes suivent (cascade)
        }

        // La suppression des sessions emporte leurs votes (cascade) ; leurs
        // photos supprimables l'ont déjà été ci-dessus
        foreach ($sessions as $session) {
            $session->delete();
        }

        AuditLog::write('purge.executed', 'console', meta: [
            'jours' => $jours,
            'photos' => $photos->count(),
            'sessions' => $sessions->count(),
        ]);

        $this->info("Purge terminée : {$photos->count()} photo(s) et {$sessions->count()} session(s) supprimées.");

        return self::SUCCESS;
    }
}
