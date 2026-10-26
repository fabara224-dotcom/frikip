<?php

namespace App\Terrain;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * VRAIS RÉSULTATS EN DIRECT – API-Football (https://www.api-football.com).
 *
 * Compétitions suivies (.env) :
 *   API_FOOTBALL_LEAGUES=2,39,140,135,78,61,12   (identifiants API-Football, voir COMPETITIONS)
 *   API_FOOTBALL_COUNTRY=Guinea                    (ajoute le championnat national de ce pays)
 *
 * Pour économiser le quota, chaque réponse est mise en cache :
 *   - matchs du jour : un seul appel pour toutes les compétitions (API_FOOTBALL_IDLE_TTL) ;
 *   - direct : un seul appel pour toutes les compétitions (API_FOOTBALL_LIVE_TTL), seulement s'il y a un match en cours ;
 *   - résultats, calendrier et classement d'une compétition : quelques heures.
 * Si le fournisseur ne répond pas, la dernière réponse valide est réutilisée.
 */
class RealScores
{
    /** Noms français des compétitions les plus suivies (identifiants API-Football). */
    public const COMPETITIONS = [
        2 => 'Ligue des champions', 3 => 'Ligue Europa', 39 => 'Premier League', 140 => 'Liga',
        135 => 'Serie A', 78 => 'Bundesliga', 61 => 'Ligue 1 (France)', 12 => 'Ligue des champions CAF',
        20 => 'Coupe de la Confédération CAF', 6 => 'Coupe d\'Afrique des nations', 1 => 'Coupe du monde',
    ];

    /**
     * Classement final réel de la Ligue 1 Pro de Guinée, saison 2025-26 (26 journées),
     * affiché tant qu'aucune clé n'est configurée. Source : matchendirect.fr, consulté le 20 octobre 2026.
     */
    public const LAST_SEASON = [
        ['Horoya AC', 26, 17, 5, 4, 42, 19], ['Hafia FC', 26, 15, 7, 4, 34, 16], ['CI Kamsar', 26, 15, 3, 8, 37, 21],
        ['AS Kaloum', 26, 12, 9, 5, 32, 18], ['Milo FC', 26, 11, 10, 5, 33, 18], ['Wakriya AC', 26, 8, 9, 9, 22, 26],
        ['ASFAG', 26, 9, 5, 12, 31, 35], ['Sangarédi', 26, 7, 8, 11, 22, 28], ['Ashanti Golden Boys', 26, 7, 8, 11, 23, 32],
        ['RCC Kamsar', 26, 7, 7, 12, 25, 31], ['Karfamoriah', 26, 7, 7, 12, 24, 33], ['Guinée Foot', 26, 7, 7, 12, 30, 42],
        ['Renaissance', 26, 6, 8, 12, 20, 35], ['Loubha Télimélé', 26, 4, 7, 15, 14, 35],
    ];

    public const LAST_SEASON_SOURCE = 'https://www.matchendirect.fr/classement-foot/guinee/classement-ligue-1.html';

    private const BASE = 'https://v3.football.api-sports.io';

    private const LIVE = ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE', 'INT', 'SUSP'];

    private const DONE = ['FT', 'AET', 'PEN', 'AWD', 'WO', 'CANC', 'ABD'];

    private const OFF = ['PST' => 'Reporté', 'CANC' => 'Annulé', 'ABD' => 'Arrêté', 'TBD' => 'Horaire à confirmer', 'SUSP' => 'Suspendu', 'INT' => 'Interrompu'];

    public function enabled(): bool
    {
        return (string) config('terrain.api_football.key') !== '';
    }

    private function tz(): string
    {
        return (string) config('terrain.timezone', 'Africa/Conakry');
    }

    // ------------------------------------------------------------------ appels et cache

    private function call(string $path, array $query): array
    {
        $r = Http::withHeaders(['x-apisports-key' => (string) config('terrain.api_football.key')])
            ->acceptJson()->connectTimeout(4)->timeout(8)->get(self::BASE.$path, $query);
        if (! $r->successful()) {
            throw new RuntimeException("HTTP {$r->status()}");
        }
        $errors = $r->json('errors');
        if (! empty($errors)) {
            throw new RuntimeException(is_array($errors) ? implode(' ', array_map('strval', $errors)) : (string) $errors);
        }

        return $r->json('response') ?? [];
    }

    private function cached(string $key, int $ttl, callable $fetch): array
    {
        $fresh = Cache::get("terrain.real.{$key}");
        if ($fresh !== null) {
            return $fresh;
        }
        // Service injoignable il y a moins de 2 minutes (pas d'Internet, coupure DNS) : on n'attend pas encore,
        // la page s'affiche tout de suite avec les derniers scores connus.
        if (Cache::has('terrain.real.down')) {
            return Cache::get("terrain.real.last.{$key}", []);
        }
        try {
            $value = $fetch();
            Cache::put("terrain.real.{$key}", $value, $ttl);
            Cache::forever("terrain.real.last.{$key}", $value);
            Cache::forget('terrain.real.error');

            return $value;
        } catch (Throwable $e) {
            Log::warning('API-Football indisponible', ['key' => $key, 'error' => $e->getMessage()]);
            Cache::put('terrain.real.error', $e->getMessage(), 600);
            if ($e instanceof \Illuminate\Http\Client\ConnectionException || str_contains($e->getMessage(), 'cURL error')) {
                Cache::put('terrain.real.down', 1, 120);
            }
            $last = Cache::get("terrain.real.last.{$key}", []);
            Cache::put("terrain.real.{$key}", $last, 60); // nouvel essai dans une minute

            return $last;
        }
    }

    /** Dernier message d'erreur du fournisseur (quota dépassé, saison non incluse dans l'offre…). */
    public function lastError(): ?string
    {
        return Cache::get('terrain.real.error');
    }

    private function liveTtl(): int
    {
        return max(15, (int) config('terrain.api_football.live_ttl', 60));
    }

    private function idleTtl(): int
    {
        return max(60, (int) config('terrain.api_football.idle_ttl', 900));
    }

    // ------------------------------------------------------------------ compétitions

    /** @return array<int, array{id: int, name: string, season: int, logo: string, country: string, flag: string}> */
    public function competitions(): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) config('terrain.api_football.leagues', '')))));
        $country = (string) config('terrain.api_football.country', '');
        $key = 'comps.'.md5(implode(',', $ids).'|'.$country);

        // Un seul appel pour toutes les compétitions (l'offre gratuite limite aussi le nombre d'appels par minute).
        $list = $this->cached($key, 86400, function () use ($ids, $country) {
            $all = $this->call('/leagues', ['current' => 'true']);
            $out = [];
            $national = null;
            foreach ($all as $row) {
                $id = (int) ($row['league']['id'] ?? 0);
                $isNational = $country !== '' && ($row['country']['name'] ?? '') === $country && ($row['league']['type'] ?? '') === 'League';
                if ($isNational && $national === null) {
                    $national = $id; // premier championnat du pays (division 1 en général)
                }
                if (! in_array($id, $ids, true) && ! ($isNational && $national === $id)) {
                    continue;
                }
                $current = collect($row['seasons'] ?? [])->firstWhere('current', true) ?? collect($row['seasons'] ?? [])->last();
                $cov = $current['coverage']['fixtures'] ?? [];
                $out[$id] = [
                    'id' => $id,
                    'name' => self::COMPETITIONS[$id] ?? (string) $row['league']['name'],
                    'season' => (int) ($current['year'] ?? date('Y')),
                    'logo' => (string) ($row['league']['logo'] ?? ''),
                    'country' => (string) ($row['country']['name'] ?? ''),
                    'flag' => (string) ($row['country']['flag'] ?? ''),
                    'coverage' => [
                        'events' => (bool) ($cov['events'] ?? false),
                        'lineups' => (bool) ($cov['lineups'] ?? false),
                        'statistics' => (bool) ($cov['statistics_fixtures'] ?? false),
                    ],
                ];
            }
            // Ordre : championnat national d'abord, puis l'ordre de API_FOOTBALL_LEAGUES
            $sorted = [];
            if ($national !== null && isset($out[$national])) {
                $sorted[$national] = $out[$national];
            }
            foreach ($ids as $id) {
                if (isset($out[$id])) {
                    $sorted[$id] = $out[$id];
                }
            }

            return $sorted;
        });

        // Nom lisible pour le championnat national (ex. « Ligue 1 Pro (Guinée) »)
        foreach ($list as $id => $c) {
            if (! isset(self::COMPETITIONS[$id]) && $c['country'] === 'Guinea') {
                $list[$id]['name'] = 'Ligue 1 Pro (Guinée)';
            }
        }
        if ($s = (int) config('terrain.api_football.season')) {
            foreach ($list as $id => $c) {
                $list[$id]['season'] = $s;
            }
        }

        return $list;
    }

    public function competition(int $id): ?array
    {
        return $this->competitions()[$id] ?? null;
    }

    private function leagueIds(): array
    {
        return array_keys($this->competitions());
    }

    // ------------------------------------------------------------------ matchs

    /** Matchs en direct de toutes les compétitions suivies, en un seul appel. */
    public function live(): array
    {
        $ids = $this->leagueIds();
        if (! $ids) {
            return [];
        }
        $rows = $this->cached('live.'.md5(implode('-', $ids)), $this->liveTtl(), fn () => $this->call('/fixtures', ['live' => implode('-', $ids), 'timezone' => $this->tz()]));

        return array_map(fn ($f) => $this->map($f), $rows);
    }

    /** Tous les matchs du jour des compétitions suivies (direct à jour), en direct d'abord. */
    public function today(): array
    {
        $ids = $this->leagueIds();
        if (! $ids) {
            return [];
        }
        $date = now($this->tz())->toDateString();
        $rows = $this->cached("day.{$date}", $this->idleTtl(), fn () => $this->call('/fixtures', ['date' => $date, 'timezone' => $this->tz()]));
        $matches = array_values(array_filter(array_map(fn ($f) => $this->map($f), $rows), fn ($m) => in_array($m['league_id'], $ids, true)));

        // On n'interroge le direct que si un match peut être en cours (économie de quota).
        $now = time();
        $maybeLive = collect($matches)->contains(fn ($m) => $m['status'] === 'live' || ($m['status'] === 'upcoming' && $m['ts'] <= $now + 600 && $m['ts'] >= $now - 3 * 3600));
        if ($maybeLive) {
            $byId = collect($this->live())->keyBy('id');
            $matches = array_map(function ($m) use ($byId, $now) {
                if (isset($byId[$m['id']])) {
                    return $byId[$m['id']];
                }
                // Coup d'envoi passé mais plus dans le direct : match terminé, on attend la mise à jour du jour.
                return $m;
            }, $matches);
            foreach ($byId as $id => $m) {
                if (! collect($matches)->contains('id', $id)) {
                    $matches[] = $m; // match commencé la veille (fuseau horaire)
                }
            }
        }

        usort($matches, fn ($a, $b) => [$a['status'] !== 'live', $a['ts']] <=> [$b['status'] !== 'live', $b['ts']]);
        $this->remember($matches);

        return $matches;
    }

    /**
     * Tous les matchs d'un jour (AAAA-MM-JJ) des compétitions suivies : aujourd'hui avec le direct,
     * les jours passés (résultats, gardés 6 h) et à venir (programme, gardé 30 min).
     */
    public function day(string $date): array
    {
        $today = now($this->tz())->toDateString();
        if ($date === $today) {
            return $this->today();
        }
        $ids = $this->leagueIds();
        if (! $ids) {
            return [];
        }
        $ttl = $date < $today ? 6 * 3600 : 1800;
        $rows = $this->cached("day.{$date}", $ttl, fn () => $this->call('/fixtures', ['date' => $date, 'timezone' => $this->tz()]));
        $matches = array_values(array_filter(array_map(fn ($f) => $this->map($f), $rows), fn ($m) => in_array($m['league_id'], $ids, true)));
        usort($matches, fn ($a, $b) => $a['ts'] <=> $b['ts']);
        $this->remember($matches);

        return $matches;
    }

    /** Une compétition : direct, 10 derniers résultats, 10 prochains matchs, classement. */
    public function league(int $id): ?array
    {
        $comp = $this->competition($id);
        if (! $comp) {
            return null;
        }
        $live = array_values(array_filter($this->live(), fn ($m) => $m['league_id'] === $id));
        $hasLive = count($live) > 0;
        $last = $this->cached("last.{$id}", $hasLive ? 600 : 10800, fn () => $this->call('/fixtures', ['league' => $id, 'last' => 10, 'timezone' => $this->tz()]));
        $next = $this->cached("next.{$id}", 10800, fn () => $this->call('/fixtures', ['league' => $id, 'next' => 10, 'timezone' => $this->tz()]));
        $standings = $this->cached("standings.{$id}.{$comp['season']}", $hasLive ? 600 : 21600, fn () => $this->call('/standings', ['league' => $id, 'season' => $comp['season']]));

        $liveTeams = collect($live)->flatMap(fn ($m) => [$m['home'], $m['away']])->all();
        $groups = [];
        foreach ($standings[0]['league']['standings'] ?? [] as $group) {
            $rows = array_map(fn ($r) => [
                'team' => $r['team']['name'], 'logo' => $r['team']['logo'] ?? '', 'p' => (int) ($r['all']['played'] ?? 0),
                'w' => (int) ($r['all']['win'] ?? 0), 'd' => (int) ($r['all']['draw'] ?? 0), 'l' => (int) ($r['all']['lose'] ?? 0),
                'gf' => (int) ($r['all']['goals']['for'] ?? 0), 'ga' => (int) ($r['all']['goals']['against'] ?? 0),
                'pts' => (int) ($r['points'] ?? 0), 'diff' => (int) ($r['goalsDiff'] ?? 0),
                'form' => (string) ($r['form'] ?? ''), 'live' => in_array($r['team']['name'], $liveTeams, true),
            ], $group);
            $groups[] = ['title' => count($standings[0]['league']['standings']) > 1 ? (string) ($group[0]['group'] ?? '') : '', 'rows' => $rows];
        }

        $results = array_map(fn ($f) => $this->map($f), $last);
        usort($results, fn ($a, $b) => $b['ts'] <=> $a['ts']);
        $upcoming = array_map(fn ($f) => $this->map($f), $next);
        usort($upcoming, fn ($a, $b) => $a['ts'] <=> $b['ts']);

        $this->remember(array_merge($live, $results, $upcoming));

        return ['competition' => $comp, 'live' => $live, 'results' => $results, 'next' => $upcoming, 'standings' => $groups];
    }

    /**
     * Fiche complète d'un match (« api-123456 ») : buts, cartons, statistiques, compositions.
     * Pour économiser le quota, on n'interroge pas le fournisseur pour un match qui commence dans plus d'une heure.
     */
    public function match(string $id): ?array
    {
        if (! $this->enabled() || ! preg_match('/^api-(\d+)$/', $id, $mm)) {
            return null;
        }
        $known = $this->known($id);
        $now = time();
        if ($known && $known['status'] === 'upcoming' && $known['ts'] > $now + 3600) {
            return $this->withNotes($known);
        }

        $key = "fixture.{$mm[1]}";
        $ttl = match ($known['status'] ?? 'live') {
            'finished' => 86400,
            'upcoming' => 600,
            default => max(60, $this->liveTtl()),
        };
        $rows = $this->cached($key, $ttl, fn () => $this->call('/fixtures', ['id' => $mm[1], 'timezone' => $this->tz()]));
        if (! isset($rows[0])) {
            return $known ? $this->withNotes($known + ['provider_error' => $this->lastError()]) : null;
        }
        $m = $this->map($rows[0]);
        if ($m['status'] === 'finished') {
            Cache::put("terrain.real.{$key}", $rows, 86400); // un match terminé ne change plus
        }

        return $this->withNotes($m);
    }

    /** Retrouve un match dans les listes déjà chargées (sans appel supplémentaire si elles sont en cache). */
    private function known(string $id): ?array
    {
        foreach (Cache::get('terrain.real.index', []) as $m) {
            if ($m['id'] === $id) {
                return $m;
            }
        }

        return collect($this->today())->firstWhere('id', $id);
    }

    /** Ajoute la couverture de la compétition et un message clair quand le détail n'est pas disponible. */
    private function withNotes(array $m): array
    {
        $cov = $this->competition($m['league_id'])['coverage'] ?? ['events' => true, 'lineups' => true, 'statistics' => true];
        $m['coverage'] = $cov;
        $empty = ! count($m['events']) && ! count($m['stats']) && ! count($m['lineups'] ?? []);
        $note = null;
        if ($empty) {
            $note = match (true) {
                ! empty($m['provider_error']) => 'Le fournisseur de scores ne répond pas pour le moment ('.$m['provider_error'].'). Réessaie dans quelques minutes.',
                ! $cov['events'] && ! $cov['statistics'] => "Pour cette compétition, le fournisseur publie seulement le score, pas le détail du match.",
                $m['status'] === 'upcoming' => "Le match n'a pas encore commencé. Les compositions sont publiées environ 40 minutes avant le coup d'envoi, puis les buts, cartons et statistiques pendant le match.",
                default => "Le détail de ce match n'est pas encore disponible chez le fournisseur.",
            };
        }
        $m['detail_note'] = $note;

        return $m;
    }

    private function remember(array $matches): void
    {
        $index = collect(Cache::get('terrain.real.index', []))->keyBy('id');
        foreach ($matches as $m) {
            $index[$m['id']] = ['id' => $m['id'], 'status' => $m['status'], 'ts' => $m['ts']] + $m;
        }
        Cache::put('terrain.real.index', $index->sortByDesc('ts')->take(400)->values()->all(), 86400);
    }

    public function firstLive(): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        return collect($this->today())->firstWhere('status', 'live');
    }

    /** Classement final réel 2025-26 de la Ligue 1 Pro guinéenne, au format du site. */
    public static function lastSeason(): array
    {
        return Standings::compute(self::LAST_SEASON, [], '');
    }

    // ------------------------------------------------------------------ conversion

    /** Convertit un match API-Football au format du site. */
    private function map(array $f): array
    {
        $short = $f['fixture']['status']['short'] ?? 'NS';
        $date = Carbon::parse($f['fixture']['date'] ?? 'now')->setTimezone($this->tz())->locale('fr');
        $status = in_array($short, self::LIVE, true) ? 'live' : (in_array($short, self::DONE, true) ? 'finished' : 'upcoming');
        $leagueId = (int) ($f['league']['id'] ?? 0);
        $homeId = $f['teams']['home']['id'] ?? null;
        $isToday = $date->isSameDay(now($this->tz()));

        if ($status === 'finished') {
            $label = (isset(self::OFF[$short]) ? self::OFF[$short].' · ' : '').($isToday ? "Aujourd'hui" : ucfirst($date->isoFormat('ddd D MMM')));
        } else {
            $label = (isset(self::OFF[$short]) ? self::OFF[$short].' · ' : '').($isToday ? "Aujourd'hui · ".$date->format('H:i') : ucfirst($date->isoFormat('ddd D MMM · HH:mm')));
        }

        $events = [];
        foreach ($f['events'] ?? [] as $e) {
            $type = match (true) {
                ($e['type'] ?? '') === 'Goal' && ($e['detail'] ?? '') !== 'Missed Penalty' => 'but',
                ($e['type'] ?? '') === 'Card' && str_contains((string) ($e['detail'] ?? ''), 'Yellow') => 'jaune',
                ($e['type'] ?? '') === 'Card' => 'rouge',
                default => null,
            };
            if ($type) {
                $extra = ($e['detail'] ?? '') === 'Penalty' ? ' (pen.)' : (($e['detail'] ?? '') === 'Own Goal' ? ' (csc)' : '');
                $min = (int) ($e['time']['elapsed'] ?? 0);
                $add = (int) ($e['time']['extra'] ?? 0);
                $events[] = ['minute' => $min, 'label' => $add ? "{$min}+{$add}" : (string) $min, 'side' => ($e['team']['id'] ?? null) === $homeId ? 'h' : 'a',
                    'type' => $type, 'player' => (string) ($e['player']['name'] ?? '?').$extra, 'athlete_id' => null];
            }
        }

        $stats = [];
        $want = ['Ball Possession' => 'Possession (%)', 'Total Shots' => 'Tirs', 'Shots on Goal' => 'Tirs cadrés', 'Corner Kicks' => 'Corners', 'Fouls' => 'Fautes', 'Offsides' => 'Hors-jeu'];
        if (count($f['statistics'] ?? []) === 2) {
            foreach ($want as $api => $fr) {
                $v = [];
                foreach ($f['statistics'] as $side) {
                    $raw = collect($side['statistics'] ?? [])->firstWhere('type', $api)['value'] ?? 0;
                    $v[] = (int) rtrim((string) $raw, '%');
                }
                if (array_sum($v) > 0) {
                    $stats[$fr] = $v; // on n'affiche pas une statistique que le fournisseur ne donne pas
                }
            }
        }

        $lineups = [];
        foreach ($f['lineups'] ?? [] as $l) {
            $lineups[] = [
                'team' => (string) ($l['team']['name'] ?? ''), 'formation' => (string) ($l['formation'] ?? ''),
                'coach' => (string) ($l['coach']['name'] ?? ''),
                'players' => array_map(fn ($p) => trim(($p['player']['number'] ?? '').' '.($p['player']['name'] ?? '')), $l['startXI'] ?? []),
            ];
        }

        $compName = self::COMPETITIONS[$leagueId]
            ?? ((($f['league']['country'] ?? '') === 'Guinea') ? 'Ligue 1 Pro (Guinée)' : (string) ($f['league']['name'] ?? ''));
        $round = (string) ($f['league']['round'] ?? '');
        $round = preg_replace(['/^Regular Season - /', '/^League Stage - /', '/^Group Stage - /'], ['J', 'J', 'J'], $round);

        return [
            'id' => 'api-'.$f['fixture']['id'], 'source' => 'api', 'ts' => $date->getTimestamp(),
            'league_id' => $leagueId, 'league_logo' => (string) ($f['league']['logo'] ?? ''),
            'sport' => 'Football',
            'competition' => $compName.($round !== '' ? ' · '.$round : ''),
            'competition_name' => $compName,
            'home' => (string) $f['teams']['home']['name'], 'away' => (string) $f['teams']['away']['name'],
            'home_logo' => (string) ($f['teams']['home']['logo'] ?? ''), 'away_logo' => (string) ($f['teams']['away']['logo'] ?? ''),
            'hs' => (int) ($f['goals']['home'] ?? 0), 'as' => (int) ($f['goals']['away'] ?? 0),
            'status' => $status, 'minute' => (int) ($f['fixture']['status']['elapsed'] ?? 0) + (int) ($f['fixture']['status']['extra'] ?? 0),
            'period' => $short === 'HT' ? 'Mi-temps' : null,
            'date_label' => $label,
            'venue' => trim(($f['fixture']['venue']['name'] ?? '').(! empty($f['fixture']['venue']['city']) ? ', '.$f['fixture']['venue']['city'] : '')),
            'referee' => (string) ($f['fixture']['referee'] ?? ''),
            'result' => null, 'events' => $events, 'stats' => $stats, 'lineups' => $lineups,
            'ratings' => [], 'candidates' => [], 'votes' => [], 'my_vote' => null,
        ];
    }
}
