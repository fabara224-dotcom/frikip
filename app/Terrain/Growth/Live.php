<?php

namespace App\Terrain\Growth;

use App\Terrain\Region;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Matchs en direct : le club (ou l'admin) diffuse avec YouTube Live, Facebook Live ou un service de diffusion (lien .m3u8),
 * Frikip affiche le direct avec le score, le nombre de spectateurs et une pub avant le match (sauf « Sans pub »).
 */
class Live
{
    public const STATUS = ['prevu' => 'À venir', 'direct' => 'En direct', 'termine' => 'Terminé'];

    /** Directs en cours, approuvés, ceux du pays du visiteur d'abord. */
    public function onAir(int $limit = 2): array
    {
        try {
            $country = Region::viewer();

            return DB::table('live_streams')->where('approved', true)->where('status', 'direct')
                ->orderByRaw('case when country = ? then 0 else 1 end', [$country])->orderByDesc('peak')->limit($limit)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Comment afficher le lien de diffusion.
     *
     * @return array{type: string, src: string}
     */
    public static function embed(?string $url): array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return ['type' => 'none', 'src' => ''];
        }
        // YouTube : watch?v=, youtu.be/, /live/, /embed/
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|live/|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $url, $m)) {
            return ['type' => 'iframe', 'src' => 'https://www.youtube.com/embed/'.$m[1].'?autoplay=1&mute=1&playsinline=1&rel=0'];
        }
        if (preg_match('~(facebook\.com|fb\.watch)/~', $url)) {
            return ['type' => 'iframe', 'src' => 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($url).'&show_text=false&autoplay=true&mute=true'];
        }
        if (preg_match('~^https://(player\.vimeo\.com|vimeo\.com)/(?:video/)?(\d+)~', $url, $m)) {
            return ['type' => 'iframe', 'src' => 'https://player.vimeo.com/video/'.$m[2].'?autoplay=1&muted=1'];
        }
        // Services de diffusion (Cloudflare Stream, Mux, api.video, serveur du club…) : flux HLS
        if (preg_match('~^https://[^\s]+\.m3u8(\?.*)?$~i', $url)) {
            return ['type' => 'hls', 'src' => $url];
        }
        if (preg_match('~^https://[^\s]+\.(mp4|webm)(\?.*)?$~i', $url)) {
            return ['type' => 'video', 'src' => $url];
        }
        // Lien d'intégration déjà prêt (Cloudflare Stream iframe, Dailymotion…)
        if (preg_match('~^https://(iframe\.videodelivery\.net|customer-[a-z0-9]+\.cloudflarestream\.com|www\.dailymotion\.com/embed|geo\.dailymotion\.com)/~', $url)) {
            return ['type' => 'iframe', 'src' => $url];
        }

        return ['type' => 'link', 'src' => $url];
    }

    /** « Horoya – Hafia », ou le titre seul pour un direct trouvé sur YouTube sans équipes reconnues. */
    public static function label(object $s): string
    {
        return $s->away !== '' && $s->away !== null ? $s->home.' – '.$s->away : $s->home;
    }

    /** Le score est-il tenu à jour ? (oui pour les directs des clubs et de l'admin, non pour ceux trouvés sur YouTube) */
    public static function hasScore(object $s): bool
    {
        return ($s->source ?? 'club') !== 'youtube';
    }

    /** Lien accepté ? (https, et un format que Frikip sait afficher ou au moins ouvrir) */
    public static function validUrl(string $url): bool
    {
        return (bool) preg_match('~^https://~i', trim($url)) && strlen($url) <= 500;
    }

    /** Prévenir les membres du pays quand le direct commence (une fois). */
    public function announce(object $s): void
    {
        try {
            if (\Illuminate\Support\Facades\Cache::add("live.announced.{$s->id}", 1, now()->addDay())) {
                $keys = DB::table('members')->where('country', $s->country)->where('last_seen_at', '>', now()->subDays(14))->limit(2000)->pluck('user_key');
                app(Notifier::class)->sendMany($keys, 'event', __('En direct : :h – :a', ['h' => $s->home, 'a' => $s->away]), $s->competition ?: $s->title, route('live.show', $s->id));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Spectateur présent (appelé toutes les 30 s par la page du direct) ; renvoie le nombre de spectateurs. */
    public function heartbeat(int $id, string $viewer): int
    {
        try {
            DB::table('live_viewers')->upsert([['stream_id' => $id, 'viewer' => $viewer, 'seen_at' => now()]], ['stream_id', 'viewer'], ['seen_at']);
            $n = $this->viewers($id);
            DB::table('live_streams')->where('id', $id)->where('peak', '<', $n)->update(['peak' => $n]);

            return $n;
        } catch (Throwable) {
            return 0;
        }
    }

    public function viewers(int $id): int
    {
        try {
            return (int) DB::table('live_viewers')->where('stream_id', $id)->where('seen_at', '>', now()->subSeconds(75))->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
