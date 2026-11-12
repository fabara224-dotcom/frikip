<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Lives des membres depuis leur téléphone, avec Cloudflare Stream (WebRTC) :
 * le navigateur envoie la caméra par WHIP, les spectateurs regardent par WHEP. Rien à installer.
 *
 * Règle affichée et acceptée avant chaque live : on commente un match, on ne refilme pas une télé
 * ni un écran (droits de diffusion).
 */
class UserLive
{
    private const API = 'https://api.cloudflare.com/client/v4/accounts/';

    public static function configured(): bool
    {
        return (string) config('terrain.cloudflare.account') !== '' && (string) config('terrain.cloudflare.token') !== '';
    }

    private function http()
    {
        return Http::withToken((string) config('terrain.cloudflare.token'))->acceptJson()->connectTimeout(5)->timeout(15);
    }

    /** Peut-il lancer un live ? Retourne null si oui, sinon la raison. */
    public function refusal(?array $u): ?string
    {
        $algo = app(Algorithm::class);
        if (! $u) {
            return __('Connecte-toi pour lancer un live.');
        }
        if (! $algo->get('lives_enabled')) {
            return __('Les lives des membres sont fermés pour le moment.');
        }
        if (! self::configured()) {
            return __('Les lives arrivent très bientôt sur FootPro.');
        }
        $days = (int) $algo->get('lives_min_age_days');
        if ($days > 0) {
            $since = DB::table('members')->where('user_key', $u['id'])->value('created_at');
            if (! $since || \Illuminate\Support\Carbon::parse($since)->gt(now()->subDays($days))) {
                return trans_choice('Ton compte doit avoir au moins :n jour pour lancer un live.|Ton compte doit avoir au moins :n jours pour lancer un live.', $days, ['n' => $days]);
            }
        }
        if (DB::table('live_streams')->where('owner_key', $u['id'])->where('kind', 'membre')->where('status', 'direct')->exists()) {
            return null;   // il reprend son live en cours
        }

        return null;
    }

    /** Crée le direct chez Cloudflare et dans FootPro. */
    public function start(array $u, string $title, ?string $matchId, ?string $matchLabel): object
    {
        $acc = (string) config('terrain.cloudflare.account');
        try {
            $r = $this->http()->post(self::API.$acc.'/stream/live_inputs', [
                'meta' => ['name' => mb_substr(config('terrain.app_name').' · '.$title, 0, 120)],
                'recording' => ['mode' => 'automatic', 'timeoutSeconds' => 60],
                'deleteRecordingAfterDays' => 30,
            ]);
            $res = $r->json('result') ?? [];
            if (! $r->successful() || empty($res['uid']) || empty($res['webRTC']['url'])) {
                throw new RuntimeException((string) ($r->json('errors.0.message') ?: 'HTTP '.$r->status()));
            }
        } catch (Throwable $e) {
            Log::warning('Cloudflare Stream : création du live impossible', ['error' => $e->getMessage()]);
            throw new RuntimeException(__('Le service de live ne répond pas. Réessaie dans un instant.'));
        }
        $whep = $res['webRTCPlayback']['url'] ?? null;
        $id = DB::table('live_streams')->insertGetId([
            'title' => mb_substr($title, 0, 150), 'home' => $matchLabel ? mb_substr($matchLabel, 0, 100) : mb_substr($title, 0, 100), 'away' => '',
            'stream_url' => (string) $whep, 'sport' => 'Football', 'country' => \App\Terrain\Region::viewer(),
            'status' => 'prevu', 'approved' => (bool) app(Algorithm::class)->get('lives_auto'),
            'owner_key' => $u['id'], 'owner_name' => $u['name'] ?? null, 'source' => 'cloudflare', 'kind' => 'membre',
            'cf_uid' => $res['uid'], 'whip_url' => $res['webRTC']['url'], 'whep_url' => $whep, 'fixture_id' => $matchId,
            'competition' => $matchLabel ? __('Commentaire en direct') : null,
            'starts_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('live_streams')->find($id);
    }

    /** Fin du live : statut « terminé » et suppression de l'entrée chez Cloudflare (l'enregistrement reste 30 jours). */
    public function stop(object $s): void
    {
        DB::table('live_streams')->where('id', $s->id)->update(['status' => 'termine', 'updated_at' => now()]);
        if ($s->cf_uid && self::configured()) {
            try {
                $this->http()->delete(self::API.config('terrain.cloudflare.account').'/stream/live_inputs/'.$s->cf_uid);
            } catch (Throwable) {
            }
        }
    }

    /** Lives restés ouverts trop longtemps : on les arrête (appelé à chaque ouverture de la page En direct). */
    public function sweep(): void
    {
        try {
            $max = max(10, (int) app(Algorithm::class)->get('lives_max_minutes'));
            $old = DB::table('live_streams')->where('kind', 'membre')->whereIn('status', ['direct', 'prevu'])->where('starts_at', '<', now()->subMinutes($max))->get();
            foreach ($old as $s) {
                $this->stop($s);
            }
        } catch (Throwable) {
        }
    }
}
