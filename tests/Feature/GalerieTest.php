<?php

use App\Enums\Phase;
use App\Http\Middleware\EnsureGuestSession;
use App\Models\GuestSession;
use App\Models\Photo;
use App\Models\User;
use App\Models\Vote;
use App\Support\EventPhase;
use App\Support\GalleryCache;

beforeEach(function () {
    $this->guestSession = GuestSession::factory()->create();
    EventPhase::set(Phase::Open);

    // Les requêtes getJson/postJson n'envoient pas les cookies sans ceci
    $this->withCredentials();
});

function requeteGalerie($test, array $parametres = [], array $entetes = [])
{
    return $test->withCookie(EnsureGuestSession::COOKIE, $test->guestSession->id)
        ->withHeaders($entetes)
        ->getJson(route('api.galerie', $parametres));
}

// ----- Page -----

it('affiche la galerie avec la barre fixe et l’accès au classement', function () {
    Photo::factory()->approved()->create(['display_name' => 'Aïcha']);

    $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('galerie.index'))
        ->assertOk()
        ->assertSee('Classement')
        ->assertSee('Touchez toutes les photos qui vous plaisent. Un appui de plus retire le vote.')
        ->assertSee('Aïcha');
});

it('exige une session pour la galerie et répond 401 en JSON au polling expiré', function () {
    $this->get(route('galerie.index'))->assertRedirect(route('tembo.entree'));

    $expiree = GuestSession::factory()->expired()->create();
    $this->withCookie(EnsureGuestSession::COOKIE, $expiree->id)
        ->getJson(route('api.galerie'))
        ->assertUnauthorized();
});

// ----- API et cache -----

it('ne sert que les photos publiées, les plus récentes d’abord', function () {
    Photo::factory()->approved()->create(['display_name' => 'Ancienne', 'created_at' => now()->subMinutes(5)]);
    Photo::factory()->approved()->create(['display_name' => 'Récente', 'created_at' => now()]);
    Photo::factory()->create(['display_name' => 'EnAttente']);
    Photo::factory()->rejected()->create(['display_name' => 'Refusée']);

    $reponse = requeteGalerie($this)->assertOk();

    $noms = collect($reponse->json('photos'))->pluck('nom');
    expect($noms->all())->toBe(['Récente', 'Ancienne'])
        ->and($reponse->json('photos.0.vignette'))->toContain('signature=');
});

it('ne renvoie que les nouveautés avec le paramètre apres (polling)', function () {
    $ancienne = Photo::factory()->approved()->create(['created_at' => now()->subMinutes(5)]);
    GalleryCache::invalidate();

    $curseur = requeteGalerie($this)->json('photos.0.curseur');

    Photo::factory()->approved()->create(['display_name' => 'Nouvelle', 'created_at' => now()]);
    GalleryCache::invalidate();

    $reponse = requeteGalerie($this, ['apres' => $curseur]);

    expect($reponse->json('photos'))->toHaveCount(1)
        ->and($reponse->json('photos.0.nom'))->toBe('Nouvelle');
});

it('pagine vers les photos plus anciennes avec le paramètre avant', function () {
    foreach (range(1, 35) as $i) {
        Photo::factory()->approved()->create(['created_at' => now()->subMinutes(100 - $i)]);
    }
    GalleryCache::invalidate();

    $premierePage = requeteGalerie($this);
    expect($premierePage->json('photos'))->toHaveCount(30)
        ->and($premierePage->json('complet'))->toBeFalse();

    $curseurAncien = collect($premierePage->json('photos'))->last()['curseur'];
    $secondePage = requeteGalerie($this, ['avant' => $curseurAncien]);

    expect($secondePage->json('photos'))->toHaveCount(5)
        ->and($secondePage->json('complet'))->toBeTrue();
});

it('sert la galerie entière avec tout=1 (recherche par prénom)', function () {
    foreach (range(1, 35) as $i) {
        Photo::factory()->approved()->create(['created_at' => now()->subMinutes(100 - $i)]);
    }
    GalleryCache::invalidate();

    $reponse = requeteGalerie($this, ['tout' => 1]);

    expect($reponse->json('photos'))->toHaveCount(35)
        ->and($reponse->json('complet'))->toBeTrue();
});

it('répond 304 sans corps quand rien n’a changé (ETag)', function () {
    Photo::factory()->approved()->create();
    GalleryCache::invalidate();

    $premiere = requeteGalerie($this)->assertOk();
    $etag = $premiere->headers->get('ETag');

    requeteGalerie($this, [], ['If-None-Match' => $etag])->assertStatus(304);

    // Une validation de photo invalide le cache : l'ETag change, le corps revient
    Photo::factory()->approved()->create(['display_name' => 'Fraîche']);
    GalleryCache::invalidate();

    $troisieme = requeteGalerie($this, [], ['If-None-Match' => $etag])->assertOk();
    expect(collect($troisieme->json('photos'))->pluck('nom'))->toContain('Fraîche');
});

