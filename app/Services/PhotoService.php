<?php

namespace App\Services;

use App\Models\GuestSession;
use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;

/**
 * Pipeline de publication, dans l'ordre exact du brief : re-encodage JPEG
 * forcé (détruit tout fichier polyglotte), vignette 400 px, nom de fichier
 * ULID, stockage sur disque privé. Traitement synchrone dans la requête :
 * aucune queue à surveiller le soir de l'événement.
 */
class PhotoService
{
    /**
     * @param  array{display_name: string, consent_event: bool, consent_reuse: bool}  $data
     */
    public function publish(GuestSession $guestSession, UploadedFile $file, array $data): Photo
    {
        // Le nom d'origine du fichier n'est jamais réutilisé
        $ulid = strtolower((string) Str::ulid());
        $path = "tembo/photos/{$ulid}.jpg";
        $thumbPath = "tembo/thumbs/{$ulid}.jpg";

        try {
            $image = ImageManager::gd()->read($file->getRealPath());
        } catch (DecoderException) {
            // Jamais confiance au mimeType envoyé par le navigateur : si GD ne
            // décode pas le contenu réel, on refuse avec une consigne claire.
            throw ValidationException::withMessages([
                'photo' => 'Cette image ne peut pas être lue. Reprenez la photo avec l’appareil photo du téléphone.',
            ]);
        }

        $quality = (int) config('tembo.image.jpeg_quality');

        $image->scaleDown(width: (int) config('tembo.image.max_width'), height: (int) config('tembo.image.max_width'));
        Storage::put($path, (string) $image->toJpeg($quality));

        $image->scaleDown(width: (int) config('tembo.image.thumb_width'));
        Storage::put($thumbPath, (string) $image->toJpeg($quality));

        $previousFiles = [];

        try {
            $photo = DB::transaction(function () use ($guestSession, $data, $path, $thumbPath, &$previousFiles): Photo {
                // Une photo refusée est remplacée : l'invité conserve son unique
                // droit de publication. Ses fichiers sont supprimés après commit.
                $previous = $guestSession->photo()->first();

                if ($previous !== null) {
                    $previousFiles = [$previous->path, $previous->thumb_path];
                    $previous->delete();
                }

                return $guestSession->photo()->create([
                    'display_name' => $data['display_name'],
                    'path' => $path,
                    'thumb_path' => $thumbPath,
                    'consent_event' => $data['consent_event'],
                    'consent_reuse' => $data['consent_reuse'],
                ]);
            });
        } catch (\Throwable $exception) {
            // Aucun fichier orphelin si l'écriture en base échoue
            Storage::delete([$path, $thumbPath]);

            throw $exception;
        }

        if ($previousFiles !== []) {
            Storage::delete($previousFiles);
        }

        return $photo;
    }
}
