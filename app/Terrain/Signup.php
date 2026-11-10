<?php

namespace App\Terrain;

use Illuminate\Validation\Rule;

/**
 * Création de compte, commune à l'inscription par e-mail, par téléphone et avec Google / Facebook / TikTok.
 * Chaque type de compte correspond à un acteur du foot, avec ses propres questions.
 */
class Signup
{
    /** type => [rôle du compte, titre, explication courte, ce que la personne pourra faire, icône] */
    public const TYPES = [
        'joueur' => ['athlete', 'Joueur ou joueuse', 'Je montre mon talent en vidéo pour être repéré.', 'Ton profil de joueur, tes vidéos, tes statistiques et les messages des recruteurs.', '⚽'],
        'proche' => ['athlete', 'Parent ou proche d’un joueur', 'Je crée le profil de mon fils, mon frère, un ami… et je publie pour lui.', 'Le profil du joueur que tu soutiens, tenu par toi, avec tes nom et lien affichés.', '👨‍👦'],
        'supporter' => ['fan', 'Supporter', 'Je regarde, je commente les matchs et je soutiens mes joueurs.', 'Le fil de vidéos, les lives, les salons de match, les votes dans les défis.', '📣'],
        'club' => ['recruiter', 'Club ou académie', 'Je présente ma structure, je publie des détections et je recrute.', 'La page de ton club, tes annonces de détection, la recherche de joueurs.', '🏟️'],
        'recruteur' => ['recruiter', 'Recruteur ou scout', 'Je cherche des talents et je les contacte.', 'La recherche avancée, les listes de joueurs suivis, les messages et les appels.', '🔎'],
        'agent' => ['recruiter', 'Agent de football', 'Je représente des joueurs (licence FIFA).', 'La recherche de joueurs et un badge « Agent FIFA vérifié » après contrôle de ta licence.', '💼'],
        'acteur' => ['athlete', 'Autre acteur du foot', 'Entraîneur, arbitre, médecin, kiné, préparateur, analyste, journaliste…', 'Ton profil professionnel et tes vidéos (exercices, arbitrage, conseils santé…).', '🧑‍🏫'],
    ];

    /** Métiers proposés à « Autre acteur du foot ». */
    public const PROFESSIONS = [
        'Entraîneur', 'Entraîneur des gardiens', 'Préparateur physique', 'Arbitre', 'Arbitre assistant',
        'Médecin du sport', 'Kinésithérapeute', 'Nutritionniste', 'Psychologue du sport', 'Analyste vidéo',
        'Journaliste sportif', 'Commentateur', 'Photographe ou vidéaste', 'Dirigeant de club', 'Intendant', 'Autre métier du foot',
    ];

    /** Types de structures pour « Club ou académie ». */
    public const CLUB_TYPES = ['Club', 'Académie', 'Centre de formation', 'Université', 'Fédération', 'Sélection nationale'];

    public static function valid(?string $type): bool
    {
        return isset(self::TYPES[(string) $type]);
    }

    public static function role(string $type): string
    {
        return self::TYPES[$type][0];
    }

    /** Règles de validation pour ce type. $credentials : e-mail + mot de passe demandés (inscription par e-mail). */
    public static function rules(string $type, bool $credentials, int $age = 0): array
    {
        $minAge = (int) config('terrain.min_age', 13);
        $rules = [
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'name' => ['required', 'string', 'max:120'],
            'country' => ['required', Rule::in(SportFields::COUNTRIES)],
            'city' => ['nullable', 'string', 'max:120'],
            'terms' => ['accepted'],
        ];
        if ($credentials) {
            $rules += ['email' => ['required', 'email', 'max:200'], 'password' => ['required', 'string', 'min:8']];
        }
        $football = SportFields::FIELDS['Football'];
        if (in_array($type, ['joueur', 'proche'], true)) {
            $rules += [
                'age' => ['required', 'integer', "min:{$minAge}", 'max:60'],
                'poste' => ['required', Rule::in($football[0]['options'])],
                'pied' => ['required', Rule::in($football[1]['options'])],
                'club' => ['nullable', 'string', 'max:120'],
                'guardian_phone' => [$age > 0 && $age < 18 ? 'required' : 'nullable', 'string', 'max:30'],
            ];
        }
        if ($type === 'proche') {
            $rules += ['relation' => ['required', Rule::in(Roles::RELATIONS)], 'manager_name' => ['required', 'string', 'max:120']];
        }
        if ($type === 'supporter') {
            $rules += ['favorite_club' => ['nullable', 'string', 'max:120']];
        }
        if ($type === 'club') {
            $rules += ['org_type' => ['required', Rule::in(self::CLUB_TYPES)]];
        }
        if ($type === 'recruteur') {
            $rules += ['organization' => ['nullable', 'string', 'max:150']];
        }
        if ($type === 'agent') {
            $rules += ['licence_number' => ['nullable', 'string', 'max:60']];
        }
        if ($type === 'acteur') {
            $rules += [
                'profession' => ['required', Rule::in(self::PROFESSIONS)],
                'age' => ['required', 'integer', 'min:18', 'max:90'],
                'organization' => ['nullable', 'string', 'max:150'],
            ];
        }

        return $rules;
    }

