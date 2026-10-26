<?php

namespace App\Terrain\Growth;

use App\Terrain\Region;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Diffuseurs officiels des grands championnats (Admin > Diffuseurs) : pour chaque match de Premier League,
 * Ligue des champions, Liga, Serie A, Bundesliga…, Frikip montre où le regarder en direct dans le pays du visiteur.
 * Avec un contrat de diffusion (« contract » + « embed_url »), le flux du diffuseur se regarde directement dans Frikip.
 */
class Broadcasters
{
    /** Compétitions affichées dans « Grands championnats » (identifiants API-Football). */
    public const BIG = [2, 39, 140, 135, 78, 61, 3, 12, 6, 1];

    /** @return list<object> diffuseurs actifs pour cette compétition et ce pays, le partenaire sous contrat d'abord */
    public function for(?int $leagueId, ?string $country = null): array
    {
        $country ??= Region::viewer();
        try {
            $rows = DB::table('broadcasters')->where('active', true)->orderByDesc('contract')->orderBy('priority')->orderBy('name')->get();
        } catch (Throwable) {
            return [];
        }

        return $rows->filter(function ($b) use ($leagueId, $country) {
            $comps = json_decode((string) $b->competitions, true) ?: [];
            $countries = json_decode((string) $b->countries, true) ?: [];

            return (! $comps || ($leagueId && in_array($leagueId, array_map('intval', $comps), true))) && (! $countries || in_array($country, $countries, true));
        })->values()->all();
    }

    /** Flux à regarder dans Frikip : direct relié au match (Admin > Direct), sinon diffuseur partenaire sous contrat. */
    public function inFrikip(array $match): ?array
    {
        try {
            $s = DB::table('live_streams')->where('fixture_id', $match['id'])->where('approved', true)->orderByDesc('id')->first();
            if ($s) {
                return ['type' => 'stream', 'stream' => $s];
            }
        } catch (Throwable) {
        }
        // API du diffuseur partenaire (LIVE_PARTNER_URL dans .env)
        if ($link = app(PartnerStreams::class)->streamFor($match)) {
            return ['type' => 'partner', 'broadcaster' => (object) ['id' => 0, 'name' => PartnerStreams::name(), 'url' => $link], 'player' => Live::embed($link)];
        }
        foreach ($this->for($match['league_id'] ?? null) as $b) {
            if ($b->contract && $b->embed_url) {
                return ['type' => 'partner', 'broadcaster' => $b, 'player' => Live::embed($b->embed_url)];
            }
        }

        return null;
    }

    public function click(int $id): ?object
    {
        try {
            DB::table('broadcasters')->where('id', $id)->increment('clicks');

            return DB::table('broadcasters')->find($id);
        } catch (Throwable) {
            return null;
        }
    }
}
