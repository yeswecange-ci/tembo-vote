<?php

use App\Enums\Phase;
use App\Enums\PhotoStatus;
use App\Http\Middleware\EnsureGuestSession;
use App\Models\GuestSession;
use App\Models\Photo;
use App\Support\EventPhase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->guestSession = GuestSession::factory()->create();
});

/** Envoie une photo comme le ferait le client (multipart + Accept JSON). */
function envoyerPhoto($test, array $surcharges = [])
{
    $donnees = array_merge([
        'photo' => UploadedFile::fake()->image('selfie.jpg', 2400, 1800),
        'display_name' => 'Aïcha',
    ], $surcharges);

    // post() multipart (et non postJson) : les fichiers ne survivent pas à un encodage JSON
    return $test->withCookie(EnsureGuestSession::COOKIE, $test->guestSession->id)
        ->withHeader('Accept', 'application/json')
        ->post(route('photos.store'), $donnees);
}

it('affiche l’écran de capture en phase open', function () {
    EventPhase::set(Phase::Open);

    $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('photos.create'))
        ->assertOk()
        ->assertSee('Prendre un selfie')
        ->assertSee('Choisir dans la galerie')
        // Marque et invite du champ prénom (décisions client du 20/08/2026)
        ->assertSee('votre Castel')
        ->assertSee('placeholder="Votre Prénom"', false)
        // Le formulaire ne demande plus que le prénom : ni case à cocher,
        // ni mention de consentement
        ->assertDontSee('type="checkbox"', false)
        ->assertDontSee('vous acceptez')
        ->assertDontSee('réutilise');
});

it('explique la fermeture de la publication hors phase open', function (Phase $phase, string $texte) {
    EventPhase::set($phase);

    $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('photos.create'))
        ->assertOk()
        ->assertSee($texte);
})->with([
    'setup' => [Phase::Setup, 'La publication n’est pas encore ouverte'],
    'frozen' => [Phase::Frozen, 'La publication est terminée'],
]);

it('publie une photo : pipeline complet, fichiers privés, ULID, vignette', function () {
    EventPhase::set(Phase::Open);

    $reponse = envoyerPhoto($this);

    $reponse->assertOk()->assertJsonStructure(['redirect']);

    $photo = Photo::query()->sole();
    expect($photo->status)->toBe(PhotoStatus::Pending)
        ->and($photo->display_name)->toBe('Aïcha')
        ->and($photo->consent_event)->toBeTrue()
        ->and($photo->consent_reuse)->toBeFalse()
        // Nom de fichier = ULID, le nom d'origine n'est jamais réutilisé
        ->and($photo->path)->toMatch('#^tembo/photos/[0-9a-z]{26}\.jpg$#')
        ->and($photo->thumb_path)->toMatch('#^tembo/thumbs/[0-9a-z]{26}\.jpg$#');

    Storage::assertExists($photo->path);
    Storage::assertExists($photo->thumb_path);

    // Redimensionné à 1600 px max, vignette à 400 px
    [$largeur] = getimagesizefromstring(Storage::get($photo->path));
    [$largeurVignette] = getimagesizefromstring(Storage::get($photo->thumb_path));
    expect($largeur)->toBeLessThanOrEqual(1600)
        ->and($largeurVignette)->toBeLessThanOrEqual(400);
});

it('re-encode toujours en JPEG, même un PNG', function () {
    EventPhase::set(Phase::Open);

    envoyerPhoto($this, ['photo' => UploadedFile::fake()->image('capture.png', 800, 600)])->assertOk();

    $photo = Photo::query()->sole();
    $mime = getimagesizefromstring(Storage::get($photo->path))['mime'];
    expect($mime)->toBe('image/jpeg');
});

it('nettoie le prénom : balises retirées, une seule ligne', function () {
    EventPhase::set(Phase::Open);

    envoyerPhoto($this, ['display_name' => "  <b>Aïcha</b>\n K. "])->assertOk();

    expect(Photo::query()->sole()->display_name)->toBe('Aïcha K.');
});

it('refuse un fichier qui n’est pas une image, avec une consigne claire', function () {
    EventPhase::set(Phase::Open);

    $reponse = envoyerPhoto($this, ['photo' => UploadedFile::fake()->create('document.pdf', 200, 'application/pdf')]);

    $reponse->assertStatus(422);
    expect($reponse->json('errors.photo.0'))->toContain('appareil photo')
        ->and(Photo::query()->count())->toBe(0);
});

