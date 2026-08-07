<?php

use App\Enums\Phase;
use App\Enums\PhotoStatus;
use App\Models\AccessPin;
use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\User;
use App\Support\EventPhase;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    $this->moderator = User::factory()->create(['name' => 'Christian']);
});

// ----- Accès -----

it('exige une connexion pour toute la régie', function () {
    $this->get(route('regie.moderation'))->assertRedirect(route('regie.connexion'));
    $this->get(route('regie.soiree'))->assertRedirect(route('regie.connexion'));
});

it('connecte un modérateur et le journalise', function () {
    $reponse = $this->post(route('regie.connexion.verifier'), [
        'email' => $this->moderator->email,
        'password' => 'password',
    ]);

    $reponse->assertRedirect(route('regie.dashboard'));
    $this->assertAuthenticatedAs($this->moderator);
    expect(AuditLog::query()->where('action', 'regie.login')->where('actor', 'Christian')->exists())->toBeTrue();
});

it('présente le tableau de bord : chiffres, PIN, phase et dernières actions', function () {
    AccessPin::factory()->create(['code' => '4827']);
    Photo::factory()->count(2)->create();
    Photo::factory()->approved()->create();
    AuditLog::write('photo.approved', 'Hadassa', 'photo', 'x');

    $this->actingAs($this->moderator)->get(route('regie.dashboard'))
        ->assertOk()
        ->assertSee('en attente')
        ->assertSee('en ligne')
        ->assertSee('4827')
        ->assertSee('Préparation')
        ->assertSee('Photo validée')
        ->assertSee('Hadassa');
});

