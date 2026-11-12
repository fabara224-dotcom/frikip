<?php

namespace App\Terrain;

/**
 * Types de comptes FootPro :
 *  - athlete   : sportif (ou profil d'un sportif tenu par un proche : frère, parent, ami…)
 *  - recruiter : club, académie, agent, université, sélection
 *  - fan       : supporter qui regarde, aime, commente, suit et vote (ne publie pas, n'écrit pas en premier)
 */
class Roles
{
    public const ALL = ['athlete', 'recruiter', 'fan'];

    /** Lien du proche qui tient le profil d'un sportif. */
    public const RELATIONS = ['Frère', 'Sœur', 'Père', 'Mère', 'Oncle', 'Tante', 'Cousin', 'Cousine', 'Ami', 'Amie', 'Entraîneur', 'Autre'];

    /** Types de structures qui recrutent. */
    public const ORG_TYPES = ['Club', 'Académie', 'Agent', 'Université', 'Fédération', 'Sélection nationale', 'Centre de formation', 'Autre'];

    public static function label(?string $role): string
    {
        return ['athlete' => __('Joueur'), 'recruiter' => __('Recruteur'), 'fan' => __('Supporter')][$role] ?? __('Membre');
    }

    /** Libellé précis d'un compte : « Joueur », « Arbitre », « Agent de football », « Club »… */
    public static function labelFor(?array $u): string
    {
        if (! $u) {
            return __('Membre');
        }
        if (! empty($u['profession'])) {
            return __($u['profession']);
        }
        if (($u['role'] ?? null) === 'recruiter' && ! empty($u['org_type'])) {
            return __($u['org_type']);
        }

        return self::label($u['role'] ?? null);
    }
}
