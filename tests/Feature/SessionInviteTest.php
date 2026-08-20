<?php

use App\Enums\Phase;
use App\Http\Middleware\EnsureGuestSession;
use App\Models\GuestSession;
use App\Models\Photo;
use App\Models\Vote;
use App\Support\EventPhase;
use App\Support\GalleryCache;

it('renvoie vers l’entrée sans session', function () {
    $this->get(route('tembo.accueil'))
        ->assertRedirect(route('tembo.entree'))
        ->assertSessionHas('message');
});

it('renvoie vers l’entrée quand la session a expiré', function () {
    $guestSession = GuestSession::factory()->expired()->create();

    $this->withCookie(EnsureGuestSession::COOKIE, $guestSession->id)
        ->get(route('tembo.accueil'))
        ->assertRedirect(route('tembo.entree'));
});

it('renvoie vers l’entrée quand la session est révoquée', function () {
    $guestSession = GuestSession::factory()->revoked()->create();

    $this->withCookie(EnsureGuestSession::COOKIE, $guestSession->id)
        ->get(route('tembo.accueil'))
        ->assertRedirect(route('tembo.entree'));
});

it('affiche l’accueil et ses deux actions avec une session active', function () {
    $guestSession = GuestSession::factory()->create();

    $this->withCookie(EnsureGuestSession::COOKIE, $guestSession->id)
        ->get(route('tembo.accueil'))
        ->assertOk()
        ->assertSee('Publier ma photo')
        ->assertSee('Voter');
});

it('explique pourquoi les actions sont fermées en phase de préparation', function () {
    EventPhase::set(Phase::Setup);
    $guestSession = GuestSession::factory()->create();

    $this->withCookie(EnsureGuestSession::COOKIE, $guestSession->id)
        ->get(route('tembo.accueil'))
        ->assertSee('La publication ouvrira au lancement de la soirée.')
        ->assertSee('Le vote ouvrira pendant la soirée.');
});

it('active les deux actions en phase open', function () {
    EventPhase::set(Phase::Open);
    $guestSession = GuestSession::factory()->create();

    $this->withCookie(EnsureGuestSession::COOKIE, $guestSession->id)
        ->get(route('tembo.accueil'))
        ->assertSee(route('photos.create'))
        ->assertSee(route('galerie.index'));
});

it('présente un accueil riche : comment ça marche, ma participation, ambiance', function () {
    EventPhase::set(Phase::Open);
    $guestSession = GuestSession::factory()->create();
    $photo = Photo::factory()->approved()->create(['display_name' => 'Aïcha']);
    Vote::factory()->create(['guest_session_id' => $guestSession->id, 'photo_id' => $photo->id]);
    GalleryCache::invalidate();

    $this->withCookie(EnsureGuestSession::COOKIE, $guestSession->id)
        ->get(route('tembo.accueil'))
        ->assertSee('Comment ça marche')
        ->assertSee('Votre soirée')
        ->assertSee('Mes votes')
        // Plusieurs votes possibles : l'accueil en donne le compte, il ne peut
        // plus nommer arbitrairement l'une des photos choisies
        ->assertSee('photo choisie')
        ->assertDontSee('Aïcha')
        ->assertSee('déjà dans la galerie');
});

it('n’exige pas de rescanner quand la session est déjà active', function () {
    $guestSession = GuestSession::factory()->create();

    $this->withCookie(EnsureGuestSession::COOKIE, $guestSession->id)
        ->get('/tembo')
        ->assertRedirect(route('tembo.accueil'));
});

it('la machine à états expose la phase courante avec setup par défaut', function () {
    expect(EventPhase::current())->toBe(Phase::Setup);

    EventPhase::set(Phase::VoteOnly);

    expect(EventPhase::current())->toBe(Phase::VoteOnly);
});

it('garde une destination pour l’invité après la clôture des votes', function () {
    EventPhase::set(Phase::Frozen);
    $guestSession = GuestSession::factory()->create();
    Photo::factory()->approved()->create();
    GalleryCache::invalidate();

    // Deux boutons éteints pendant toute la fin de soirée : la galerie et le
    // classement restent consultables, l'accueil doit y mener.
    $this->withCookie(EnsureGuestSession::COOKIE, $guestSession->id)
        ->get(route('tembo.accueil'))
        ->assertOk()
        ->assertSee('Voir le classement')
        ->assertSee(route('classement'))
        ->assertSee(route('galerie.index'));
});
