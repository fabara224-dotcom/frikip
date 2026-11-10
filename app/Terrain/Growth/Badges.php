<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Statuts de vérification distincts (cahier des modifications, partie 4) — trois contrôles différents à ne pas confondre :
 *  - identite       : la pièce d'identité a été contrôlée
 *  - recruteur      : l'affiliation au club, à l'académie ou à l'agence a été confirmée
 *  - agent_fifa     : la licence figure dans le répertoire officiel des agents de la FIFA
 *  - enregistrement : l'enregistrement sportif du joueur auprès d'un club ou d'une fédération est confirmé
 * Chaque badge garde sa date et sa source ; il peut être retiré (avec la raison) et contesté par le membre.
 */
class Badges
{
    public const TYPES = [
        'identite' => ['Identité vérifiée', '🪪'],
        'recruteur' => ['Recruteur vérifié', '✅'],
        'agent_fifa' => ['Agent FIFA vérifié', '🌍'],
        'enregistrement' => ['Enregistrement sportif confirmé', '📋'],
    ];

    public function grant(string $subject, string $type, ?string $source, ?string $admin): void
    {
        if (! isset(self::TYPES[$type])) {
            return;
        }
        DB::table('verification_badges')->where('subject', $subject)->where('type', $type)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => 'Remplacé par une nouvelle vérification', 'updated_at' => now()]);
        DB::table('verification_badges')->insert([
            'subject' => $subject, 'type' => $type, 'source' => $source ? mb_substr($source, 0, 200) : null, 'admin_name' => $admin,
            'verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function revoke(int $id, string $reason): void
    {
        DB::table('verification_badges')->where('id', $id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_reason' => mb_substr($reason, 0, 300), 'updated_at' => now()]);
    }

    /** Badges en cours pour ces identifiants (compte et profil de joueur). */
    public function active(array $subjects): array
    {
        $subjects = array_values(array_filter($subjects));
        if (! $subjects) {
            return [];
        }
        try {
            return DB::table('verification_badges')->whereIn('subject', $subjects)->whereNull('revoked_at')->orderBy('verified_at')->get()
                ->unique('type')->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** Tous les badges, y compris retirés (admin, page Vérification du membre). */
    public function history(array $subjects): array
    {
        $subjects = array_values(array_filter($subjects));
        try {
            return $subjects ? DB::table('verification_badges')->whereIn('subject', $subjects)->orderByDesc('id')->get()->all() : [];
        } catch (Throwable) {
            return [];
        }
    }

    public static function label(string $type): string
    {
        return __(self::TYPES[$type][0] ?? $type);
    }
}
