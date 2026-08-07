<?php

use App\Enums\Phase;

it('autorise publication et vote selon la machine à états du brief', function (Phase $phase, bool $publication, bool $vote) {
    expect($phase->allowsPublishing())->toBe($publication)
        ->and($phase->allowsVoting())->toBe($vote);
})->with([
    'setup' => [Phase::Setup, false, false],
    'open' => [Phase::Open, true, true],
    'vote_only' => [Phase::VoteOnly, false, true],
    'frozen' => [Phase::Frozen, false, false],
    'reveal' => [Phase::Reveal, false, false],
    'closed' => [Phase::Closed, false, false],
]);
