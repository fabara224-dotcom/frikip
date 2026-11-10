<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * « Découvrir » : des vidéos de foot YouTube (entraînements, gestes techniques, gardiens, arbitrage, santé…)
 * pour que chacun comprenne le concept dès sa première visite.
 *
 * Seules les vidéos dont la chaîne autorise l'intégration sont gardées ; elles sont lues avec le lecteur
 * officiel de YouTube et le nom de la chaîne est toujours affiché (rien n'est copié sur le serveur).
 * Réglages : Admin > Découvrir. Clé : YOUTUBE_API_KEY.
 */
class Discover
{
    private const API = 'https://www.googleapis.com/youtube/v3';

    public function __construct(private Algorithm $algo)
    {
    }

    public static function configured(): bool
    {
        return (string) config('terrain.youtube.key') !== '';
    }

    public static function count(): int
    {
        try {
            return (int) DB::table('discover_videos')->where('hidden', false)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function lines(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->algo->get($key)))));
    }

    /** Thèmes affichés en filtres (ceux qui ont des vidéos). */
    public function topics(): array
    {
        try {
            return DB::table('discover_videos')->where('hidden', false)->whereNotNull('topic')
                ->selectRaw('topic, count(*) as n')->groupBy('topic')->orderByDesc('n')->pluck('n', 'topic')->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    public function page(?string $topic, int $page, int $per = 24)
    {
        try {
            return DB::table('discover_videos')->where('hidden', false)->when($topic, fn ($q) => $q->where('topic', $topic))
                ->orderByRaw('(plays + 1) * 1.0 / (1 + (julianday(\'now\') - julianday(coalesce(published_at, created_at))) / 365.0) desc')
                ->orderByDesc('id')->forPage($page, $per)->get();
        } catch (Throwable) {
            // MySQL : pas de julianday
            return DB::table('discover_videos')->where('hidden', false)->when($topic, fn ($q) => $q->where('topic', $topic))
                ->orderByDesc('plays')->orderByDesc('id')->forPage($page, $per)->get();
        }
    }

    /** Quelques vidéos au hasard pour les glisser dans l'Accueil. */
    public function sample(int $n): array
    {
        if (! $this->algo->get('disc_in_feed') || $n <= 0) {
            return [];
        }
        try {
            $ids = DB::table('discover_videos')->where('hidden', false)->pluck('id')->all();
        } catch (Throwable) {
            return [];
        }
        shuffle($ids);

        return DB::table('discover_videos')->whereIn('id', array_slice($ids, 0, $n))->get()->all();
    }

    /**
     * Va chercher de nouvelles vidéos jusqu'au maximum réglé (1000 par défaut).
     * Coût YouTube : 100 unités par page de 50 résultats ; le quota gratuit est de 10 000 unités par jour.
     *
     * @return array{added: int, total: int, error: ?string}
     */
    public function import(?int $max = null, int $pagesPerTopic = 2): array
    {
        $key = (string) config('terrain.youtube.key');
        $max ??= max(50, min(5000, (int) $this->algo->get('disc_max')));
        $total = self::count();
        $added = 0;
        $error = null;
        if ($key === '') {
            return ['added' => 0, 'total' => $total, 'error' => 'Ajoute YOUTUBE_API_KEY dans le fichier .env.'];
        }
        $block = array_map('mb_strtolower', $this->lines('disc_blocklist'));
        $channels = $this->lines('disc_channels');
        $searches = [];
        foreach ($this->lines('disc_topics') as $t) {
            $searches[] = ['q' => $t, 'topic' => self::topicLabel($t)];
        }
        foreach ($channels as $c) {
            $searches[] = ['channel' => $c, 'topic' => null];
        }

        foreach ($searches as $s) {
            if ($total + $added >= $max) {
                break;
            }
            $tokenKey = 'frikip.disc.page.'.md5(json_encode($s));
            $pageToken = Cache::get($tokenKey);
            for ($i = 0; $i < $pagesPerTopic && $total + $added < $max; $i++) {
                try {
                    $params = ['part' => 'snippet', 'type' => 'video', 'videoEmbeddable' => 'true', 'videoSyndicated' => 'true',
                        'safeSearch' => 'strict', 'maxResults' => 50, 'key' => $key, 'relevanceLanguage' => 'fr', 'regionCode' => 'SN'];
                    if (isset($s['q'])) {
                        $params['q'] = $s['q'];
                    } else {
                        $params['channelId'] = $s['channel'];
                        $params['order'] = 'date';
                    }
                    if ($pageToken) {
                        $params['pageToken'] = $pageToken;
                    }
                    $r = Http::acceptJson()->connectTimeout(5)->timeout(15)->get(self::API.'/search', $params);
                    if (! $r->successful()) {
                        $error = (string) ($r->json('error.message') ?: 'HTTP '.$r->status());
                        break 2;   // quota dépassé ou clé refusée : on s'arrête
                    }
                    $ids = array_values(array_filter(array_map(fn ($it) => $it['id']['videoId'] ?? null, $r->json('items') ?? [])));
                    $pageToken = $r->json('nextPageToken');
                    Cache::put($tokenKey, $pageToken, now()->addDays(30));
                    $added += $this->store($ids, $s['topic'], $block, $max - $total - $added);
                    if (! $pageToken) {
                        break;
                    }
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                    break 2;
                }
            }
        }
        Cache::put('frikip.disc.last', ['at' => now()->toIso8601String(), 'added' => $added, 'error' => $error], now()->addDays(30));

        return ['added' => $added, 'total' => self::count(), 'error' => $error];
    }

    /** Détails des vidéos (durée, intégration autorisée, public) puis enregistrement. */
    private function store(array $ids, ?string $topic, array $block, int $room): int
    {
        $ids = array_values(array_diff($ids, DB::table('discover_videos')->whereIn('youtube_id', $ids)->pluck('youtube_id')->all()));
        if (! $ids || $room <= 0) {
            return 0;
        }
        $r = Http::acceptJson()->connectTimeout(5)->timeout(15)->get(self::API.'/videos', [
            'part' => 'snippet,contentDetails,status', 'id' => implode(',', $ids), 'key' => (string) config('terrain.youtube.key'),
        ]);
        if (! $r->successful()) {
            return 0;
        }
        $n = 0;
        foreach ($r->json('items') ?? [] as $v) {
            if ($n >= $room) {
                break;
            }
            $st = $v['status'] ?? [];
            if (empty($st['embeddable']) || ($st['privacyStatus'] ?? '') !== 'public' || ($v['snippet']['liveBroadcastContent'] ?? 'none') !== 'none') {
                continue;
            }
            $sec = self::seconds((string) ($v['contentDetails']['duration'] ?? ''));
            $maxSec = max(60, (int) $this->algo->get('disc_max_minutes') * 60);
            if ($sec < 15 || $sec > $maxSec) {
                continue;
            }
            $title = html_entity_decode((string) ($v['snippet']['title'] ?? ''), ENT_QUOTES);
            $hay = mb_strtolower($title.' '.($v['snippet']['channelTitle'] ?? ''));
            foreach ($block as $w) {
                if ($w !== '' && str_contains($hay, $w)) {
                    continue 2;
                }
            }
            $th = $v['snippet']['thumbnails'] ?? [];
            DB::table('discover_videos')->insertOrIgnore([
                'youtube_id' => $v['id'], 'title' => mb_substr($title, 0, 200), 'channel' => mb_substr((string) ($v['snippet']['channelTitle'] ?? ''), 0, 120),
                'channel_id' => $v['snippet']['channelId'] ?? null, 'thumbnail' => ($th['high']['url'] ?? $th['medium']['url'] ?? $th['default']['url'] ?? null),
                'duration' => $sec, 'topic' => $topic, 'published_at' => isset($v['snippet']['publishedAt']) ? \Illuminate\Support\Carbon::parse($v['snippet']['publishedAt']) : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $n++;
        }

        return $n;
    }

    /** « PT4M13S » → 253 */
    public static function seconds(string $iso): int
    {
        if (! preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) {
            return 0;
        }

        return ((int) ($m[1] ?? 0)) * 86400 + ((int) ($m[2] ?? 0)) * 3600 + ((int) ($m[3] ?? 0)) * 60 + (int) ($m[4] ?? 0);
    }

    /** « gestes techniques football tuto » → « Gestes techniques » (libellé court du filtre). */
    public static function topicLabel(string $q): string
    {
        $map = [
            'gardien' => 'Gardiens de but', 'dribble' => 'Dribbles', 'frappe' => 'Frappes et tirs', 'tir ' => 'Frappes et tirs', 'jonglage' => 'Jonglage',
            'physique' => 'Préparation physique', 'tactique' => 'Tactique', 'arbitr' => 'Arbitrage', 'blessure' => 'Santé et blessures', 'nutrition' => 'Nutrition',
            'féminin' => 'Football féminin', 'passe' => 'Passes', 'défens' => 'Défense', 'coup franc' => 'Coups francs', 'exercice' => 'Exercices', 'entraînement' => 'Exercices',
            'technique' => 'Gestes techniques', 'skill' => 'Gestes techniques', 'but' => 'Plus beaux buts', 'goal' => 'Plus beaux buts', 'détection' => 'Détections',
        ];
        $l = mb_strtolower($q);
        foreach ($map as $w => $label) {
            if (str_contains($l, $w)) {
                return $label;
            }
        }

        return mb_strtoupper(mb_substr($q, 0, 1)).mb_substr($q, 1, 40);
    }

    public static function duration(int $s): string
    {
        return $s >= 3600 ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }
}