it('refuse de mauvais identifiants avec un message clair', function () {
    $this->from(route('regie.connexion'))->post(route('regie.connexion.verifier'), [
        'email' => $this->moderator->email,
        'password' => 'mauvais-mot-de-passe',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('bloque le login après 5 tentatives ratées', function () {
    foreach (range(1, 5) as $tentative) {
        $this->post(route('regie.connexion.verifier'), [
            'email' => $this->moderator->email,
            'password' => 'mauvais',
        ]);
    }

    $reponse = $this->from(route('regie.connexion'))->post(route('regie.connexion.verifier'), [
        'email' => $this->moderator->email,
        'password' => 'password',
    ]);

    $reponse->assertRedirect(route('regie.connexion'))->assertSessionHasErrors('email');

    // Même le bon mot de passe est refusé pendant le blocage, avec le délai
    // annoncé. En environnement de test, le sac d'erreurs peut arriver
    // sérialisé en tableau (session json) : on couvre les deux formes.
    $erreurs = session('errors');
    $message = $erreurs instanceof ViewErrorBag
        ? $erreurs->first('email')
        : (string) data_get($erreurs, 'default.messages.email.0');

    expect($message)->toContain('Trop de tentatives');
    $this->assertGuest();
});

it('déconnecte proprement', function () {
    $this->actingAs($this->moderator)->post(route('regie.deconnexion'))
        ->assertRedirect(route('regie.connexion'));

    $this->assertGuest();
});

// ----- File de modération -----

it('affiche la photo la plus ancienne en premier, avec le compteur', function () {
    Photo::factory()->create(['display_name' => 'Récente', 'created_at' => now()]);
    Photo::factory()->create(['display_name' => 'Ancienne', 'created_at' => now()->subMinutes(10)]);

    $this->actingAs($this->moderator)->get(route('regie.moderation'))
        ->assertOk()
        ->assertSee('Ancienne')
        ->assertDontSee('Récente')
        ->assertSee('en attente de validation');
});

it('affiche un état vide conçu quand la file est vide', function () {
    $this->actingAs($this->moderator)->get(route('regie.moderation'))
        ->assertOk()
        ->assertSee('Aucune photo en attente');
});

it('valide une photo et le journalise', function () {
    $photo = Photo::factory()->create();

    $this->actingAs($this->moderator)->post(route('regie.photos.valider', $photo), [
        'verrou' => $photo->updated_at->toDateTimeString(),
    ])->assertRedirect(route('regie.moderation'));

    $photo->refresh();
    expect($photo->status)->toBe(PhotoStatus::Approved)
        ->and($photo->moderated_by)->toBe($this->moderator->id)
        ->and($photo->moderated_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'photo.approved')->where('target_id', $photo->id)->exists())->toBeTrue();
});

it('refuse une photo avec un motif de la liste', function () {
    $photo = Photo::factory()->create();

    $this->actingAs($this->moderator)->post(route('regie.photos.refuser', $photo), [
        'verrou' => $photo->updated_at->toDateTimeString(),
        'reason' => 'photo illisible',
    ])->assertRedirect(route('regie.moderation'));

    $photo->refresh();
    expect($photo->status)->toBe(PhotoStatus::Rejected)
        ->and($photo->reject_reason)->toBe('photo illisible')
        ->and(AuditLog::query()->where('action', 'photo.rejected')->exists())->toBeTrue();
});

it('exige un motif dans la liste pour refuser', function () {
    $photo = Photo::factory()->create();

    $this->actingAs($this->moderator)->from(route('regie.moderation'))
        ->post(route('regie.photos.refuser', $photo), [
            'verrou' => $photo->updated_at->toDateTimeString(),
            'reason' => 'motif inventé',
        ])->assertSessionHasErrors('reason');

    expect($photo->refresh()->status)->toBe(PhotoStatus::Pending);
});

it('détecte la collision entre les deux modérateurs (verrou périmé)', function () {
    $photo = Photo::factory()->create();

    $reponse = $this->actingAs($this->moderator)->post(route('regie.photos.valider', $photo), [
        'verrou' => now()->subMinutes(3)->toDateTimeString(),
    ]);

    $reponse->assertRedirect(route('regie.moderation'))
        ->assertSessionHas('collision');

    // La photo n'a pas bougé : le second modérateur n'écrase rien
    expect($photo->refresh()->status)->toBe(PhotoStatus::Pending);
});

it('ne valide pas une photo déjà traitée', function () {
    $photo = Photo::factory()->approved()->create();

    $this->actingAs($this->moderator)->post(route('regie.photos.valider', $photo), [
        'verrou' => $photo->updated_at->toDateTimeString(),
    ])->assertSessionHas('collision');
});

// ----- Publiées et retrait -----

it('liste les photos publiées et permet le retrait à tout moment', function () {
    $photo = Photo::factory()->approved()->create(['display_name' => 'Aïcha']);

    $this->actingAs($this->moderator)->get(route('regie.publiees'))
        ->assertOk()
        ->assertSee('Aïcha')
        ->assertSee('Retirer');

    $this->actingAs($this->moderator)->post(route('regie.photos.retirer', $photo))
        ->assertRedirect(route('regie.publiees'));

    $photo->refresh();
    expect($photo->status)->toBe(PhotoStatus::Rejected)
        ->and($photo->reject_reason)->toBe(config('tembo.removal_reason'))
        ->and(AuditLog::query()->where('action', 'photo.removed')->exists())->toBeTrue();
});

it('signale un retrait déjà effectué', function () {
    $photo = Photo::factory()->rejected()->create();

    $this->actingAs($this->moderator)->post(route('regie.photos.retirer', $photo))
        ->assertSessionHas('collision');
});

// ----- Polling -----

it('expose l’état de la file en JSON pour le polling', function () {
    $photo = Photo::factory()->create();
    Photo::factory()->create();

    $this->actingAs($this->moderator)
        ->getJson(route('regie.etat', ['photo' => $photo->id]))
        ->assertOk()
        ->assertJson(['pending' => 2, 'currentStillPending' => true]);

    $photo->update(['status' => PhotoStatus::Approved]);

    $this->actingAs($this->moderator)
        ->getJson(route('regie.etat', ['photo' => $photo->id]))
        ->assertJson(['pending' => 1, 'currentStillPending' => false]);
});

// ----- Soirée -----

it('change la phase en un clic, avec effet immédiat et journalisation', function () {
    expect(EventPhase::current())->toBe(Phase::Setup);

    $this->actingAs($this->moderator)->post(route('regie.soiree.phase'), ['phase' => 'open'])
        ->assertRedirect(route('regie.soiree'));

    expect(EventPhase::current())->toBe(Phase::Open)
        ->and(AuditLog::query()->where('action', 'phase.changed')->exists())->toBeTrue();
});

it('refuse une phase inconnue', function () {
    $this->actingAs($this->moderator)->from(route('regie.soiree'))
        ->post(route('regie.soiree.phase'), ['phase' => 'inexistante'])
        ->assertSessionHasErrors('phase');
});

it('affiche le PIN courant et les 6 phases sur la page Soirée', function () {
    $pin = AccessPin::factory()->create(['code' => '4827']);

    $reponse = $this->actingAs($this->moderator)->get(route('regie.soiree'));

    $reponse->assertOk()->assertSee('4827');

    foreach (Phase::cases() as $phase) {
        $reponse->assertSee($phase->label());
    }
});
