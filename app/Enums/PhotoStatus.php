<?php

namespace App\Enums;

enum PhotoStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de validation',
            self::Approved => 'Publiée',
            self::Rejected => 'Refusée',
        };
    }
}
