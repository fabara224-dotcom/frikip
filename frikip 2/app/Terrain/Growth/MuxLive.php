<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Diffusion en direct par Frikip avec Mux (service payant, https://www.mux.com) :
 * le club filme avec son téléphone (Larix Broadcaster, Prism Live…) ou une caméra (OBS), Mux reçoit la vidéo
 * et Frikip la diffuse. Le direct passe « En direct » tout seul quand la vidéo arrive, puis « Terminé » avec le replay.
 *
 * Réglages .env : MUX_TOKEN_ID, MUX_TOKEN_SECRET (Mux > Settings > Access Tokens, droits Mux Video « Read » et « Write »)
 * et, facultatif, MUX_WEBHOOK_SECRET (Mux > Settings > Webhooks, adresse https://ton-site/webhooks/mux).
 */
class MuxLive
{
    private const API = 'https://api.mux.com/video/v1';

    /** Adresse de diffusion à saisir dans l'application de la caméra ou du téléphone. */
    public const RTMP = 'rtmps://global-live.mux.com:443/app';

    public const RTMP_PLAIN = 'rtmp://global-live.mux.com:5222/app';

    public static function configured(): bool
    {
        return config('terrain.mux.token_id') && config('terrain.mux.token_secret');
    }

    private function http()
    {
        return Http::timeout(20)->acceptJson()->withBasicAuth((string) config('terrain.mux.token_id'), (string) config('terrain.mux.token_secret'));
    }

    /** @return array{id: string, stream_key: string, playback_id: string} */
    public function create(): array
    {
        $r = $this->http()->post(self::API.'/live-streams', [
            'playback_policy' => ['public'],
            'new_asset_settings' => ['playback_policy' => ['public']],   // le match est enregistré : replay automatique
            'latency_mode' => in_array(config('terrain.mux.latency'), ['low', 'reduced', 'standard'], true) ? config('terrain.mux.latency') : 'low',
            'reconnect_window' => 120,   // coupure réseau de moins de 2 minutes : le direct reprend sans s'arrêter
        ]);
        $d = $r->json('data');
        if (! $r->successful() || empty($d['id']) || empty($d['stream_key']) || empty($d['playback_ids'][0]['id'])) {
            Log::warning('Mux : création du direct impossible : '.$r->body());
            throw new RuntimeException($r->status() === 401
                ? 'Clés Mux refusées : vérifie MUX_TOKEN_ID et MUX_TOKEN_SECRET dans le fichier .env.'
                : 'Le service de diffusion ne répond pas. Réessaie dans un instant.');
        }

        return ['id' => $d['id'], 'stream_key' => $d['stream_key'], 'playback_id' => $d['playback_ids'][0]['id']];
    }

    public static function hls(string $playbackId): string
    {
        return 'https://stream.mux.com/'.$playbackId.'.m3u8';
    }

    public static function poster(string $playbackId): string
    {
        return 'https://image.mux.com/'.$playbackId.'/thumbnail.jpg?width=960';
    }

    /**
     * Met à jour l'état du direct depuis Mux (au plus une fois toutes les 20 s par direct).
     * Utile sans webhook, par exemple sur l'ordinateur de développement.
     */
    public function refresh(object $s): object
    {
        if (empty($s->mux_id) || $s->status === 'termine' || ! self::configured()) {
            return $s;
        }
        if (! Cache::add('frikip.mux.check.'.$s->id, 1, 20)) {
            return $s;
        }
        try {
            $r = $this->http()->get(self::API.'/live-streams/'.$s->mux_id);
            if ($r->successful()) {
                $this->apply($s, (string) $r->json('data.status'), (array) $r->json('data.recent_asset_ids', []));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return DB::table('live_streams')->find($s->id) ?? $s;
    }

    /** active → « En direct » ; idle après un direct → « Terminé » avec le replay. */
    public function apply(object $s, string $muxStatus, array $assetIds = []): void
    {
        if ($muxStatus === 'active' && $s->status !== 'direct') {
            DB::table('live_streams')->where('id', $s->id)->update(['status' => 'direct', 'started_live' => true, 'updated_at' => now()]);
            if ($s->approved) {
                app(Live::class)->announce($s);
            }
        } elseif ($muxStatus === 'idle' && $s->status === 'direct' && ! empty($s->started_live)) {
            DB::table('live_streams')->where('id', $s->id)->update(['status' => 'termine', 'replay_url' => $this->replay(end($assetIds) ?: null), 'updated_at' => now()]);
        }
    }

    /** Lien du replay (enregistrement du direct). */
    public function replay(?string $assetId): ?string
    {
        if (! $assetId) {
            return null;
        }
        try {
            $pid = $this->http()->get(self::API.'/assets/'.$assetId)->json('data.playback_ids.0.id');

            return $pid ? self::hls($pid) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Nouvelle clé de diffusion (si l'ancienne a été partagée par erreur). */
    public function resetKey(string $muxId): ?string
    {
        try {
            return $this->http()->post(self::API.'/live-streams/'.$muxId.'/reset-stream-key')->json('data.stream_key');
        } catch (Throwable) {
            return null;
        }
    }

    /** Fin du match : coupe la diffusion et garde le replay. */
    public function end(object $s): void
    {
        if (empty($s->mux_id) || ! self::configured()) {
            return;
        }
        $this->finish($s->mux_id);
        try {
            $assets = (array) $this->http()->get(self::API.'/live-streams/'.$s->mux_id)->json('data.recent_asset_ids', []);
            if ($replay = $this->replay(end($assets) ?: null)) {
                DB::table('live_streams')->where('id', $s->id)->update(['replay_url' => $replay]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Coupe le direct chez Mux (fin du match, suppression) pour ne plus rien payer. */
    public function finish(string $muxId): void
    {
        try {
            $this->http()->put(self::API.'/live-streams/'.$muxId.'/complete');
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Vérifie la signature d'un webhook Mux (en-tête « Mux-Signature: t=…,v1=… »). */
    public static function validSignature(string $header, string $body): bool
    {
        $secret = (string) config('terrain.mux.webhook_secret');
        if ($secret === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $p) {
            [$k, $v] = array_pad(explode('=', trim($p), 2), 2, '');
            $parts[$k] = $v;
        }
        if (empty($parts['t']) || empty($parts['v1']) || abs(time() - (int) $parts['t']) > 300) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $parts['t'].'.'.$body, $secret), $parts['v1']);
    }
}
