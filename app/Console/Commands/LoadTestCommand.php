<?php

namespace App\Console\Commands;

use App\Enums\Phase;
use App\Models\Photo;
use App\Services\PinService;
use App\Support\EventPhase;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

#[Signature('tembo:test-charge
    {--url= : URL de base (défaut : APP_URL)}
    {--sessions=300 : Nombre de sessions invité simulées}
    {--uploads=30 : Nombre d\'uploads simultanés (pic)}
    {--concurrence=25 : Requêtes envoyées en parallèle par vague}')]
#[Description('Simule la charge de la soirée — connexions, pic d\'uploads, 300 votes, polling — et rapporte les temps mesurés')]
class LoadTestCommand extends Command
{
    /** @var array<string, array<int, float>> */
    private array $durations = [];

    private string $baseUrl = '';

    public function handle(): int
    {
        $this->baseUrl = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $nbSessions = (int) $this->option('sessions');
        $nbUploads = (int) $this->option('uploads');
        $concurrence = max(1, (int) $this->option('concurrence'));

        $this->line("Cible : {$this->baseUrl} · {$nbSessions} sessions · pic de {$nbUploads} uploads · vagues de {$concurrence}");

        if (EventPhase::current() !== Phase::Open) {
            $this->warn('La phase n\'est pas « open » : uploads et votes seraient refusés. Passez la phase en open avant le test.');

            return self::FAILURE;
        }

        $pin = app(PinService::class)->current()->code;

        // ----- 1. Ruée sur le QR : création des sessions -----
        $this->line('1/4 · Connexions (saisie du PIN)…');
        $sessions = [];
        $barre = $this->output->createProgressBar($nbSessions);

        for ($i = 0; $i < $nbSessions; $i++) {
            $session = $this->creerSession($pin);
            if ($session !== null) {
                $sessions[] = $session;
            }
            $barre->advance();
        }
        $barre->finish();
        $this->newLine();

        if (count($sessions) < max(1, (int) ($nbSessions * 0.9))) {
            $this->error(count($sessions).' sessions créées seulement : vérifiez le rate limiting PIN ou le serveur.');
        }

        // ----- 2. Pic d'uploads simultanés -----
        $this->line("2/4 · Pic de {$nbUploads} uploads simultanés…");
        $photoJpeg = $this->fabriquerJpeg();

        foreach (array_chunk(array_slice($sessions, 0, $nbUploads), $concurrence) as $indice => $vague) {
            Http::pool(fn (Pool $pool) => collect($vague)->map(
                fn (array $session, int $i) => $pool
                    ->withOptions($this->optionsMesure('upload'))
                    ->withCookies($session['cookies'], $this->domaine())
                    ->withHeaders(['Accept' => 'application/json'])
                    ->attach('photo', $photoJpeg, 'photo.jpg', ['Content-Type' => 'image/jpeg'])
                    ->post($this->baseUrl.'/tembo/photo', [
                        '_token' => $session['token'],
                        'display_name' => 'Charge '.($indice * 100 + $i),
                        'consent_event' => '1',
                    ])
            )->all());
        }

        // ----- 3. 300 votes (objectif brief : en 2 minutes — envoyés au plus vite) -----
        $this->line('3/4 · Votes ('.count($sessions).' sessions)…');
        $photoIds = Photo::query()->approved()->pluck('id');

        if ($photoIds->isEmpty()) {
            $this->warn('Aucune photo publiée : votes ignorés. Validez les uploads en régie et relancez pour mesurer les votes.');
        } else {
            foreach (array_chunk($sessions, $concurrence) as $vague) {
                Http::pool(fn (Pool $pool) => collect($vague)->map(
                    fn (array $session) => $pool
                        ->withOptions($this->optionsMesure('vote'))
                        ->withCookies($session['cookies'], $this->domaine())
                        ->withHeaders(['Accept' => 'application/json'])
                        ->post($this->baseUrl.'/tembo/vote', [
                            '_token' => $session['token'],
                            'photo_id' => $photoIds->random(),
                        ])
                )->all());
            }
        }

        // ----- 4. Polling continu : une vague complète de /api/galerie -----
        $this->line('4/4 · Polling galerie ('.count($sessions).' clients)…');
        foreach (array_chunk($sessions, $concurrence) as $vague) {
            Http::pool(fn (Pool $pool) => collect($vague)->map(
                fn (array $session) => $pool
                    ->withOptions($this->optionsMesure('polling'))
                    ->withCookies($session['cookies'], $this->domaine())
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get($this->baseUrl.'/api/galerie')
            )->all());
        }

        $this->rapport();

        return self::SUCCESS;
    }

    /**
     * Parcours réel : GET /tembo (jeton CSRF) puis POST du PIN. Tous les
     * cookies (session Laravel comprise) sont conservés : le jeton CSRF
     * n'est valable qu'avec eux.
     *
     * @return array{cookies: array<string, string>, token: string}|null
     */
    private function creerSession(string $pin): ?array
    {
        $jar = new CookieJar;

        $page = Http::withOptions(['cookies' => $jar] + $this->optionsMesure('connexion'))
            ->get($this->baseUrl.'/tembo');

        if (! preg_match('/name="_token" value="([^"]+)"/', $page->body(), $csrf)) {
            return null;
        }

        Http::withOptions(['cookies' => $jar] + $this->optionsMesure('connexion'))
            ->asForm()
            ->post($this->baseUrl.'/tembo', ['_token' => $csrf[1], 'code' => $pin]);

        if ($jar->getCookieByName('tembo_session') === null) {
            return null;
        }

        $cookies = [];
        foreach ($jar as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return ['cookies' => $cookies, 'token' => $csrf[1]];
    }

    /** JPEG ~250 Ko, équivalent d'une photo compressée côté client. */
    private function fabriquerJpeg(): string
    {
        $image = imagecreatetruecolor(1600, 1200);
        for ($y = 0; $y < 1200; $y += 3) {
            imagefilledrectangle($image, 0, $y, 1600, $y + 3, imagecolorallocate($image, random_int(10, 240), random_int(10, 120), random_int(10, 90)));
        }
        ob_start();
        imagejpeg($image, null, 80);

        return (string) ob_get_clean();
    }

    /** @return array<string, mixed> */
    private function optionsMesure(string $operation): array
    {
        return [
            'timeout' => 60,
            'on_stats' => function ($stats) use ($operation): void {
                $this->durations[$operation][] = $stats->getTransferTime() * 1000;
            },
        ];
    }

    private function domaine(): string
    {
        return (string) parse_url($this->baseUrl, PHP_URL_HOST);
    }

    private function rapport(): void
    {
        $this->newLine();
        $lignes = [];

        foreach ($this->durations as $operation => $durees) {
            sort($durees);
            $total = count($durees);
            $lignes[] = [
                $operation,
                $total,
                round($durees[0]).' ms',
                round($durees[(int) floor($total * 0.5)]).' ms',
                round($durees[min($total - 1, (int) floor($total * 0.95))]).' ms',
                round($durees[$total - 1]).' ms',
                round(array_sum($durees) / 1000, 1).' s',
            ];
        }

        $this->table(['Opération', 'Requêtes', 'Min', 'Médiane', 'p95', 'Max', 'Cumul'], $lignes);
        $this->line('Rappel : sur « php artisan serve » les requêtes sont sérialisées (un seul worker). Les chiffres en production (Apache/FPM ou nginx) seront meilleurs à concurrence égale.');
    }
}