    public static function messages(): array
    {
        $minAge = (int) config('terrain.min_age', 13);

        return [
            'type.required' => 'Choisis qui tu es (étape 1).',
            'name.required' => 'Indique le nom.',
            'country.required' => 'Choisis le pays.',
            'terms.accepted' => 'Accepte les conditions d’utilisation et la politique de confidentialité.',
            'email.required' => 'Indique ton adresse e-mail.',
            'password.min' => 'Le mot de passe doit faire au moins 8 caractères.',
            'age.required' => 'Indique l’âge.',
            'age.min' => "Il faut avoir au moins {$minAge} ans.",
            'poste.required' => 'Choisis le poste.',
            'pied.required' => 'Choisis le pied fort.',
            'guardian_phone.required' => 'Moins de 18 ans : le téléphone d’un parent ou tuteur est obligatoire.',
            'relation.required' => 'Indique ton lien avec le joueur (frère, mère, ami…).',
            'manager_name.required' => 'Indique ton nom : il apparaît sur le profil (« profil tenu par … »).',
            'org_type.required' => 'Indique le type de structure (club, académie…).',
            'profession.required' => 'Choisis ton métier.',
        ];
    }

    /** Fiche transmise à DataSource::signUp() à partir des réponses validées. */
    public static function profile(string $type, array $d): array
    {
        $country = $d['country'] ?? Region::viewer();
        $p = ['name' => trim($d['name']), 'country' => $country, 'city' => $d['city'] ?? null, 'type' => $type];
        if (in_array($type, ['joueur', 'proche'], true)) {
            $fields = [];
            foreach (SportFields::FIELDS['Football'] as $f) {
                $fields[$f['label']] = $f['type'] === 'choice' ? $f['options'][0] : '–';
            }
            $fields['Poste'] = $d['poste'];
            $fields['Pied fort'] = $d['pied'];
            $fields['Disponible pour des essais'] = SportFields::FIELDS['Football'][6]['options'][0];
            $p += ['age' => (int) $d['age'], 'sport' => 'Football', 'fields' => $fields, 'club' => $d['club'] ?? null,
                'guardian_phone' => $d['guardian_phone'] ?? null, 'kind' => 'joueur'];
            if ($type === 'proche') {
                $p += ['managed_by' => $d['relation'], 'manager_name' => $d['manager_name']];
            }
        } elseif ($type === 'acteur') {
            $p += ['age' => (int) $d['age'], 'sport' => 'Football', 'kind' => 'pro', 'profession' => $d['profession'], 'organization' => $d['organization'] ?? null,
                'club' => $d['organization'] ?? null,
                'fields' => ['Métier' => $d['profession'], 'Expérience (années)' => '–', 'Diplômes et licences' => '–', 'Structure actuelle' => ($d['organization'] ?? '') ?: '–']];
        } elseif ($type === 'supporter') {
            $p += ['favorite_club' => $d['favorite_club'] ?? null];
        } elseif ($type === 'club') {
            $p += ['org_type' => $d['org_type']];
        } elseif ($type === 'recruteur') {
            $p += ['org_type' => 'Recruteur ou scout', 'organization' => $d['organization'] ?? null];
        } elseif ($type === 'agent') {
            $p += ['org_type' => 'Agent de football', 'licence_number' => $d['licence_number'] ?? null];
        }

        return $p;
    }

    /** Page d'arrivée après l'inscription. */
    public static function landing(string $type): string
    {
        return match ($type) {
            'joueur', 'proche', 'acteur' => 'publish',
            'club', 'recruteur', 'agent' => 'search',
            default => 'feed',
        };
    }

    public static function welcome(string $type): string
    {
        return match ($type) {
            'joueur', 'proche' => __('Bienvenue ! Publie une première vidéo : c’est elle que les recruteurs verront.'),
            'acteur' => __('Bienvenue ! Publie une vidéo pour présenter ton travail aux joueurs et aux clubs.'),
            'club', 'recruteur', 'agent' => __('Bienvenue ! Cherche tes premiers talents. Pense à faire vérifier ton compte pour écrire aux moins de 18 ans.'),
            default => __('Bienvenue ! Suis tes joueurs préférés, commente les matchs et vote dans les défis.'),
        };
    }
}
