<?php

namespace App\Terrain\Growth;

use App\Terrain\DataSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Statistiques (vues de profil et de vidéos), badges, classements et recommandations.
 */
class Insights
{
    public function __construct(private DataSource $data)
    {
    }

    // ------------------------------------------------------------------ vues

    /** Vue d'un profil (une seule par visiteur toutes les 6 h). Prévient le sportif quand c'est un recruteur. */
    public function profileView(array $athlete, ?array $viewer): void
    {
        try {
            $key = $viewer['id'] ?? ('anon:'.substr(sha1(request()->ip().request()->userAgent()), 0, 16));
            if (($viewer['athlete_id'] ?? null) === $athlete['id']) {
                return; // on ne compte pas ses propres visites
            }
            $recent = DB::table('profile_views')->where('athlete_id', $athlete['id'])->where('viewer_key', $key)->where('created_at', '>', now()->subHours(6))->exists();
            if ($recent) {
                return;
            }
            DB::table('profile_views')->insert([
                'athlete_id' => $athlete['id'], 'viewer_key' => $key, 'viewer_role' => $viewer['role'] ?? 'visiteur', 'viewer_name' => $viewer['name'] ?? null,
                'country' => request()->header('CF-IPCountry') ?: null, 'created_at' => now(),
            ]);
            if (($viewer['role'] ?? null) === 'recruiter') {
                app(Notifier::class)->send($athlete['id'], 'profile_view', ($viewer['name'] ?? 'Un recruteur').' a consulté ton profil', 'Continue à publier tes meilleures actions !', route('stats'));
            }
        } catch (Throwable) {
        }
    }

    public function videoView(string $videoId, string $athleteId, ?string $viewerKey): void
    {
        try {
            DB::table('video_views')->insert(['video_id' => $videoId, 'athlete_id' => $athleteId, 'viewer_key' => $viewerKey, 'created_at' => now()]);
        } catch (Throwable) {
        }
    }

    /** Tableau de bord du sportif. */
    public function summary(array $athlete): array
    {
        $id = $athlete['id'];
        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $days[now()->subDays($i)->format('Y-m-d')] = 0;
        }
        $rows = DB::table('profile_views')->where('athlete_id', $id)->where('created_at', '>=', now()->subDays(29)->startOfDay())->get();
        foreach ($rows as $r) {
            $d = substr((string) $r->created_at, 0, 10);
            if (isset($days[$d])) {
                $days[$d]++;
            }
        }
        $recruiters = DB::table('profile_views')->where('athlete_id', $id)->where('viewer_role', 'recruiter')
            ->selectRaw('viewer_key, max(viewer_name) as name, count(*) as n, max(created_at) as last')->groupBy('viewer_key')->orderByDesc('last')->limit(20)->get();
        $countries = DB::table('profile_views')->where('athlete_id', $id)->whereNotNull('country')
            ->selectRaw('country, count(*) as n')->groupBy('country')->orderByDesc('n')->limit(6)->pluck('n', 'country')->all();
        $videoViews = DB::table('video_views')->where('athlete_id', $id)->selectRaw('video_id, count(*) as n')->groupBy('video_id')->pluck('n', 'video_id')->all();
        $videos = array_map(fn ($v) => $v + ['site_views' => $videoViews[$v['id']] ?? 0], $athlete['videos']);
        usort($videos, fn ($a, $b) => ($b['views'] + $b['site_views']) <=> ($a['views'] + $a['site_views']));

