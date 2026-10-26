<?php

namespace App\Terrain\Growth;

use App\Terrain\Region;
use App\Terrain\SportFields;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trouve les matchs diffusés en direct sur YouTube (API officielle YouTube Data v3) et les ajoute à « En direct ».
 * Seulement des directs dont la chaîne autorise l'intégration sur d'autres sites (videoEmbeddable=true) :
 * fédérations, ligues, clubs, télévisions. Les recherches et les chaînes se règlent dans Admin > Direct.
 *
 * Quota gratuit YouTube : 10 000 unités par jour, une recherche coûte 100 unités.
 * L'intervalle entre deux recherches est donc calculé pour ne jamais dépasser le quota.
 */
class LiveFinder
{
    private const API = 'https://www.googleapis.com/youtube/v3';

    public function __construct(private Algorithm $algo)
    {
    }

    public static function configured(): bool
    {
        return (string) config('terrain.youtube.key') !== '';
    }

    private function lines(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->algo->get($key)))));
    }

    /** Minutes entre deux recherches, pour rester sous ~9 000 unités par jour. */
    public function interval(): int
    {
        $calls = max(1, count($this->lines('yt_queries')) + count($this->lines('yt_channels')));

        return max(20, (int) ceil(1440 * 100 * $calls / 9000));
    }

    /** Lance une recherche après la réponse envoyée au visiteur, si la dernière est assez ancienne. */
    public function maybeSync(): void
    {
        if (! self::configured() || ! $this->algo->get('yt_enabled')) {
            return;
        }
        if (Cache::add('frikip.yt.sync', now()->toIso8601String(), now()->addMinutes($this->interval()))) {
            app()->terminating(function () {
                try {
                    $this->sync();
                } catch (Throwable $e) {
                    report($e);
                }
            });
        }
    }

    /** @return array{found: int, added: int, ended: int, error: ?string} */
    public function sync(): array
    {
        $out = ['found' => 0, 'added' => 0, 'ended' => 0, 'error' => null];
        if (! self::configured()) {
            return $out + ['error' => 'Clé YOUTUBE_API_KEY absente du fichier .env.'];
        }
        $key = (string) config('terrain.youtube.key');
        $ids = [];
        $origin = [];   // videoId => recherche ou chaîne qui l'a trouvé
        $requests = [];
        foreach ($this->lines('yt_queries') as $q) {
            $requests[] = ['q' => $q, 'relevanceLanguage' => 'fr'];
        }
        foreach ($this->lines('yt_channels') as $c) {
            if ($id = $this->channelId($c, $key)) {
                $requests[] = ['channelId' => $id, '_chan' => true];
            }
        }
        foreach ($requests as $params) {
            $label = $params['q'] ?? 'chaîne';
            $chan = ! empty($params['_chan']);
            unset($params['_chan']);
            try {
                $r = Http::connectTimeout(4)->timeout(10)->get(self::API.'/search', $params + [
                    'part' => 'snippet', 'eventType' => 'live', 'type' => 'video', 'videoEmbeddable' => 'true', 'maxResults' => 15, 'key' => $key,
                ]);
            } catch (Throwable $e) {
                $out['error'] = $e->getMessage();

                break;   // pas d'Internet : inutile d'essayer les autres recherches
            }
            if (! $r->successful()) {
                $out['error'] = (string) ($r->json('error.message') ?: 'Erreur YouTube '.$r->status());
                if ($r->status() === 403) {
                    break;   // quota épuisé ou clé refusée : on s'arrête
                }

                continue;
            }
            foreach ((array) $r->json('items', []) as $it) {
                $vid = $it['id']['videoId'] ?? null;
                if ($vid && ! isset($origin[$vid])) {
                    $ids[] = $vid;
                    $origin[$vid] = ['label' => $label, 'channel' => $chan];
                }
            }
        }
        $ids = array_slice(array_unique($ids), 0, 50);
        $live = $ids ? $this->details($ids, $key) : [];
        $block = array_map('mb_strtolower', $this->lines('yt_blocklist'));
        $auto = (bool) $this->algo->get('yt_auto_publish');
        foreach ($live as $vid => $v) {
            $title = (string) ($v['snippet']['title'] ?? '');
            $low = mb_strtolower($title);
            if (! ($v['status']['embeddable'] ?? false) || ($v['snippet']['liveBroadcastContent'] ?? '') !== 'live') {
                continue;
            }
            if (! $origin[$vid]['channel'] && $this->blocked($low, $block)) {
                continue;
            }
            $out['found']++;
            [$home, $away] = self::teams($title);
            $row = [
                'title' => mb_substr(html_entity_decode($title, ENT_QUOTES), 0, 160), 'home' => mb_substr($home, 0, 80), 'away' => mb_substr($away, 0, 80),
                'sport' => self::sport($low.' '.mb_strtolower($origin[$vid]['label'])),
                'country' => self::country($title.' '.$origin[$vid]['label'].' '.($v['snippet']['channelTitle'] ?? '')),
                'channel' => mb_substr((string) ($v['snippet']['channelTitle'] ?? ''), 0, 120),
                'thumbnail' => $v['snippet']['thumbnails']['high']['url'] ?? ($v['snippet']['thumbnails']['medium']['url'] ?? null),
                'stream_url' => 'https://www.youtube.com/watch?v='.$vid, 'status' => 'direct',
                'starts_at' => $v['liveStreamingDetails']['actualStartTime'] ?? now(),
                'peak' => (int) ($v['liveStreamingDetails']['concurrentViewers'] ?? 0), 'updated_at' => now(),
            ];
            $existing = DB::table('live_streams')->where('source', 'youtube')->where('external_id', $vid)->first();
            if ($existing) {
                DB::table('live_streams')->where('id', $existing->id)->update(array_intersect_key($row, array_flip(['title', 'thumbnail', 'status', 'peak', 'updated_at'])));
            } else {
                DB::table('live_streams')->insert($row + [
                    'source' => 'youtube', 'external_id' => $vid, 'owner_name' => $row['channel'], 'rights_ok' => true,
                    'approved' => $auto || $origin[$vid]['channel'], 'created_at' => now(),
                ]);
                $out['added']++;
            }
        }
        // Directs YouTube qui ne sont plus en cours → terminés (le replay reste la même vidéo)
        $still = DB::table('live_streams')->where('source', 'youtube')->where('status', 'direct')->whereNotIn('external_id', array_keys($live))->pluck('external_id')->all();
        if ($still) {
            $check = $this->details(array_slice($still, 0, 50), $key);
            foreach ($still as $vid) {
                if (($check[$vid]['snippet']['liveBroadcastContent'] ?? 'none') !== 'live') {
                    DB::table('live_streams')->where('source', 'youtube')->where('external_id', $vid)
                        ->update(['status' => 'termine', 'replay_url' => 'https://www.youtube.com/watch?v='.$vid, 'updated_at' => now()]);
                    $out['ended']++;
                }
            }
        }
        // Ménage : directs YouTube non validés de plus de 2 jours
        DB::table('live_streams')->where('source', 'youtube')->where('approved', false)->where('created_at', '<', now()->subDays(2))->delete();
        Cache::put('frikip.yt.last', ['at' => now()->toIso8601String()] + $out, now()->addDays(7));
        if ($out['error']) {
            Log::warning('Recherche des directs YouTube : '.$out['error']);
        }

        return $out;
    }

    private function details(array $ids, string $key): array
    {
        try {
            $r = Http::connectTimeout(4)->timeout(10)->get(self::API.'/videos', ['part' => 'snippet,status,liveStreamingDetails', 'id' => implode(',', $ids), 'key' => $key]);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ((array) $r->json('items', []) as $v) {
            $out[$v['id']] = $v;
        }

        return $out;
    }

    /** Accepte un identifiant (UC…), une adresse /channel/UC…, ou un @pseudo (résolu une seule fois puis gardé). */
    private function channelId(string $c, string $key): ?string
    {
        if (preg_match('~(UC[A-Za-z0-9_-]{20,24})~', $c, $m)) {
            return $m[1];
        }
        if (! preg_match('~@([A-Za-z0-9._-]{3,40})~', $c, $m)) {
            return null;
        }
        return Cache::remember('frikip.yt.handle.'.strtolower($m[1]), now()->addDays(30), function () use ($m, $key) {
            try {
                return Http::connectTimeout(4)->timeout(8)->get(self::API.'/channels', ['part' => 'id', 'forHandle' => '@'.$m[1], 'key' => $key])->json('items.0.id');
            } catch (Throwable) {
                return null;
            }
        });
    }

    private function blocked(string $title, array $block): bool
    {
        foreach ($block as $w) {
            if ($w !== '' && str_contains($title, $w)) {
                return true;
            }
        }

        return false;
    }

    /** « Horoya AC vs Hafia FC | Ligue 1 » → ['Horoya AC', 'Hafia FC'] ; sinon [titre, '']. */
    public static function teams(string $title): array
    {
        $t = trim(preg_replace('/\s*[|•#\[(].*$/u', '', html_entity_decode($title, ENT_QUOTES)));
        $t = preg_replace('/^(?:🔴|live|en direct|direct|live\s*:)\s*[:\-–]?\s*/iu', '', $t);
        if (preg_match('/^(.{2,60}?)\s+(?:vs\.?|v|contre|x|–|-)\s+(.{2,60})$/iu', $t, $m)) {
            $away = trim(preg_replace('/\s+(?:en direct|live|direct)\b.*$/iu', '', $m[2]));

            return [trim($m[1]), $away];
        }

        return [mb_substr($t ?: $title, 0, 80), ''];
    }

    public static function sport(string $text): string
    {
        foreach (['Basket' => ['basket', 'nba', 'bal '], 'Lutte' => ['lutte', 'laamb', 'wrestling'], 'Judo' => ['judo'], 'Handball' => ['handball', 'hand-ball'],
            'Tennis' => ['tennis'], 'Athlétisme' => ['athlétisme', 'athletics', 'marathon'], 'Natation' => ['natation', 'swimming']] as $sport => $words) {
            foreach ($words as $w) {
                if (str_contains($text, $w)) {
                    return $sport;
                }
            }
        }

        return in_array('Football', SportFields::sports(), true) ? 'Football' : SportFields::sports()[0];
    }

    public static function country(string $text): string
    {
        $low = mb_strtolower($text);
        $extra = ['Sénégal' => ['senegal', 'dakar'], "Côte d'Ivoire" => ['ivoire', 'ivory', 'abidjan'], 'Guinée' => ['guinee', 'conakry', 'féguifoot', 'feguifoot'],
            'Ghana' => ['ghana', 'gpl'], 'Nigeria' => ['nigeria', 'npfl'], 'Mali' => ['mali', 'bamako'], 'Cameroun' => ['cameroon', 'cameroun']];
        foreach (Region::names() as $c) {
            if (str_contains($low, mb_strtolower($c))) {
                return $c;
            }
        }
        foreach ($extra as $c => $words) {
            foreach ($words as $w) {
                if (str_contains($low, $w)) {
                    return $c;
                }
            }
        }

        return (string) config('terrain.country_default', 'Guinée');
    }
}
