<?php

namespace App\Models;

use App\Enums\PhotoStatus;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'guest_session_id',
        'display_name',
        'path',
        'thumb_path',
        'status',
        'reject_reason',
        'moderated_by',
        'moderated_at',
        'consent_event',
        'consent_reuse',
    ];

    protected $attributes = [
        'status' => 'pending',
        'consent_reuse' => false,
        'votes_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => PhotoStatus::class,
            'consent_event' => 'boolean',
            'consent_reuse' => 'boolean',
            'moderated_at' => 'datetime',
        ];
    }

    /**
     * URL signée temporaire : seul moyen d'afficher une image, le disque
     * étant privé. Durée couvrant toute la soirée.
     */
    public function signedImageUrl(string $variante = 'vignette'): string
    {
        return URL::temporarySignedRoute('photos.image', now()->addHours(6), [
            'photo' => $this->id,
            'variante' => $variante,
        ]);
    }

    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @param Builder<Photo> $query */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', PhotoStatus::Approved);
    }

    /** @param Builder<Photo> $query */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', PhotoStatus::Pending);
    }
}