it('invalide le cache quand la régie valide ou retire une photo', function () {
    $moderateur = User::factory()->create();
    $photo = Photo::factory()->create(['display_name' => 'Nouvelle']);

    // Galerie vide, mise en cache
    expect(requeteGalerie($this)->json('photos'))->toHaveCount(0);

    $this->actingAs($moderateur)->post(route('regie.photos.valider', $photo), [
        'verrou' => $photo->updated_at->toDateTimeString(),
    ]);

    expect(collect(requeteGalerie($this)->json('photos'))->pluck('nom'))->toContain('Nouvelle');

    $this->actingAs($moderateur)->post(route('regie.photos.retirer', $photo->refresh()));

    expect(requeteGalerie($this)->json('photos'))->toHaveCount(0);
});

// ----- Vote -----

function voter($test, Photo $photo)
{
    return $test->withCookie(EnsureGuestSession::COOKIE, $test->guestSession->id)
        ->postJson(route('votes.store'), ['photo_id' => $photo->id]);
}

it('enregistre un vote et incrémente le compteur dénormalisé', function () {
    $photo = Photo::factory()->approved()->create();

    voter($this, $photo)->assertOk()->assertJson(['photo_id' => $photo->id, 'vote' => true]);

    expect(Vote::query()->count())->toBe(1)
        ->and($photo->refresh()->votes_count)->toBe(1);
});

it('accepte autant de votes que l’invité veut, un par photo', function () {
    $photos = Photo::factory()->approved()->count(3)->create();

    foreach ($photos as $photo) {
        voter($this, $photo)->assertOk()->assertJson(['vote' => true]);
    }

    expect(Vote::query()->count())->toBe(3)
        ->and($photos->map(fn (Photo $photo): int => $photo->refresh()->votes_count)->all())->toBe([1, 1, 1]);
});

it('retire le vote au second appui sur la même photo', function () {
    $photo = Photo::factory()->approved()->create();

    voter($this, $photo)->assertOk()->assertJson(['vote' => true]);
    // Un appui malencontreux doit pouvoir se défaire
    voter($this, $photo)->assertOk()->assertJson(['vote' => false]);

    expect(Vote::query()->count())->toBe(0)
        ->and($photo->refresh()->votes_count)->toBe(0);

    // Et se refaire
    voter($this, $photo)->assertOk()->assertJson(['vote' => true]);
    expect(Vote::query()->count())->toBe(1)
        ->and($photo->refresh()->votes_count)->toBe(1);
});

it('refuse le vote pour sa propre photo', function () {
    $sienne = Photo::factory()->approved()->create(['guest_session_id' => $this->guestSession->id]);

    $reponse = voter($this, $sienne);

    $reponse->assertStatus(422);
    expect($reponse->json('errors.photo_id.0'))->toContain('votre propre photo')
        ->and(Vote::query()->count())->toBe(0)
        ->and($sienne->refresh()->votes_count)->toBe(0);
});

it('refuse de voter pour une photo hors galerie', function () {
    $enAttente = Photo::factory()->create();

    voter($this, $enAttente)->assertStatus(422);
    expect(Vote::query()->count())->toBe(0);
});

it('refuse le vote hors des phases open et vote_only', function () {
    $photo = Photo::factory()->approved()->create();

    EventPhase::set(Phase::Frozen);
    $reponse = voter($this, $photo);

    $reponse->assertStatus(422);
    expect($reponse->json('errors.photo_id.0'))->toContain('clos');

    EventPhase::set(Phase::VoteOnly);
    voter($this, $photo)->assertOk();
});

it('limite le martelage à 60 appuis par minute et par session', function () {
    // Le même appui répété suffit : la limite compte les requêtes, pas les votes
    $photo = Photo::factory()->approved()->create();

    for ($appui = 0; $appui < 60; $appui++) {
        voter($this, $photo)->assertOk();
    }

    voter($this, $photo)->assertStatus(429);
});

// ----- Classement invité -----

it('classe le Top 5 par votes en affichant les compteurs', function () {
    $photos = Photo::factory()->approved()->count(6)->create();
    // votes_count n'est volontairement pas fillable : forceFill dans le test
    $photos[3]->forceFill(['votes_count' => 9, 'display_name' => 'Meneuse'])->save();
    $photos[1]->forceFill(['votes_count' => 4, 'display_name' => 'Seconde'])->save();

    $reponse = $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('classement'))
        ->assertOk()
        // Transparence demandée par le client : le compteur de chaque photo
        ->assertSeeInOrder(['Meneuse', '9', 'votes', 'Seconde', '4', 'votes'])
        ->assertSee('vote')
        ->assertDontSee('votes_count');

    // 6 photos publiées, mais seulement 5 au classement
    expect(substr_count($reponse->content(), 'rounded-full'))->toBe(5);
});

