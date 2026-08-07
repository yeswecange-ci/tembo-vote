<?php

use App\Http\Middleware\EnsureGuestSession;
use App\Models\AccessPin;
use App\Models\AuditLog;
use App\Models\GuestSession;
use App\Services\PinService;
use Illuminate\Support\Facades\Storage;

it('affiche l’écran de saisie du code', function () {
    $this->get('/tembo')
        ->assertOk()
        ->assertSee('Code d’accès', false)
        ->assertSee('one-time-code', false);
});

it('crée une session et pose le cookie avec un code valide', function () {
    AccessPin::factory()->create(['code' => '1234']);

    $response = $this->post('/tembo', ['code' => '1234']);

    $response->assertRedirect(route('tembo.accueil'))
        ->assertCookie(EnsureGuestSession::COOKIE);

    $guestSession = GuestSession::query()->sole();
    expect($guestSession->pin_used)->toBe('1234')
        ->and($guestSession->expires_at->isFuture())->toBeTrue()
        // Empreintes hachées : jamais d'IP en clair en base
        ->and($guestSession->device_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($guestSession->ip_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('fait entrer directement via le QR de l’écran (?code=), sans saisie', function () {
    AccessPin::factory()->create(['code' => '1234']);

    $this->get('/tembo?code=1234')
        ->assertRedirect(route('tembo.accueil'))
        ->assertCookie(EnsureGuestSession::COOKIE);

    expect(GuestSession::query()->count())->toBe(1)
        ->and(GuestSession::query()->sole()->pin_used)->toBe('1234');
});

it('renvoie à la saisie manuelle quand le QR scanné a expiré', function () {
    AccessPin::factory()->create(['code' => '1234']);

    $this->get('/tembo?code=9999')
        ->assertRedirect(route('tembo.pin'))
        ->assertSessionHas('message');

    expect(GuestSession::query()->count())->toBe(0);
});

it('accepte les deux codes valides en glissement', function () {
    // Ancien code : rotation passée mais fenêtre de 40 min pas encore close
    AccessPin::factory()->create([
        'code' => '1111',
        'valid_from' => now()->subMinutes(25),
        'valid_until' => now()->addMinutes(15),
    ]);
    AccessPin::factory()->create(['code' => '2222']);

    $this->post('/tembo', ['code' => '1111'])->assertRedirect(route('tembo.accueil'));
    $this->post('/tembo', ['code' => '2222'])->assertRedirect(route('tembo.accueil'));

    expect(GuestSession::query()->count())->toBe(2);
});

it('rejette un code invalide avec un message explicite, sans créer de session', function () {
    AccessPin::factory()->create(['code' => '1234']);

    $this->from('/tembo')->post('/tembo', ['code' => '9999'])
        ->assertRedirect('/tembo')
        ->assertSessionHasErrors('code');

    expect(GuestSession::query()->count())->toBe(0);
});

it('bloque après 5 tentatives ratées et annonce le délai, même avec le bon code', function () {
    AccessPin::factory()->create(['code' => '1234']);

    foreach (range(1, 5) as $tentative) {
        $this->post('/tembo', ['code' => '0000']);
    }

    $response = $this->from('/tembo')->post('/tembo', ['code' => '1234']);

    $response->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toContain('Trop de tentatives')
        ->and(GuestSession::query()->count())->toBe(0);
});

it('génère un nouveau code à la volée quand la rotation est due, sans invalider l’ancien', function () {
    $ancien = AccessPin::factory()->create(['code' => '1111']);

    $this->travel(21)->minutes();

    $courant = app(PinService::class)->current();

    expect($courant->isNot($ancien))->toBeTrue()
        ->and($courant->valid_from->isPast())->toBeTrue()
        // L'ancien code reste utilisable jusqu'à la fin de sa fenêtre de 40 min
        ->and(AccessPin::query()->currentlyValid()->where('code', '1111')->exists())->toBeTrue();
});

it('ne crée pas de doublon tant que le code courant est frais', function () {
    $pin = AccessPin::factory()->create(['code' => '1111']);

    $service = app(PinService::class);

    expect($service->current()->is($pin))->toBeTrue()
        ->and(AccessPin::query()->count())->toBe(1);
});

it('la commande tembo:rotate-pin crée un code et le journalise', function () {
    $this->artisan('tembo:rotate-pin')->assertSuccessful();

    expect(AccessPin::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'pin.rotated')->count())->toBe(1);
});

it('la commande tembo:qr produit le PNG et le SVG', function () {
    Storage::fake('local');

    $this->artisan('tembo:qr')->assertSuccessful();

    Storage::assertExists('qr/qr-tembo.png');
    Storage::assertExists('qr/qr-tembo.svg');
});
