<?php

use App\Enums\Phase;
use App\Http\Middleware\EnsureGuestSession;
use App\Models\AccessToken;
use App\Models\AuditLog;
use App\Models\GuestSession;
use App\Services\AccessTokenService;
use App\Support\EventPhase;

/** URL exacte portée par le QR de l'écran. */
function urlScan(string $token): string
{
    return '/tembo?'.AccessTokenService::QUERY_PARAM.'='.$token;
}

it('fait entrer directement en scannant le QR, sans rien saisir', function () {
    AccessToken::factory()->create(['token' => 'jeton-valide']);

    $this->get(urlScan('jeton-valide'))
        ->assertRedirect(route('tembo.accueil'))
        ->assertCookie(EnsureGuestSession::COOKIE);

    $guestSession = GuestSession::query()->sole();
    expect($guestSession->token_used)->toBe('jeton-valide')
        ->and($guestSession->expires_at->isFuture())->toBeTrue()
        // Empreintes hachées : jamais d'IP en clair en base
        ->and($guestSession->device_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($guestSession->ip_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('mène droit à la publication pendant la phase de publication', function () {
    EventPhase::set(Phase::Open);
    AccessToken::factory()->create(['token' => 'jeton-valide']);

    $this->get(urlScan('jeton-valide'))->assertRedirect(route('photos.create'));
});

it('mène droit à la galerie pendant la phase de vote seul', function () {
    EventPhase::set(Phase::VoteOnly);
    AccessToken::factory()->create(['token' => 'jeton-valide']);

    $this->get(urlScan('jeton-valide'))->assertRedirect(route('galerie.index'));
});

it('explique quoi faire quand on arrive sans jeton, sans proposer de saisie', function () {
    $this->get('/tembo')
        ->assertForbidden()
        ->assertSee('scannant le QR code', false)
        // Plus aucun champ de code : le QR est le seul chemin
        ->assertDontSee('one-time-code', false);

    expect(GuestSession::query()->count())->toBe(0);
});

it('refuse un QR périmé et invite à rescanner l’écran', function () {
    AccessToken::factory()->create(['token' => 'jeton-valide']);

    $this->get(urlScan('jeton-perime'))
        ->assertForbidden()
        ->assertSee('n’est plus valide', false);

    expect(GuestSession::query()->count())->toBe(0);
});

it('refuse un jeton dont la fenêtre est close', function () {
    AccessToken::factory()->expired()->create(['token' => 'jeton-clos']);

    $this->get(urlScan('jeton-clos'))->assertForbidden();

    expect(GuestSession::query()->count())->toBe(0);
});

it('accepte les deux jetons valides en glissement', function () {
    // Ancien jeton : rotation passée, fenêtre de 10 min pas encore close
    AccessToken::factory()->create([
        'token' => 'jeton-ancien',
        'valid_from' => now()->subMinutes(6),
        'valid_until' => now()->addMinutes(4),
    ]);
    AccessToken::factory()->create(['token' => 'jeton-courant']);

    $this->get(urlScan('jeton-ancien'))->assertRedirect(route('tembo.accueil'));
    $this->get(urlScan('jeton-courant'))->assertRedirect(route('tembo.accueil'));

    expect(GuestSession::query()->count())->toBe(2);
});

it('ne redemande rien à qui a déjà une session active', function () {
    $guestSession = GuestSession::factory()->create();

    $this->withCookie(EnsureGuestSession::COOKIE, $guestSession->id)
        ->get('/tembo')
        ->assertRedirect(route('tembo.accueil'));

    // Aucune session supplémentaire : le scan n'a pas été rejoué
    expect(GuestSession::query()->count())->toBe(1);
});

it('génère un nouveau jeton à la volée quand la rotation est due, sans invalider l’ancien', function () {
    $ancien = AccessToken::factory()->create(['token' => 'jeton-ancien']);

    $this->travel(6)->minutes();

    $courant = app(AccessTokenService::class)->current();

    expect($courant->isNot($ancien))->toBeTrue()
        ->and($courant->valid_from->isPast())->toBeTrue()
        // L'ancien reste utilisable jusqu'à la fin de sa fenêtre de 10 min
        ->and(AccessToken::query()->currentlyValid()->where('token', 'jeton-ancien')->exists())->toBeTrue();
});

it('ne crée pas de doublon tant que le jeton courant est frais', function () {
    $accessToken = AccessToken::factory()->create();

    $service = app(AccessTokenService::class);

    expect($service->current()->is($accessToken))->toBeTrue()
        ->and(AccessToken::query()->count())->toBe(1);
});

it('produit un jeton long et opaque, hors de portée d’une devinette', function () {
    $accessToken = app(AccessTokenService::class)->rotate();

    expect($accessToken->token)->toHaveLength(32)
        ->and($accessToken->token)->toMatch('/^[A-Za-z0-9]{32}$/');
});

it('la commande tembo:rotate-token crée un jeton et le journalise', function () {
    $this->artisan('tembo:rotate-token')->assertSuccessful();

    expect(AccessToken::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'token.rotated')->count())->toBe(1);
});
