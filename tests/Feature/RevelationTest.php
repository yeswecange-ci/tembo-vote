<?php

use App\Enums\Phase;
use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vote;
use App\Support\EventPhase;

beforeEach(function () {
    $this->moderator = User::factory()->create(['name' => 'Christian']);
});

it('affiche le Top 5 avec les compteurs et signale les votes suspects', function () {
    $suspecte = Photo::factory()->approved()->create(['display_name' => 'Suspecte']);
    $saine = Photo::factory()->approved()->create(['display_name' => 'Saine']);
    $suspecte->forceFill(['votes_count' => 3])->save();
    $saine->forceFill(['votes_count' => 2])->save();

    // Deux votes pour « Suspecte » depuis la même empreinte d'appareil
    $memeAppareil = hash('sha256', 'meme-appareil');
    Vote::factory()->create(['photo_id' => $suspecte->id, 'device_hash' => $memeAppareil]);
    Vote::factory()->create(['photo_id' => $suspecte->id, 'device_hash' => $memeAppareil]);
    Vote::factory()->create(['photo_id' => $suspecte->id]);
    Vote::factory()->create(['photo_id' => $saine->id]);

    $this->actingAs($this->moderator)->get(route('regie.revelation'))
        ->assertOk()
        ->assertSeeInOrder(['Suspecte', 'Saine'])
        ->assertSee('2 votes suspects')
        ->assertSee('aucun vote suspect');
});

it('refuse de valider tant que les votes ne sont pas clos', function () {
    EventPhase::set(Phase::Open);
    Photo::factory()->approved()->create();

    $this->actingAs($this->moderator)->post(route('regie.revelation.valider'))
        ->assertRedirect(route('regie.revelation'))
        ->assertSessionHas('erreur');

    expect(Setting::getValue('winner_photo_id'))->toBeNull();
});

it('valide le classement en phase frozen : gagnant en settings + journal', function () {
    EventPhase::set(Phase::Frozen);
    $gagnante = Photo::factory()->approved()->create(['display_name' => 'Gagnante']);
    $gagnante->forceFill(['votes_count' => 7])->save();
    Photo::factory()->approved()->create();

    $this->actingAs($this->moderator)->post(route('regie.revelation.valider'))
        ->assertRedirect(route('regie.revelation'))
        ->assertSessionHas('succes');

    expect(Setting::getValue('winner_photo_id'))->toBe($gagnante->id)
        ->and(Setting::getValue('ranking_validated_by'))->toBe('Christian')
        ->and(AuditLog::query()->where('action', 'ranking.validated')->exists())->toBeTrue();
});

it('refuse de lancer la révélation sans validation humaine', function () {
    EventPhase::set(Phase::Frozen);
    Photo::factory()->approved()->create();

    $this->actingAs($this->moderator)->post(route('regie.revelation.lancer'))
        ->assertSessionHas('erreur');

    expect(EventPhase::current())->toBe(Phase::Frozen);
});

it('lance la révélation après validation : phase reveal + journal', function () {
    EventPhase::set(Phase::Frozen);
    Photo::factory()->approved()->create();

    $this->actingAs($this->moderator)->post(route('regie.revelation.valider'));
    $this->actingAs($this->moderator)->post(route('regie.revelation.lancer'))
        ->assertSessionHas('succes');

    expect(EventPhase::current())->toBe(Phase::Reveal)
        ->and(AuditLog::query()->where('action', 'reveal.launched')->exists())->toBeTrue();
});

it('prévient si le classement a changé depuis la validation', function () {
    EventPhase::set(Phase::Frozen);
    $premiere = Photo::factory()->approved()->create(['display_name' => 'Première']);
    $premiere->forceFill(['votes_count' => 5])->save();
    $seconde = Photo::factory()->approved()->create(['display_name' => 'Nouvelle première']);

    $this->actingAs($this->moderator)->post(route('regie.revelation.valider'));

    // Le classement bascule après validation (retrait, correction…)
    $seconde->forceFill(['votes_count' => 9])->save();

    $this->actingAs($this->moderator)->get(route('regie.revelation'))
        ->assertSee('Le classement a changé depuis la validation');
});

it('sert le gagnant validé à l’écran, prioritaire sur le premier du classement', function () {
    EventPhase::set(Phase::Frozen);
    $populaire = Photo::factory()->approved()->create(['display_name' => 'Populaire']);
    $populaire->forceFill(['votes_count' => 9])->save();
    $validee = Photo::factory()->approved()->create(['display_name' => 'Validée']);
    $validee->forceFill(['votes_count' => 4])->save();

    // La régie valide « Validée » (après disqualification de fait de l'autre, par exemple)
    Setting::setValue('winner_photo_id', $validee->id);
    EventPhase::set(Phase::Reveal);

    $reponse = $this->getJson(route('api.ecran', ['cle' => config('tembo.screen_key')]));

    expect($reponse->json('gagnant.nom'))->toBe('Validée')
        ->and($reponse->json('top.0.nom'))->toBe('Populaire');
});

it('propose le passage au remerciement pendant la révélation', function () {
    EventPhase::set(Phase::Reveal);
    Photo::factory()->approved()->create();

    $this->actingAs($this->moderator)->get(route('regie.revelation'))
        ->assertSee('Terminer — écran de remerciement');
});
