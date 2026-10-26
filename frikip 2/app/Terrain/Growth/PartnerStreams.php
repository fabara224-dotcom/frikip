<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * API d'un diffuseur partenaire : quand tu signes avec un diffuseur (ou un fournisseur de flux sous licence),
 * il te donne une adresse d'API qui renvoie le lien du flux vidéo d'un match. Il suffit de la mettre dans .env :
 *
 *   LIVE_PARTNER_NAME="Nom du partenaire"
 *   LIVE_PARTNER_URL="https://api.partenaire.com/v1/streams?fixture={fixture}&league={league}"
 *   LIVE_PARTNER_KEY=la-cle-fournie
 *   LIVE_PARTNER_HEADER=Authorization        (en-tête qui porte la clé ; vide = « ?key=… » ajouté à l'adresse)
 *   LIVE_PARTNER_FIELD=data.hls_url          (où se trouve le lien dans la réponse JSON)
 *
 * Variables disponibles dans l'adresse : {fixture} (numéro API-Football), {league}, {home}, {away}, {date} (AAAA-MM-JJ), {country}.
 * Le lien renvoyé (HLS .m3u8, MP4 ou page d'intégration) est lu dans le lecteur de Frikip, sur la page du match.
 */
class PartnerStreams
{
    public static function configured(): bool
    {
        return (string) config('terrain.live_partner.url') !== '';
    }

    public static function name(): string
    {
        return (string) (config('terrain.live_partner.name') ?: 'Partenaire officiel');
    }

    /** Lien du flux pour ce match, ou null (gardé 2 minutes pour ne pas interroger l'API à chaque visite). */
    public function streamFor(array $match): ?string
    {
        if (! self::configured() || ($match['source'] ?? null) !== 'api' || Cache::has('frikip.partner.down')) {
            return null;
        }
        $fixture = preg_replace('/^api-/', '', (string) $match['id']);

        return Cache::remember('frikip.partner.'.$fixture, 120, function () use ($match, $fixture) {
            $vars = ['{fixture}' => $fixture, '{league}' => (string) ($match['league_id'] ?? ''), '{home}' => rawurlencode((string) $match['home']),
                '{away}' => rawurlencode((string) $match['away']), '{date}' => date('Y-m-d', (int) ($match['ts'] ?? time())),
                '{country}' => rawurlencode(\App\Terrain\Region::viewer())];
            $url = strtr((string) config('terrain.live_partner.url'), $vars);
            $key = (string) config('terrain.live_partner.key');
            $header = (string) config('terrain.live_partner.header');
            try {
                $req = Http::acceptJson()->connectTimeout(4)->timeout(8);
                if ($key !== '' && $header !== '') {
                    $req = $req->withHeaders([$header => strcasecmp($header, 'Authorization') === 0 && ! str_contains($key, ' ') ? 'Bearer '.$key : $key]);
                } elseif ($key !== '') {
                    $url .= (str_contains($url, '?') ? '&' : '?').'key='.rawurlencode($key);
                }
                $r = $req->get($url);
                if (! $r->successful()) {
                    return null;
                }
                $link = Arr::get((array) $r->json(), (string) (config('terrain.live_partner.field') ?: 'url'));

                return is_string($link) && str_starts_with($link, 'https://') ? $link : null;
            } catch (Throwable $e) {
                Log::warning('API du partenaire de diffusion injoignable : '.$e->getMessage());
                Cache::put('frikip.partner.down', 1, 120);

                return null;
            }
        }) ?: null;
    }
}
