<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Setting;
use App\Models\Vote;
use App\Services\PinService;
use App\Support\EventPhase;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Mode Écran (mur LED 3 × 4 m) : aucune interaction, résolution inconnue.
 * Route protégée par une clé secrète en config — pas de session, la machine
 * de régie ouvre simplement l'URL.
 */
class ScreenController extends Controller
{
    public function show(string $cle, PinService $pinService): View
    {
        $this->verifierCle($cle);

        return view('ecran', [
            'cle' => $cle,
            'initial' => $this->payload($pinService),
        ]);
    }

    /**
     * Page dédiée au QR d'accès : plein écran, toujours affiché quelle que
     * soit la phase — pour une tablette à l'entrée ou un second écran.
     */
    public function qrPage(string $cle, PinService $pinService): View
    {
        $this->verifierCle($cle);

        return view('ecran-qr', [
            'cle' => $cle,
            'initial' => $this->payload($pinService),
        ]);
    }

    /** Polling toutes les 2 secondes depuis l'écran. */
    public function state(string $cle, PinService $pinService): JsonResponse
    {
        $this->verifierCle($cle);

        return response()->json($this->payload($pinService))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @return array{phase: string, pin: string, qr: string, top: array<int, array<string, mixed>>, stats: array{photos: int, votes: int}}
     */
    private function payload(PinService $pinService): array
    {
        $pin = $pinService->current();

        $top = Photo::query()
            ->approved()
            ->orderByDesc('votes_count')
            ->oldest()
            ->limit(5)
            ->get()
            ->map(fn (Photo $photo): array => [
                'id' => $photo->id,
                'nom' => $photo->display_name,
                'vignette' => $photo->signedImageUrl('vignette'),
                // L'image pleine taille sert à la révélation du gagnant
                'plein' => $photo->signedImageUrl('plein'),
                'votes' => $photo->votes_count,
            ])
            ->all();

        return [
            'phase' => EventPhase::current()->value,
            'pin' => $pin->code,
            // QR d'accès direct : il embarque le code rotatif courant —
            // scanner l'écran fait entrer sans rien saisir, le PIN est le repli
            'qr' => $this->qrDataUri($pin->code),
            'top' => $top,
            // Le gagnant révélé est celui validé par un humain en régie ;
            // à défaut, le premier du classement courant
            'gagnant' => $this->winner() ?? ($top[0] ?? null),
            'stats' => [
                'photos' => Photo::query()->approved()->count(),
                'votes' => Vote::query()->count(),
            ],
        ];
    }

    /**
     * @return array{id: string, nom: string, vignette: string, plein: string, votes: int}|null
     */
    private function winner(): ?array
    {
        $winnerId = Setting::getValue('winner_photo_id');

        if ($winnerId === null) {
            return null;
        }

        $photo = Photo::query()->approved()->find($winnerId);

        if ($photo === null) {
            return null;
        }

        return [
            'id' => $photo->id,
            'nom' => $photo->display_name,
            'vignette' => $photo->signedImageUrl('vignette'),
            'plein' => $photo->signedImageUrl('plein'),
            'votes' => $photo->votes_count,
        ];
    }

    /** QR en data URI SVG, mis en cache par code (un nouveau toutes les 20 min). */
    private function qrDataUri(string $code): string
    {
        return Cache::remember('tembo.qr.'.$code, 3600, function () use ($code): string {
            $svg = (new Builder(
                writer: new SvgWriter,
                data: route('tembo.pin', ['code' => $code]),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 480,
                margin: 12,
            ))->build();

            return 'data:image/svg+xml;base64,'.base64_encode($svg->getString());
        });
    }

    private function verifierCle(string $cle): void
    {
        $attendue = (string) config('tembo.screen_key');

        // hash_equals : comparaison en temps constant, la clé est un secret
        abort_unless($attendue !== '' && hash_equals($attendue, $cle), 403);
    }
}
