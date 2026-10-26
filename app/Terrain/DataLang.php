<?php

namespace App\Terrain;

use Illuminate\Support\Facades\App;

/**
 * Traduction des contenus (titres de vidéos, fiches des sportifs, sports, pays…) quand le site est en anglais.
 *  - vidéos publiées : titre traduit enregistré à la publication (video_meta.title_en / title_fr) ;
 *  - profils d'exemple : dictionnaire resources/data/demo-en.json + règles pour les nombres.
 * Dans Blade : @td($texte) affiche le texte dans la langue de la page.
 */
class DataLang
{
    private static ?array $dict = null;

    private const SPORTS = ['Basket' => 'Basketball', 'Athlétisme' => 'Athletics', 'Natation' => 'Swimming', 'Lutte' => 'Wrestling', 'Tous' => 'All'];

    private const COUNTRIES = [
        'Guinée' => 'Guinea', 'Sénégal' => 'Senegal', 'Cameroun' => 'Cameroon', 'RDC' => 'DR Congo', 'Maroc' => 'Morocco',
        'Bénin' => 'Benin', 'Libéria' => 'Liberia', 'États-Unis' => 'United States', 'France' => 'France', "Côte d'Ivoire" => "Côte d'Ivoire",
        'Gambie' => 'The Gambia', 'Guinée-Bissau' => 'Guinea-Bissau', 'Cap-Vert' => 'Cape Verde', 'Mauritanie' => 'Mauritania',
    ];

    public static function en(): bool
    {
        return App::getLocale() === 'en';
    }

    public static function t(?string $s): string
    {
        $s = (string) $s;
        if ($s === '' || ! self::en()) {
            return $s;
        }

        return self::toEnglish($s);
    }

    public static function toEnglish(string $s): string
    {
        self::$dict ??= json_decode((string) @file_get_contents(resource_path('data/demo-en.json')), true) ?: [];
        if (isset(self::$dict[$s])) {
            return self::$dict[$s];
        }
        if (isset(self::SPORTS[$s])) {
            return self::SPORTS[$s];
        }
        if (isset(self::COUNTRIES[$s])) {
            return self::COUNTRIES[$s];
        }
        // « (exemple) » dans les noms de clubs d'exemple
        if (str_ends_with($s, ' (exemple)')) {
            return self::toEnglish(substr($s, 0, -10)).' (example)';
        }
        $r = self::rules($s);

        return $r ?? $s;
    }

    /** Règles pour les textes avec des nombres (« 14 buts · 9 passes », « 10,4 points par match »…). */
    private static function rules(string $s): ?string
    {
        $pl = fn ($n, $one, $many) => ((float) str_replace(',', '.', $n) == 1 ? $one : $many);
        $dec = fn ($n) => str_replace(',', '.', $n);
        $rules = [
            '/^(\d+) buts? · (\d+) passes?$/u' => fn ($m) => "{$m[1]} ".$pl($m[1], 'goal', 'goals')." · {$m[2]} ".$pl($m[2], 'assist', 'assists'),
            '/^(\d+) buts? – saison 2025-26$/u' => fn ($m) => "{$m[1]} goals – 2025-26 season",
            '/^(\d+) buts? · (\d+) duels gagnés$/u' => fn ($m) => "{$m[1]} goals · {$m[2]} duels won",
            '/^(\d+) passes? décisives$/u' => fn ($m) => "{$m[1]} ".$pl($m[1], 'assist', 'assists'),
            '/^(\d+) penalties arrêtés( cette saison)?$/u' => fn ($m) => "{$m[1]} ".$pl($m[1], 'penalty', 'penalties').' saved'.(! empty($m[2]) ? ' this season' : ''),
            '/^(\d+) clean sheets( cette saison)?$/u' => fn ($m) => "{$m[1]} clean sheets".(! empty($m[2]) ? ' this season' : ''),
            '/^(\d+) victoires, (\d+) défaites$/u' => fn ($m) => "{$m[1]} wins, {$m[2]} ".$pl($m[2], 'loss', 'losses'),
            '/^(\d+) victoires$/u' => fn ($m) => "{$m[1]} wins",
            '/^(\d+) V · (\d+) D$/u' => fn ($m) => "{$m[1]} W · {$m[2]} L",
            '/^([\d,]+) rebonds par match$/u' => fn ($m) => $dec($m[1]).' rebounds per game',
            '/^([\d,]+) points par match$/u' => fn ($m) => $dec($m[1]).' points per game',
            '/^([\d,]+) buts? par match$/u' => fn ($m) => $dec($m[1]).' goals per game',
            '/^Pointe à ([\d,]+) km\/h$/u' => fn ($m) => 'Top speed '.$dec($m[1]).' km/h',
            '/^(\d+) cm de détente$/u' => fn ($m) => "{$m[1]} cm vertical leap",
            '/^(\d+) duels gagnés cette saison$/u' => fn ($m) => "{$m[1]} duels won this season",
            '/^(\d+) m (crawl|dos|papillon|brasse|4 nages) – finale$/u' => fn ($m) => "{$m[1]} m ".['crawl' => 'freestyle', 'dos' => 'backstroke', 'papillon' => 'butterfly', 'brasse' => 'breaststroke', '4 nages' => 'individual medley'][$m[2]].' – final',
            '/^(\d+) m – finale nationale$/u' => fn ($m) => "{$m[1]} m – national final",
            '/^N°(\d+) nationale?( juniors)?$/u' => fn ($m) => "National No. {$m[1]}".(! empty($m[2]) ? ' (juniors)' : ''),
            '/^(\d+),(\d+)( (?:m|km|km\/h|cm|kg|%))?$/u' => fn ($m) => "{$m[1]}.{$m[2]}".($m[3] ?? ''),
        ];
        foreach ($rules as $re => $fn) {
            if (preg_match($re, $s, $m)) {
                return $fn($m);
            }
        }

        return null;
    }

    /** Titre (ou texte affiché) d'une vidéo dans la langue de la page. */
    public static function video(array $v, string $field = 'title'): string
    {
        $locale = App::getLocale();
        $own = $v['i18n'][$field][$locale] ?? null;
        if ($own) {
            return $own;
        }

        return self::t($v[$field] ?? '');
    }

    /** Valeurs d'une fiche sportive : clés et valeurs traduites. */
    public static function fields(array $fields): array
    {
        if (! self::en()) {
            return $fields;
        }
        $out = [];
        foreach ($fields as $k => $v) {
            $out[self::toEnglish((string) $k)] = self::toEnglish((string) $v);
        }

        return $out;
    }
}
