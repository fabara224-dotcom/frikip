<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletionRequest extends Model
{
    protected $fillable = ['contact', 'profile_name', 'scope', 'note', 'status', 'admin_note', 'done_at'];

    protected $casts = ['done_at' => 'datetime'];

    public const STATUSES = [
        'nouvelle' => 'Nouvelle',
        'verification' => 'Vérification en cours',
        'terminee' => 'Terminée',
        'refusee' => 'Refusée',
    ];

    public const SCOPES = [
        'account' => 'Tout le compte et les données',
        'activity' => "Historique d'activité seulement",
        'videos' => 'Certaines vidéos',
    ];

    public function targetLabel(): string
    {
        return self::SCOPES[$this->scope] ?? $this->scope;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Demande en retard : plus de 7 jours sans être terminée. */
    public function isLate(): bool
    {
        return ! in_array($this->status, ['terminee', 'refusee'], true) && $this->created_at?->lt(now()->subDays(7));
    }
}
