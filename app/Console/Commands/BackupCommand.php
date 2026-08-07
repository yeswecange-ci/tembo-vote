<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Symfony\Component\Process\Process;

#[Signature('tembo:backup {--keep= : Nombre de sauvegardes conservées (défaut : config tembo.backup_keep)}')]
#[Description('Sauvegarde la base dans storage/app/private/backups avec rotation — planifiée toutes les 10 minutes pendant la soirée')]
class BackupCommand extends Command
{
    public function handle(): int
    {
        $connection = (string) config('database.default');

        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            $this->error("Sauvegarde non prise en charge pour le driver « {$connection} » : la soirée tourne sur MySQL/MariaDB.");

            return self::FAILURE;
        }

        $config = config("database.connections.{$connection}");

        Storage::makeDirectory('backups');
        $fichier = 'backups/tembo-'.now()->format('Ymd-His').'.sql';

        // --result-file : évite les corruptions d'encodage des redirections Windows
        $processus = new Process([
            (string) config('tembo.mysqldump_path'),
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--password='.$config['password'],
            '--single-transaction',
            '--skip-lock-tables',
            '--result-file='.Storage::path($fichier),
            $config['database'],
        ]);
        $processus->setTimeout(120);
        $processus->run();

        if (! $processus->isSuccessful() || ! Storage::exists($fichier) || Storage::size($fichier) === 0) {
            Storage::delete($fichier);
            $this->error('La sauvegarde a échoué : '.trim($processus->getErrorOutput() ?: 'mysqldump introuvable — renseignez TEMBO_MYSQLDUMP_PATH.'));

            return self::FAILURE;
        }

        // Rotation : on ne garde que les N plus récentes
        $keep = max(1, (int) ($this->option('keep') ?: config('tembo.backup_keep')));
        $anciennes = collect(Storage::files('backups'))
            ->filter(fn (string $chemin): bool => str_ends_with($chemin, '.sql'))
            ->sortDesc()
            ->slice($keep);

        foreach ($anciennes as $ancienne) {
            Storage::delete($ancienne);
        }

        $this->info("Sauvegarde écrite : {$fichier} (".Number::fileSize(Storage::size($fichier)).") · rotation : {$keep} conservées, {$anciennes->count()} supprimée(s).");

        return self::SUCCESS;
    }
}
