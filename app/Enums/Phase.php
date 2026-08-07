<?php

namespace App\Enums;

/**
 * Machine à états de la soirée : setup → open → vote_only → frozen → reveal → closed.
 * La phase courante est stockée dans la table settings et pilotée depuis le back-office.
 */
enum Phase: string
{
    case Setup = 'setup';
    case Open = 'open';
    case VoteOnly = 'vote_only';
    case Frozen = 'frozen';
    case Reveal = 'reveal';
    case Closed = 'closed';

    public function allowsPublishing(): bool
    {
        return $this === self::Open;
    }

    public function allowsVoting(): bool
    {
        return $this === self::Open || $this === self::VoteOnly;
    }

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Préparation',
            self::Open => 'Publication + vote',
            self::VoteOnly => 'Vote seul',
            self::Frozen => 'Votes clos',
            self::Reveal => 'Révélation',
            self::Closed => 'Terminé',
        };
    }
}
