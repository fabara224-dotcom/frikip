<?php

namespace App\Terrain;

/**
 * Champs propres à chaque sport (identique à lib/data/sport_fields.dart dans l'app).
 * Pour ajouter un sport : ajoute une entrée ici ET dans l'app.
 */
class SportFields
{
    /** « Étoile de Conakry (exemple) » → « EC » */
    public static function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/[\s\-’\']+/u', trim(preg_replace('/\(.*?\)/u', '', $name))), fn ($w) => mb_strlen($w) > 2 || ctype_upper(mb_substr($w, 0, 1))));
        $ini = '';
        foreach (array_slice($words ?: [$name], 0, 2) as $w) {
            $ini .= mb_substr($w, 0, 1);
        }

        return mb_strtoupper(\Illuminate\Support\Str::ascii($ini));
    }

    /** type : text | number | choice */
    public const FIELDS = [
        'Football' => [
            ['label' => 'Poste', 'type' => 'choice', 'options' => ['Gardien', 'Défenseur central', 'Latéral', 'Milieu défensif', 'Milieu offensif', 'Ailier', 'Attaquant']],
            ['label' => 'Pied fort', 'type' => 'choice', 'options' => ['Droit', 'Gauche', 'Les deux']],
            ['label' => 'Buts cette saison', 'type' => 'number'],
            ['label' => 'Passes décisives', 'type' => 'number'],
        ],
        'Basket' => [
            ['label' => 'Poste', 'type' => 'choice', 'options' => ['Meneur', 'Arrière', 'Ailier', 'Ailier fort', 'Pivot']],
            ['label' => 'Points / match', 'type' => 'number'],
            ['label' => 'Rebonds / match', 'type' => 'number'],
            ['label' => 'Détente verticale (cm)', 'type' => 'number'],
        ],
        'Athlétisme' => [
            ['label' => 'Discipline', 'type' => 'choice', 'options' => ['100 m', '200 m', '400 m', '800 m', '1500 m', 'Saut en longueur', 'Saut en hauteur', 'Lancer']],
            ['label' => 'Meilleure performance', 'type' => 'text'],
            ['label' => 'Meilleur résultat', 'type' => 'text'],
        ],
        'Lutte' => [
            ['label' => 'Catégorie', 'type' => 'choice', 'options' => ['Lutte simple', 'Lutte avec frappe', 'Lutte olympique']],
            ['label' => 'Victoires', 'type' => 'number'],
            ['label' => 'Défaites', 'type' => 'number'],
        ],
        'Judo' => [
            ['label' => 'Catégorie de poids', 'type' => 'choice', 'options' => ['-48 kg', '-52 kg', '-57 kg', '-60 kg', '-66 kg', '-73 kg', '-81 kg', '-90 kg', '+90 kg']],
            ['label' => 'Grade', 'type' => 'text'],
            ['label' => 'Victoires', 'type' => 'number'],
            ['label' => 'Défaites', 'type' => 'number'],
        ],
        'Handball' => [
            ['label' => 'Poste', 'type' => 'choice', 'options' => ['Gardien', 'Ailier', 'Arrière', 'Demi-centre', 'Pivot']],
            ['label' => 'Buts / match', 'type' => 'number'],
            ['label' => 'Main forte', 'type' => 'choice', 'options' => ['Droite', 'Gauche']],
        ],
        'Tennis' => [
            ['label' => 'Classement', 'type' => 'text'],
            ['label' => 'Main', 'type' => 'choice', 'options' => ['Droitier', 'Gaucher']],
            ['label' => 'Surface préférée', 'type' => 'choice', 'options' => ['Dur', 'Terre battue', 'Gazon']],
        ],
        'Natation' => [
            ['label' => 'Nage', 'type' => 'choice', 'options' => ['Crawl', 'Dos', 'Brasse', 'Papillon', '4 nages']],
            ['label' => 'Meilleur temps', 'type' => 'text'],
            ['label' => 'Meilleur résultat', 'type' => 'text'],
        ],
    ];

    public const COLORS = [
        'Football' => ['#1F7A4A', '#0E4A2B'], 'Basket' => ['#C2561A', '#7A2E0B'], 'Athlétisme' => ['#B3261E', '#5E1410'],
        'Lutte' => ['#7A5524', '#3A2510'], 'Judo' => ['#2B3A8C', '#141C4A'], 'Handball' => ['#0F6E7A', '#073B42'],
        'Tennis' => ['#6F7D10', '#3A4207'], 'Natation' => ['#1565A8', '#0A3358'],
    ];

    /** Pays proposés partout sur le site (détails : App\Terrain\Region) : les 16 pays d'Afrique de l'Ouest, puis quelques voisins. */
    public const COUNTRIES = ['Guinée', 'Sénégal', "Côte d'Ivoire", 'Mali', 'Burkina Faso', 'Bénin', 'Togo', 'Ghana', 'Nigeria', 'Niger', 'Libéria',
        'Sierra Leone', 'Gambie', 'Guinée-Bissau', 'Cap-Vert', 'Mauritanie', 'Cameroun', 'Maroc', 'RDC'];

    /** @return list<string> */
    public static function sports(): array
    {
        return array_keys(self::FIELDS);
    }

    /** @return array{0: string, 1: string} */
    public static function color(string $sport): array
    {
        return self::COLORS[$sport] ?? ['#3B4A42', '#1E2823'];
    }
}
