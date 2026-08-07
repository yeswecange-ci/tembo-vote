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
        ->assertSee('Touchez une photo pour voter')
        ->assertSee('Aïcha');
});

it('exige une session pour la galerie et répond 401 en JSON au polling expiré', function () {
    $this->get(route('galerie.index'))->assertRedirect(route('tembo.pin'));

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

    voter($this, $photo)->assertOk()->assertJson(['vote' => $photo->id]);

    expect(Vote::query()->count())->toBe(1)
        ->and($photo->refresh()->votes_count)->toBe(1);
});

it('ne compte qu’un vote actif par session, changeable', function () {
    $premiere = Photo::factory()->approved()->create();
    $seconde = Photo::factory()->approved()->create();

    voter($this, $premiere)->assertOk();
    // Re-voter la même photo ne change rien
    voter($this, $premiere)->assertOk();
    expect(Vote::query()->count())->toBe(1)
        ->and($premiere->refresh()->votes_count)->toBe(1);

    // Changer de vote : décrément de l'ancienne, incrément de la nouvelle
    voter($this, $seconde)->assertOk();
    expect(Vote::query()->count())->toBe(1)
        ->and($premiere->refresh()->votes_count)->toBe(0)
        ->and($seconde->refresh()->votes_count)->toBe(1)
        ->and(Vote::query()->sole()->photo_id)->toBe($seconde->id);
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

it('limite à 10 votes par minute et par session', function () {
    $photos = Photo::factory()->approved()->count(11)->create();

    foreach ($photos->take(10) as $photo) {
        voter($this, $photo)->assertOk();
    }

    voter($this, $photos->last())->assertStatus(429);
});

// ----- Classement invité -----

it('classe le Top 5 par votes sans afficher les compteurs', function () {
    $photos = Photo::factory()->approved()->count(6)->create();
    // votes_count n'est volontairement pas fillable : forceFill dans le test
    $photos[3]->forceFill(['votes_count' => 9, 'display_name' => 'Meneuse'])->save();
    $photos[1]->forceFill(['votes_count' => 4, 'display_name' => 'Seconde'])->save();

    $reponse = $this->withCookie(EnsureGuestSession::COOKIE, $this->guestSession->id)
        ->get(route('classement'))
        ->assertOk()
        ->assertSeeInOrder(['Meneuse', 'Seconde'])
        ->assertDontSee('votes_count');

    // 6 photos publiées, mais seulement 5 au classement
    expect(substr_count($reponse->content(), 'rounded-full'))->toBe(5);
});