        return [
            'days' => $days, 'total30' => array_sum($days), 'all' => DB::table('profile_views')->where('athlete_id', $id)->count(),
            'recruiters' => $recruiters, 'clubs' => $recruiters->count(), 'countries' => $countries, 'videos' => $videos,
            'followers' => DB::table('follows_local')->where('athlete_id', $id)->count(),
        ];
    }

    // ------------------------------------------------------------------ badges

    /** @return list<array{0: string, 1: string, 2: string}> [icône, libellé, explication] */
    public function badges(array $a, ?array $all = null): array
    {
        $b = [];
        $wins = app(Challenges::class)->winsOf($a['id']);
        if ($wins) {
            $b[] = ['🏆', $wins > 1 ? "Joueur de la semaine ×{$wins}" : 'Joueur de la semaine', 'A gagné un défi de la semaine'];
        }
        try {
            $clubs = DB::table('profile_views')->where('athlete_id', $a['id'])->where('viewer_role', 'recruiter')->distinct()->count('viewer_key');
        } catch (Throwable) {
            $clubs = 0;
        }
        if ($clubs >= 3) {
            $b[] = ['👀', "Vu par {$clubs} recruteurs", 'Des clubs et agents ont consulté ce profil'];
        }
        if (max(array_merge([0], array_column($a['videos'], 'views'))) >= 5000) {
            $b[] = ['🔥', 'Vidéo virale', 'Une compilation a dépassé 5 000 vues'];
        }
        $rank = $this->cityRank($a, $all);
        if ($rank && $rank <= 10) {
            $b[] = ['⭐', "Top {$rank} ".$a['city'], "Classement {$a['sport']} de {$a['city']}"];
        }
        if (count($a['videos']) >= 3) {
            $b[] = ['🎬', 'Régulier', 'Au moins 3 compilations publiées'];
        }
        if (! empty($a['boosted'])) {
            $b[] = ['🚀', 'Boosté', 'Profil mis en avant'];
        }

        return $b;
    }

    // ------------------------------------------------------------------ classements

    /** Note de classement : pertinence du profil + popularité des vidéos + défis gagnés. */
    public function rating(array $a): float
    {
        $views = array_sum(array_column($a['videos'], 'views'));
        $likes = array_sum(array_column($a['videos'], 'likes'));

        // Note sur 99, comme une carte de joueur : fiche + popularité des vidéos + vérification
        return min(99.0, round(25 + $a['score'] * 0.5 + log10(1 + $views) * 3 + log10(1 + $likes) * 2 + ($a['verified'] ? 2 : 0), 1));
    }

    public function ranking(array $athletes, array $f): array
    {
        $list = array_filter($athletes, function ($a) use ($f) {
            if (($f['sport'] ?? 'Tous') !== 'Tous' && $a['sport'] !== $f['sport']) {
                return false;
            }
            if (($f['country'] ?? 'Tous') !== 'Tous' && $a['country'] !== $f['country']) {
                return false;
            }
            if (($f['city'] ?? '') !== '' && Str::lower($a['city']) !== Str::lower($f['city'])) {
                return false;
            }
            if (($f['poste'] ?? '') !== '' && ! str_contains(Str::lower((string) ($a['fields']['Poste'] ?? $a['fields']['Discipline'] ?? '')), Str::lower($f['poste']))) {
                return false;
            }
            $cat = $f['cat'] ?? 'Tous';

            return $cat === 'Tous' || ($cat === 'U17' && $a['age'] <= 17) || ($cat === 'U20' && $a['age'] <= 20) || ($cat === 'Seniors' && $a['age'] > 20);
        });
        $list = array_map(fn ($a) => $a + ['rating' => $this->rating($a)], $list);
        usort($list, fn ($x, $y) => $y['rating'] <=> $x['rating']);

        return array_values($list);
    }

    private function cityRank(array $a, ?array $all): ?int
    {
        $all ??= $this->data->athletes([]);
        $same = array_values(array_filter($all, fn ($x) => $x['sport'] === $a['sport'] && $x['city'] === $a['city']));
        if (count($same) < 3) {
            return null;
        }
        usort($same, fn ($x, $y) => $this->rating($y) <=> $this->rating($x));
        foreach ($same as $i => $x) {
            if ($x['id'] === $a['id']) {
                return $i + 1;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ recommandations

    private const POSITIONS = ['gardien', 'défenseur', 'latéral', 'milieu', 'ailier', 'attaquant', 'avant-centre', 'meneur', 'arrière', 'pivot', 'demi-centre', 'sprint', '100 m', '200 m', '400 m', '800 m'];

    /** @return array{0: int|null, 1: int|null} âge minimum / maximum demandé par l'offre */
    private function ageRange(array $o): array
    {
        $text = Str::lower($o['title'].' '.$o['criteria']);
        if (preg_match('/u\s?(\d{2})/', $text, $m)) {
            return [null, (int) $m[1]];
        }
        if (preg_match_all('/(20\d{2})/', $text, $m) && $m[1]) {
            $years = array_map('intval', $m[1]);
            $y = (int) now()->year;

            return [$y - max($years) - 1, $y - min($years)];
        }

        return [null, null];
    }

    /** Score de correspondance sportif ↔ offre (null = ne correspond pas) + raisons lisibles. */
    public function match(array $a, array $o): ?array
    {
        if ($a['sport'] !== $o['sport']) {
            return null;
        }
        [$min, $max] = $this->ageRange($o);
        if (($min && $a['age'] < $min) || ($max && $a['age'] > $max)) {
            return null;
        }
        $score = $a['score'];
        $why = [];
        $text = Str::lower($o['title'].' '.$o['criteria']);
        $poste = Str::lower((string) ($a['fields']['Poste'] ?? $a['fields']['Discipline'] ?? ''));
        foreach (self::POSITIONS as $p) {
            if (str_contains($text, $p)) {
                if ($poste && str_contains($poste, rtrim($p, 's'))) {
                    $score += 25;
                    $why[] = 'poste recherché';
                } else {
                    $score -= 15;
                }
                break;
            }
        }
        if ($max || $min) {
            $why[] = 'âge correspondant';
        }
        if (Str::lower($a['city']) === Str::lower((string) $o['place'])) {
            $score += 10;
            $why[] = 'même ville';
        }
        if ($a['verified']) {
            $score += 5;
            $why[] = 'profil vérifié';
        }
        if (count($a['videos'])) {
            $score += min(10, count($a['videos']) * 3);
        } else {
            $score -= 30;
        }

        return ['score' => $score, 'why' => array_values(array_unique($why))];
    }

    public function playersFor(array $offer, int $n = 5): array
    {
        $out = [];
        foreach ($this->data->athletes(['sport' => $offer['sport']]) as $a) {
            if (in_array($a['id'], $offer['candidates'] ?? [], true)) {
                continue;
            }
            if ($m = $this->match($a, $offer)) {
                $out[] = ['athlete' => $a] + $m;
            }
        }
        usort($out, fn ($x, $y) => $y['score'] <=> $x['score']);

        return array_slice($out, 0, $n);
    }

    /** Offres triées pour un sportif, avec « recommandée » et les raisons. */
    public function offersFor(?array $athlete, array $offers): array
    {
        foreach ($offers as &$o) {
            $m = $athlete ? $this->match($athlete, $o) : null;
            $o['match'] = $m;
        }
        unset($o);
        usort($offers, fn ($x, $y) => ($y['match']['score'] ?? -1000) <=> ($x['match']['score'] ?? -1000));

        return $offers;
    }
}
