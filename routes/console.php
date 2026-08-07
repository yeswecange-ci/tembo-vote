<?php

use Illuminate\Support\Facades\Schedule;

/*
| Sauvegarde de la base toutes les 10 minutes pendant la soirée, avec
| rotation. La sauvegarde n'est pas une tâche critique du parcours invité
| (le brief interdit le cron pour les tâches critiques) : si le scheduler
| tombe, la soirée continue — voir RUNBOOK.md.
*/
Schedule::command('tembo:backup')->everyTenMinutes();
