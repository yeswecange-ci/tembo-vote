<?php

use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GuestAccessController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\Regie\AuthController;
use App\Http\Controllers\Regie\DashboardController;
use App\Http\Controllers\Regie\ExportController;
use App\Http\Controllers\Regie\ModerationController;
use App\Http\Controllers\Regie\RevelationController;
use App\Http\Controllers\Regie\SoireeController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

// Le QR affiché en salle mène à /tembo : la racine y renvoie aussi
Route::redirect('/', '/tembo');

Route::get('/tembo', [GuestAccessController::class, 'showPinForm'])->name('tembo.pin');
Route::post('/tembo', [GuestAccessController::class, 'verifyPin'])->name('tembo.pin.verifier');

Route::middleware('guest.session')->group(function () {
    Route::get('/tembo/accueil', [GuestAccessController::class, 'home'])->name('tembo.accueil');

    Route::get('/tembo/photo', [PhotoController::class, 'create'])->name('photos.create');
    Route::post('/tembo/photo', [PhotoController::class, 'store'])
        ->middleware('throttle:uploads')
        ->name('photos.store');

    // Retrait sur demande : l'invité retire sa propre photo, à tout moment
    Route::post('/tembo/photo/retrait', [PhotoController::class, 'selfRemove'])->name('photos.retrait');

    Route::get('/tembo/galerie', [GalleryController::class, 'page'])->name('galerie.index');
    Route::get('/tembo/classement', [GalleryController::class, 'ranking'])->name('classement');
    Route::post('/tembo/vote', [VoteController::class, 'store'])
        ->middleware('throttle:votes')
        ->name('votes.store');

    // Polling de la galerie (3 s), servi depuis le cache avec ETag/304
    Route::get('/api/galerie', [GalleryController::class, 'index'])->name('api.galerie');
});

// Diffusion des images : route signée uniquement, le disque est privé.
// Hors du groupe guest.session : le mur LED (Module 5) affiche aussi ces images.
Route::get('/tembo/image/{photo}/{variante}', [PhotoController::class, 'image'])
    ->middleware('signed')
    ->whereIn('variante', ['plein', 'vignette'])
    ->name('photos.image');

// ----- Mode Écran (mur LED), protégé par clé secrète -----
Route::get('/ecran/{cle}', [ScreenController::class, 'show'])->name('ecran');
// Page dédiée au QR d'accès (tablette à l'entrée, second écran)
Route::get('/ecran/{cle}/qr', [ScreenController::class, 'qrPage'])->name('ecran.qr');
Route::get('/api/ecran/{cle}', [ScreenController::class, 'state'])->name('api.ecran');

// ----- Régie (back-office de modération) -----
Route::prefix('regie')->group(function () {
    Route::get('/connexion', [AuthController::class, 'showLoginForm'])->name('regie.connexion');
    Route::post('/connexion', [AuthController::class, 'login'])->name('regie.connexion.verifier');

    Route::middleware('auth')->group(function () {
        Route::post('/deconnexion', [AuthController::class, 'logout'])->name('regie.deconnexion');

        Route::get('/', [DashboardController::class, 'show'])->name('regie.dashboard');
        Route::get('/moderation', [ModerationController::class, 'queue'])->name('regie.moderation');
        Route::get('/publiees', [ModerationController::class, 'published'])->name('regie.publiees');
        Route::get('/etat', [ModerationController::class, 'state'])->name('regie.etat');
        Route::post('/photos/{photo}/valider', [ModerationController::class, 'approve'])->name('regie.photos.valider');
        Route::post('/photos/{photo}/refuser', [ModerationController::class, 'reject'])->name('regie.photos.refuser');
        Route::post('/photos/{photo}/retirer', [ModerationController::class, 'remove'])->name('regie.photos.retirer');

        Route::get('/soiree', [SoireeController::class, 'show'])->name('regie.soiree');
        Route::post('/soiree/phase', [SoireeController::class, 'setPhase'])->name('regie.soiree.phase');

        Route::get('/export', [ExportController::class, 'download'])->name('regie.export');

        Route::get('/revelation', [RevelationController::class, 'show'])->name('regie.revelation');
        Route::post('/revelation/valider', [RevelationController::class, 'validateRanking'])->name('regie.revelation.valider');
        Route::post('/revelation/lancer', [RevelationController::class, 'launchReveal'])->name('regie.revelation.lancer');
    });
});

if (app()->environment('local')) {
    // Démonstration du design system — jamais enregistrée hors local
    Route::view('/demo-design', 'demo-design')->name('demo-design');
}
