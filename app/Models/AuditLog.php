<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /** Un journal d'audit ne se modifie jamais après écriture. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'action',
        'actor',
        'target_type',
        'target_id',
        'ip_hash',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /** Libellé lisible d'une action, pour le tableau de bord. */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'regie.login' => 'Connexion à la régie',
            'regie.logout' => 'Déconnexion de la régie',
            'photo.approved' => 'Photo validée',
            'photo.rejected' => 'Photo refusée',
            'photo.removed' => 'Photo retirée de la galerie',
            'phase.changed' => 'Changement de phase',
            'token.rotated' => 'Nouveau QR d’accès',
            default => $this->action,
        };
    }

    /**
     * Point d'entrée unique du journal : toute action de la régie passe par ici.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public static function write(string $action, string $actor, ?string $targetType = null, ?string $targetId = null, ?array $meta = null): self
    {
        return static::query()->create([
            'action' => $action,
            'actor' => $actor,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_hash' => request()?->ip() !== null ? hash('sha256', request()->ip().'|'.config('app.key')) : null,
            'meta' => $meta,
        ]);
    }
}
