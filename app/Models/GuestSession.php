<?php

namespace App\Models;

use Database\Factories\GuestSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GuestSession extends Model
{
    /** @use HasFactory<GuestSessionFactory> */
    use HasFactory;

    use HasUlids;

    /** La table n'a pas de colonne updated_at : une session ne se modifie pas, elle expire ou se révoque. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'device_hash',
        'ip_hash',
        'token_used',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function photo(): HasOne
    {
        return $this->hasOne(Photo::class);
    }

    /** Un invité vote pour autant de photos qu'il veut, une seule fois par photo. */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
