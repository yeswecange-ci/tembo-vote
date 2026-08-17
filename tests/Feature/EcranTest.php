<?php

use App\Enums\Phase;
use App\Models\AccessToken;
use App\Models\Photo;
use App\Models\Vote;
use App\Support\EventPhase;

beforeEach(function () {
    $this->cle = config('tembo.screen_key');
});

it('refuse l’écran et son API sans la bonne clé', function () {
    $this->get('/ecran/mauvaise-cle')->assertForbidden();
    $this->getJson('/api/ecran/mauvaise-cle')->assertForbidden();
});

it('affiche l’écran d’attente avec le QR d’accès en phase setup', function () {
    AccessToken::factory()->create();

    $this->get(route('ecran', ['cle' => $this->cle]))
        ->assertOk()
        ->assertSee('data:image/svg+xml;base64,', false)
        ->assertSee('Soirée Castel Beer Afterwork')
        ->assertSee(config('tembo.legal.responsible_drinking'));
});

it('expose le classement complet en JSON pour le polling', function () {
    EventPhase::set(Phase::Open);

    $photos = Photo::factory()->approved()->count(6)->create();
    $photos[2]->forceFill(['votes_count' => 8, 'display_name' => 'Meneuse'])->save();
    $photos[5]->forceFill(['votes_count' => 3, 'display_name' => 'Second'])->save();
    Vote::factory()->count(2)->create(['photo_id' => $photos[2]->id]);

    $reponse = $this->getJson(route('api.ecran', ['cle' => $this->cle]))->assertOk();

    expect($reponse->json('phase'))->toBe('open')
        // Unique porte d'entrée : le QR embarque le jeton rotatif
        ->and($reponse->json('qr'))->toStartWith('data:image/svg+xml;base64,')
        // Top 5 seulement, mené par la photo la plus votée
        ->and($reponse->json('top'))->toHaveCount(5)
        ->and($reponse->json('top.0.nom'))->toBe('Meneuse')
        ->and($reponse->json('top.0.votes'))->toBe(8)
        ->and($reponse->json('top.0.vignette'))->toContain('signature=')
        ->and($reponse->json('top.0.plein'))->toContain('signature=')
        ->and($reponse->json('stats.photos'))->toBe(6)
        ->and($reponse->json('stats.votes'))->toBe(2);
});

it('sert le classement live et le bandeau de stats en phase open', function () {
    EventPhase::set(Phase::Open);
    Photo::factory()->approved()->create(['display_name' => 'Aïcha']);

    $this->get(route('ecran', ['cle' => $this->cle]))
        ->assertOk()
        ->assertSee('La photo de la soirée')
        ->assertSee('photos')
        ->assertSee('votes');
});

it('continue de servir le classement figé en phase frozen', function () {
    EventPhase::set(Phase::Frozen);
    Photo::factory()->approved()->create();

    $this->getJson(route('api.ecran', ['cle' => $this->cle]))
        ->assertOk()
        ->assertJsonPath('phase', 'frozen');
});

it('rend les sections révélation et remerciement selon la phase', function () {
    Photo::factory()->approved()->create(['display_name' => 'Gagnante']);

    EventPhase::set(Phase::Reveal);
    $this->get(route('ecran', ['cle' => $this->cle]))->assertOk();

    EventPhase::set(Phase::Closed);
    $this->get(route('ecran', ['cle' => $this->cle]))
        ->assertOk()
        ->assertSee('Merci');
});

it('sert la page dédiée au QR d’accès, protégée par la même clé', function () {
    AccessToken::factory()->create();

    $this->get(route('ecran.qr', ['cle' => $this->cle]))
        ->assertOk()
        ->assertSee('Scannez pour publier votre selfie')
        ->assertSee('data:image/svg+xml;base64,', false)
        // Plus aucun code à saisir nulle part
        ->assertDontSee('saisissez le code');

    $this->get('/ecran/mauvaise-cle/qr')->assertForbidden();
});

it('génère un jeton à la volée pour l’écran si aucun n’est valide', function () {
    // Aucun jeton en base : l'écran ne doit jamais afficher un QR vide
    $reponse = $this->getJson(route('api.ecran', ['cle' => $this->cle]));

    expect($reponse->json('qr'))->toStartWith('data:image/svg+xml;base64,')
        ->and(AccessToken::query()->currentlyValid()->count())->toBe(1);
});
