<?php

namespace App\Models;

use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    protected $fillable = [
        'guest_session_id',
        'photo_id',
        'device_hash',
    ];

    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
