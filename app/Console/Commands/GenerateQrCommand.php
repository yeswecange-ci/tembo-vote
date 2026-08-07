<?php

namespace App\Console\Commands;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('tembo:qr {--taille=1200 : Largeur du PNG en pixels}')]
#[Description("Génère le QR code d'accès unique (PNG + SVG) dans storage/app/private/qr")]
class GenerateQrCommand extends Command
{
    public function handle(): int
    {
        $url = route('tembo.pin');
        $taille = (int) $this->option('taille');

        // Correction d'erreur élevée : le QR sera affiché sur des totems,
        // parfois photographié de loin ou sous un mauvais angle.
        $png = new Builder(
            writer: new PngWriter,
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $taille,
            margin: (int) round($taille * 0.05),
        )->build();

        $svg = new Builder(
            writer: new SvgWriter,
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $taille,
            margin: (int) round($taille * 0.05),
        )->build();

        Storage::put('qr/qr-tembo.png', $png->getString());
        Storage::put('qr/qr-tembo.svg', $svg->getString());

        $this->info("QR généré pour : {$url}");
        $this->line(Storage::path('qr/qr-tembo.png'));
        $this->line(Storage::path('qr/qr-tembo.svg'));
        $this->warn('Vérifiez que APP_URL pointe bien vers le domaine public avant de transmettre ce QR aux graphistes.');

        return self::SUCCESS;
    }
}
