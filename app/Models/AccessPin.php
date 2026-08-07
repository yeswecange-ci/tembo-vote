<?php

namespace App\Models;

use Database\Factories\AccessPinFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessPin extends Model
{
    /** @use HasFactory<AccessPinFactory> */
    use HasFactory;

    /** Un PIN ne se modifie jamais : il naît, il expire. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'code',
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
     * Codes acceptés à cet instant (2 codes en glissement pendant la rotation).
     *
     * @param  Builder<AccessPin>  $query
     */
    #[Scope]
    protected function currentlyValid(Builder $query): void
    {
        $query->where('valid_from', '<=', now())->where('valid_until', '>=', now());
    }
}
