<?php

use App\Models\AccessToken;
use App\Models\Setting;
use App\Models\User;

it('seed la base avec les 2 modérateurs, un jeton d’accès valide et la phase setup', function () {
    $this->seed();

    expect(User::query()->count())->toBe(2)
        ->and(User::query()->pluck('name')->sort()->values()->all())->toBe(['Christian', 'Hadassa'])
        ->and(AccessToken::query()->currentlyValid()->count())->toBe(1)
        ->and(Setting::getValue('phase'))->toBe('setup');

    $accessToken = AccessToken::query()->currentlyValid()->first();
    expect($accessToken->token)->toHaveLength(32);
});

it('ne remet pas la phase à zéro quand on re-seed en pleine soirée', function () {
    $this->seed();

    Setting::setValue('phase', 'open');
    $this->seed();

    expect(Setting::getValue('phase'))->toBe('open');
});

it('refuse de seeder les modérateurs sans mot de passe défini', function () {
    config(['tembo.moderator_password' => '']);

    $this->seed();
})->throws(RuntimeException::class);

it('expose la configuration tembo attendue', function () {
    expect(config('tembo.access.rotation_minutes'))->toBe(5)
        ->and(config('tembo.access.valid_tokens'))->toBe(2)
        ->and(config('tembo.access.token_length'))->toBe(32)
        ->and(config('tembo.rate_limits.upload.attempts'))->toBe(3)
        ->and(config('tembo.rate_limits.vote.attempts'))->toBe(10)
        ->and(config('tembo.upload_max_kb'))->toBe(5120)
        ->and(config('tembo.legal.consent_event'))->toBeString()->not->toBeEmpty()
        ->and(config('tembo.legal.responsible_drinking'))->toBeString()->not->toBeEmpty()
        ->and(config('tembo.screen_key'))->not->toBeEmpty();
});
