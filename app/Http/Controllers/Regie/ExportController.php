<?php

namespace App\Http\Controllers\Regie;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Export de fin de soirée : les photos validées en pleine taille + un CSV
 * (prénom, votes, rang, consentement de réutilisation, horodatage).
 */
class ExportController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        $photos = Photo::query()
            ->approved()
            ->orderByDesc('votes_count')
            ->oldest()
            ->get();

        abort_if($photos->isEmpty(), 404, 'Aucune photo validée à exporter.');

        $zipPath = tempnam(sys_get_temp_dir(), 'tembo-export');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Impossible de préparer l’archive. Vérifiez l’espace disque puis réessayez.');
        }

        // CSV en tête d'archive : séparateur point-virgule, lisible dans Excel FR
        $lignes = ['rang;prenom;votes;consentement_reutilisation;horodatage;fichier'];

        foreach ($photos as $rang => $photo) {
            $fichier = sprintf('%02d-%s.jpg', $rang + 1, Str::slug($photo->display_name) ?: 'photo');
            $lignes[] = implode(';', [
                $rang + 1,
                str_replace(';', ',', $photo->display_name),
                $photo->votes_count,
                $photo->consent_reuse ? 'oui' : 'non',
                $photo->created_at->format('Y-m-d H:i:s'),
                $fichier,
            ]);

            if (Storage::exists($photo->path)) {
                $zip->addFile(Storage::path($photo->path), 'photos/'.$fichier);
            }
        }

        $zip->addFromString('photos.csv', "\xEF\xBB\xBF".implode("\r\n", $lignes));
        $zip->close();

        AuditLog::write('export.downloaded', $request->user()->name, meta: ['photos' => $photos->count()]);

        return response()
            ->download($zipPath, 'export-tembo-'.now()->format('Ymd-Hi').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
