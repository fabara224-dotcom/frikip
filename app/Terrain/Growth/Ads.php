<?php

namespace App\Terrain\Growth;

use App\Terrain\Region;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Publicités vidéo ajoutées par l'admin (Admin > Publicités).
 * Elles s'intercalent dans le fil (une toutes les N vidéos) et avant les matchs en direct.
 * Personne ne voit de pub avec l'option « Sans pub », Premium ou Structure Pro.
 */
class Ads
{
    private ?array $pool = null;

    public function __construct(private Algorithm $algo, private Members $members)
    {
    }

    /** Ce visiteur doit-il voir des pubs ? */
    public function shouldShow(?array $user): bool
    {
        if (! $this->algo->get('ads_enabled')) {
            return false;
        }
        if (! $user) {
            return (bool) $this->algo->get('ads_guests');
        }

        return ! $this->members->isAdFree();
    }

    /** Pubs actives pour ce pays (et ce sport si la pub est ciblée). */
    public function eligible(?string $sport = null): array
    {
        if ($this->pool === null) {
            try {
                $today = now()->toDateString();
                $country = Region::viewer();
                $this->pool = DB::table('ads')->where('active', true)
                    ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
                    ->where(fn ($q) => $q->where('max_impressions', 0)->orWhereColumn('impressions', '<', 'max_impressions'))
                    ->where(fn ($q) => $q->whereNotNull('video_path')->orWhereNotNull('video_url'))
                    ->get()->filter(function ($ad) use ($country) {
                        $c = json_decode((string) $ad->countries, true) ?: [];

                        return ! $c || in_array($country, $c, true);
                    })->values()->all();
            } catch (Throwable) {
                $this->pool = [];
            }
        }

        return array_values(array_filter($this->pool, fn ($ad) => ! $sport || $sport === 'Tous' || ! $ad->sport || $ad->sport === $sport));
    }

    /** Tire une pub au hasard, les pubs au poids élevé reviennent plus souvent. */
    public function pick(?string $sport = null, array $avoid = []): ?object
    {
        $list = $this->eligible($sport);
        if (count($list) > 1 && $avoid) {
            $list = array_values(array_filter($list, fn ($a) => ! in_array($a->id, $avoid, true))) ?: $list;
        }
        if (! $list) {
            return null;
        }
        $total = array_sum(array_map(fn ($a) => max(1, (int) $a->weight), $list));
        $r = random_int(1, $total);
        foreach ($list as $a) {
            $r -= max(1, (int) $a->weight);
            if ($r <= 0) {
                return $a;
            }
        }

        return $list[0];
    }

    /**
     * Intercale des pubs dans une page du fil.
     *
     * @param  int  $offset  position de la première vidéo de cette page dans le fil complet
     */
    public function inject(array $items, int $offset, ?array $user, ?string $sport = null): array
    {
        if (! $items || ! $this->shouldShow($user)) {
            return $items;
        }
        $every = max(3, min(30, (int) $this->algo->get('ads_every')));
        $out = [];
        $last = [];
        foreach (array_values($items) as $i => $it) {
            $out[] = $it;
            if (($offset + $i + 1) % $every === 0 && ($ad = $this->pick($sport, $last))) {
                $out[] = ['ad' => $this->present($ad)];
                $last = [$ad->id];
            }
        }

        return $out;
    }

    public function present(object $ad): array
    {
        return [
            'id' => $ad->id, 'title' => $ad->title, 'advertiser' => $ad->advertiser, 'cta' => $ad->cta ?: 'En savoir plus',
            'video' => $ad->video_path ? route('ads.video', $ad->id) : $ad->video_url,
            'poster' => $ad->poster_path ? route('ads.poster', $ad->id) : null,
            'link' => $ad->link ? route('ads.click', $ad->id) : null,
        ];
    }

    public function impression(int $id): void
    {
        $this->count($id, 'impressions');
    }

    public function click(int $id): void
    {
        $this->count($id, 'clicks');
    }

    private function count(int $id, string $col): void
    {
        try {
            DB::table('ads')->where('id', $id)->increment($col);
            $day = now()->toDateString();
            $n = DB::table('ad_daily')->where('ad_id', $id)->where('day', $day)->increment($col);
            if (! $n) {
                DB::table('ad_daily')->insertOrIgnore(['ad_id' => $id, 'day' => $day, 'impressions' => $col === 'impressions' ? 1 : 0, 'clicks' => $col === 'clicks' ? 1 : 0]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Dossier des vidéos de pub (hors du dossier public : elles passent par la route ads.video). */
    public static function dir(): string
    {
        $d = storage_path('app/pubs');
        if (! is_dir($d)) {
            mkdir($d, 0755, true);
        }

        return $d;
    }
}
