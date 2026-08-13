<?php

namespace App\Models;

use Database\Factories\AccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessToken extends Model
{
    /** @use HasFactory<AccessTokenFactory> */
    use HasFactory;

    /** Un jeton ne se modifie jamais : il naît, il expire. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'token',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    /**
     * Jetons acceptés à cet instant (2 jetons en glissement pendant la rotation).
     *
     * @param  Builder<AccessToken>  $query
     */
    #[Scope]
    protected function currentlyValid(Builder $query): void
    {
        $query->where('valid_from', '<=', now())->where('valid_until', '>=', now());
    }
}
