<?php

namespace App\Terrain\Growth;

use App\Terrain\DataSource;
use App\Terrain\RealScores;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Le score affiché en haut du fil est choisi par chaque visiteur :
 *  - auto   : le premier match en direct (comportement par défaut) ;
 *  - team   : son équipe (match du jour, en direct d'abord) ;
 *  - league : une compétition (Premier League, Ligue des champions, matchs locaux…) ;
 *  - match  : un match précis (bouton « Afficher dans mon fil » sur la page du match) ;
 *  - off    : aucun score.
 * Le choix est gardé dans un cookie (même sans compte) et dans le compte du membre (tous ses appareils).
 */
class ScoreChoice
{
    public const COOKIE = 'frikip_score';

    public const MODES = ['auto', 'team', 'league', 'match', 'off'];

    public function __construct(private DataSource $data, private RealScores $real)
    {
    }

    /** @return array{mode: string, value: string, label: string} */
    public function get(): array
    {
        $raw = request()->cookie(self::COOKIE);
        if (! $raw && ($key = $this->data->user()['id'] ?? null)) {
            try {
                $raw = DB::table('members')->where('user_key', $key)->value('score_pref');
            } catch (Throwable) {
            }
        }
        $c = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        $mode = in_array($c['mode'] ?? '', self::MODES, true) ? $c['mode'] : 'auto';

        return ['mode' => $mode, 'value' => (string) ($c['value'] ?? ''), 'label' => (string) ($c['label'] ?? '')];
    }

    /** Enregistre le choix (compte du membre) et renvoie la valeur du cookie à poser. */
    public function store(string $mode, string $value = '', string $label = ''): string
    {
        $json = json_encode(['mode' => $mode, 'value' => mb_substr($value, 0, 80), 'label' => mb_substr($label, 0, 120)], JSON_UNESCAPED_UNICODE);
        if ($key = $this->data->user()['id'] ?? null) {
            try {
                DB::table('members')->where('user_key', $key)->update(['score_pref' => $json]);
            } catch (Throwable) {
            }
        }

        return $json;
    }

    /** Tous les matchs du jour connus du site (grands championnats + matchs locaux). */
    public function today(): array
    {
        $out = [];
        try {
            if ($this->real->enabled()) {
                $out = $this->real->today();
            }
        } catch (Throwable) {
        }
        try {
            // Matchs locaux : en direct, ou programmés / joués entre hier et demain
            $from = time() - 18 * 3600;
            $to = time() + 36 * 3600;
            foreach ($this->data->matches() as $m) {
                if ($m['status'] === 'live' || (($m['ts'] ?? 0) >= $from && ($m['ts'] ?? 0) <= $to)) {
                    $out[] = ['league_id' => 'local'] + $m;
                }
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /** Le match à afficher en haut du fil selon le choix du visiteur (null = rien). */
    public function pick(): ?array
    {
        $c = $this->get();
        if ($c['mode'] === 'off') {
            return null;
        }
        if ($c['mode'] === 'match' && $c['value'] !== '') {
            try {
                $m = str_starts_with($c['value'], 'api-') ? $this->real->match($c['value']) : $this->data->match($c['value']);
            } catch (Throwable) {
                $m = null;
            }
            // Un match terminé depuis plus de 12 heures n'est plus affiché
            if ($m && ! ($m['status'] === 'finished' && ($m['ts'] ?? time()) < time() - 12 * 3600)) {
                return $m;
            }

            return null;
        }
        $list = $this->today();
        if ($c['mode'] === 'team' && $c['value'] !== '') {
            $t = mb_strtolower($c['value']);
            $list = array_values(array_filter($list, fn ($m) => str_contains(mb_strtolower($m['home'].' '.$m['away']), $t)));
        } elseif ($c['mode'] === 'league' && $c['value'] !== '') {
            $list = array_values(array_filter($list, fn ($m) => (string) ($m['league_id'] ?? '') === $c['value']));
        } else {
            return collect($list)->firstWhere('status', 'live');   // automatique : le premier match en direct
        }
        // Équipe ou compétition : en direct d'abord, puis le prochain match du jour, puis le dernier résultat
        usort($list, fn ($a, $b) => [self::rank($a), $a['ts'] ?? 0] <=> [self::rank($b), $b['ts'] ?? 0]);

        return $list[0] ?? null;
    }

    private static function rank(array $m): int
    {
        return ['live' => 0, 'upcoming' => 1, 'finished' => 2][$m['status']] ?? 3;
    }

    /** Texte court du bandeau : « 67' Liverpool 1–0 Arsenal », « 20:45 Real – Bayern », « Fin Milan 2–1 Inter ». */
    public static function text(array $m): array
    {
        $teams = [(string) $m['home'], (string) ($m['away'] ?? '')];
        if ($m['status'] === 'live') {
            return [($m['minute'] ?? 0)."'", "{$teams[0]} {$m['hs']}–{$m['as']} {$teams[1]}"];
        }
        if ($m['status'] === 'finished') {
            return [__('Fin'), "{$teams[0]} {$m['hs']}–{$m['as']} {$teams[1]}"];
        }
        $time = ! empty($m['ts'])
            ? \Illuminate\Support\Carbon::createFromTimestamp((int) $m['ts'])->setTimezone((string) config('terrain.timezone', 'Africa/Conakry'))->format('H:i')
            : (string) ($m['date_label'] ?? '');

        return [$time, trim("{$teams[0]} – {$teams[1]}", ' –')];
    }

    /** Équipes et compétitions proposées dans le choix (matchs du jour + compétitions suivies). */
    public function options(): array
    {
        $today = $this->today();
        $teams = [];
        foreach ($today as $m) {
            $teams[$m['home']] = true;
            if ($m['away'] !== '') {
                $teams[$m['away']] = true;
            }
        }
        $leagues = [];
        try {
            foreach ($this->real->competitions() as $id => $comp) {
                $leagues[(string) $id] = $comp['name'];
            }
        } catch (Throwable) {
        }
        $leagues['local'] = __('Matchs locaux (clubs et tournois)');
        ksort($teams);

        return ['teams' => array_keys($teams), 'leagues' => $leagues];
    }
}