it('n’affiche aucun compteur dans la galerie, seulement au classement', function () {
    $photo = Photo::factory()->approved()->create(['display_name' => 'Meneuse']);
    $photo->forceFill(['votes_count' => 42])->save();
    GalleryCache::invalidate();

    // L'effet de meute se joue au moment du choix : la grille reste muette.
    // Le compte n'est cherché ni dans le corps ni dans l'état initial d'Alpine
    // (assertDontSee sur « 42 » serait instable : la signature d'URL en
    // contient au hasard).
    $reponse = $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('galerie.index'))
        ->assertOk()
        ->assertSee('Meneuse');

    expect($reponse->content())->not->toContain('votes_count')
        ->and($reponse->content())->not->toContain('&quot;votes&quot;')
        ->and(requeteGalerie($this)->json('photos.0'))
        ->toHaveKeys(['id', 'nom', 'vignette', 'curseur'])
        ->and(requeteGalerie($this)->json('photos.0'))->not->toHaveKey('votes');
});

// ----- Phase et galerie -----

it('expose le droit de voter au polling et change d’ETag avec la phase', function () {
    Photo::factory()->approved()->create();

    $ouvert = requeteGalerie($this)->assertOk()->assertJson(['peutVoter' => true]);

    EventPhase::set(Phase::Frozen);

    // La clôture des votes ne peut pas être masquée par un 304 : la phase
    // entre dans l'ETag, le téléphone déjà ouvert l'apprend en 3 secondes.
    $ferme = requeteGalerie($this, [], ['If-None-Match' => $ouvert->headers->get('ETag')])
        ->assertOk()
        ->assertJson(['peutVoter' => false]);

    expect($ferme->headers->get('ETag'))->not->toBe($ouvert->headers->get('ETag'));
});

it('supprime les votes portés par une photo retirée de la galerie', function () {
    $moderateur = User::factory()->create();
    $photo = Photo::factory()->approved()->create();

    voter($this, $photo)->assertOk();

    $this->actingAs($moderateur)->post(route('regie.photos.retirer', $photo->refresh()));

    // Sinon l'invité garde un vote mort et le total du mur LED reste gonflé
    expect(Vote::query()->count())->toBe(0)
        ->and($photo->refresh()->votes_count)->toBe(0);
});

it('rend son vote à l’invité dont la photo votée a été retirée', function () {
    $moderateur = User::factory()->create();
    $retiree = Photo::factory()->approved()->create();
    $autre = Photo::factory()->approved()->create();

    voter($this, $retiree)->assertOk();
    $this->actingAs($moderateur)->post(route('regie.photos.retirer', $retiree->refresh()));

    voter($this, $autre)->assertOk();

    expect($autre->refresh()->votes_count)->toBe(1)
        ->and(Vote::query()->sole()->photo_id)->toBe($autre->id);
});

it('marque toutes mes photos votées, y compris hors de la page initiale', function () {
    $ancienne = Photo::factory()->approved()->create(['created_at' => now()->subHour()]);
    $recente = Photo::factory()->approved()->count(30)->create()->last();
    GalleryCache::invalidate();

    foreach ([$ancienne, $recente] as $photo) {
        Vote::factory()->create([
            'guest_session_id' => $this->guestSession->id,
            'photo_id' => $photo->id,
        ]);
    }

    // La barre fixe compte mes votes même quand une des photos n'est pas
    // dans les 30 chargées : les ids partent du serveur, pas de la grille.
    $reponse = $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('galerie.index'))
        ->assertOk();

    expect($reponse->content())->toContain($ancienne->id)
        ->and($reponse->content())->toContain($recente->id)
        ->and($reponse->content())->toContain('photos choisies');
});

it('signale au polling les photos retirées de la galerie', function () {
    $moderateur = User::factory()->create();
    $retiree = Photo::factory()->approved()->create(['display_name' => 'Retirée']);
    $refusee = Photo::factory()->create(['display_name' => 'Refusée']);
    Photo::factory()->approved()->create(['display_name' => 'Restée']);

    expect(collect(requeteGalerie($this)->json('photos'))->pluck('nom'))->toContain('Retirée');

    // Un refus en modération n'a jamais atteint la galerie : rien à retirer
    $this->actingAs($moderateur)->post(route('regie.photos.refuser', $refusee), [
        'verrou' => $refusee->updated_at->toDateTimeString(),
        'reason' => 'produit absent',
    ]);
    $this->actingAs($moderateur)->post(route('regie.photos.retirer', $retiree->refresh()));

    $reponse = requeteGalerie($this)->assertOk();

    expect(collect($reponse->json('photos'))->pluck('nom')->all())->toBe(['Restée'])
        ->and($reponse->json('retirees'))->toBe([$retiree->id]);
});

it('signale aussi le retrait d’une photo par son auteur', function () {
    $auteur = GuestSession::factory()->create();
    $photo = Photo::factory()->approved()->create(['guest_session_id' => $auteur->id]);

    $this->withCookie(EnsureGuestSession::COOKIE, $auteur->id)->post(route('photos.retrait'));

    expect(requeteGalerie($this)->json('retirees'))->toBe([$photo->id]);
});