it('refuse un fichier au-delà de la taille maximale', function () {
    EventPhase::set(Phase::Open);

    envoyerPhoto($this, ['photo' => UploadedFile::fake()->create('grosse.jpg', 6000, 'image/jpeg')])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photo');
});

it('enregistre le consentement d’affichage sans rien demander de plus', function () {
    EventPhase::set(Phase::Open);

    // Plus aucune case à cocher : l'envoi vaut consentement d'affichage
    envoyerPhoto($this)->assertOk();

    $photo = Photo::query()->sole();
    expect($photo->consent_event)->toBeTrue()
        // Le consentement de réutilisation n'est plus collecté
        ->and($photo->consent_reuse)->toBeFalse();
});

it('valide la longueur du prénom (2 à 24 caractères)', function (string $nom) {
    EventPhase::set(Phase::Open);

    envoyerPhoto($this, ['display_name' => $nom])
        ->assertStatus(422)
        ->assertJsonValidationErrors('display_name');
})->with([
    'trop court' => ['A'],
    'trop long' => [str_repeat('a', 25)],
]);

it('refuse une seconde photo tant que la première n’est pas rejetée', function () {
    EventPhase::set(Phase::Open);

    envoyerPhoto($this)->assertOk();
    $reponse = envoyerPhoto($this);

    $reponse->assertStatus(422);
    expect($reponse->json('errors.photo.0'))->toContain('déjà publié')
        ->and(Photo::query()->count())->toBe(1);
});

it('remplace une photo refusée et supprime ses fichiers', function () {
    EventPhase::set(Phase::Open);

    $refusee = Photo::factory()->rejected('photo illisible')->create([
        'guest_session_id' => $this->guestSession->id,
    ]);
    Storage::put($refusee->path, 'ancienne');
    Storage::put($refusee->thumb_path, 'ancienne vignette');

    envoyerPhoto($this)->assertOk();

    $photo = Photo::query()->sole();
    expect($photo->id)->not->toBe($refusee->id)
        ->and($photo->status)->toBe(PhotoStatus::Pending);

    Storage::assertMissing($refusee->path);
    Storage::assertMissing($refusee->thumb_path);
});

it('bloque la publication hors phase open', function () {
    EventPhase::set(Phase::VoteOnly);

    $reponse = envoyerPhoto($this);

    $reponse->assertStatus(422);
    expect($reponse->json('errors.photo.0'))->toContain('terminée')
        ->and(Photo::query()->count())->toBe(0);
});

it('limite les envois à 3 par minute et par session', function () {
    EventPhase::set(Phase::Open);

    foreach (range(1, 3) as $tentative) {
        envoyerPhoto($this, ['display_name' => '']); // tentatives comptées même invalides
    }

    envoyerPhoto($this)->assertStatus(429);
});

it('sert les images par URL signée uniquement', function () {
    EventPhase::set(Phase::Open);
    envoyerPhoto($this)->assertOk();

    $photo = Photo::query()->sole();

    // URL signée → l'image est servie
    $this->get($photo->signedImageUrl('vignette'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    // Sans signature → refus, même connecté
    $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('photos.image', ['photo' => $photo->id, 'variante' => 'vignette']))
        ->assertForbidden();
});

it('affiche le statut « en attente » puis « refusée » avec son motif', function () {
    EventPhase::set(Phase::Open);
    envoyerPhoto($this)->assertOk();

    $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('photos.create'))
        ->assertSee('En attente de validation');

    Photo::query()->sole()->update([
        'status' => PhotoStatus::Rejected,
        'reject_reason' => 'produit absent',
    ]);
    EventPhase::set(Phase::Frozen);

    $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('photos.create'))
        ->assertSee('Photo refusée')
        ->assertSee('produit absent');
});

it('propose de reprendre une photo refusée quand la publication est ouverte', function () {
    EventPhase::set(Phase::Open);

    Photo::factory()->rejected('photo illisible')->create([
        'guest_session_id' => $this->guestSession->id,
    ]);

    $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('photos.create'))
        ->assertSee('Votre photo précédente a été refusée')
        ->assertSee('photo illisible')
        ->assertSee('Prendre un selfie');
});

it('transforme le bouton de l’accueil en « Ma photo » avec le statut', function () {
    EventPhase::set(Phase::Open);
    envoyerPhoto($this)->assertOk();

    $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('tembo.accueil'))
        ->assertSee('Ma photo')
        ->assertSee('En attente de validation');
});
