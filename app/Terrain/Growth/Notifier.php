<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifications : toujours dans le site (cloche), et selon les préférences du membre par e-mail, SMS ou WhatsApp.
 * SMS / WhatsApp : Twilio (TWILIO_SID, TWILIO_TOKEN, TWILIO_SMS_FROM, TWILIO_WHATSAPP_FROM dans .env).
 * Sans configuration, seuls la cloche et l'e-mail (journal par défaut) fonctionnent.
 */
class Notifier
{
    /** Types envoyés aussi hors du site (les autres restent dans la cloche). */
    private const EXTERNAL = ['moderation', 'message', 'call_missed', 'offer', 'profile_view', 'event', 'challenge', 'alert', 'verification', 'payment', 'referral'];

    /** Types envoyés en notification sur le téléphone (Web Push), sans limite de fréquence. */
    private const PUSH = ['moderation', 'message', 'call', 'call_missed', 'comment', 'offer', 'payment', 'verification', 'star', 'profile_view', 'event', 'alert', 'video'];

    public function send(string $keyOrAthlete, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        try {
            $m = app(Members::class)->find($keyOrAthlete);
            $key = $m->user_key ?? $keyOrAthlete;
            DB::table('notifications_feed')->insert([
                'user_key' => $key, 'type' => $type, 'title' => $title, 'body' => $body, 'url' => $url, 'created_at' => now(), 'updated_at' => now(),
            ]);
            // Notification sur le téléphone / l'ordinateur (même site fermé) : chaque message, appel, réponse…
            if (in_array($type, self::PUSH, true) && ($m->notify_push ?? true)) {
                app(WebPush::class)->sendTo($key, [
                    'title' => $title, 'body' => (string) $body, 'url' => $url ?: url('/'), 'type' => $type,
                    // un seul encart par conversation / par appel (les suivants le remplacent)
                    'tag' => $type.':'.substr(sha1((string) $url), 0, 12),
                ]);
            }
            if (! $m || ! in_array($type, self::EXTERNAL, true)) {
                return;
            }
            // Pas plus d'un envoi externe du même type toutes les 15 minutes (évite de spammer les téléphones)
            $lock = "notif:{$key}:{$type}";
            if (cache()->has($lock)) {
                return;
            }
            cache()->put($lock, 1, now()->addMinutes(15));
            $text = $title.($body ? ' – '.$body : '').($url ? ' '.$url : '');
            if ($m->notify_email && $m->email) {
                Mail::raw($text."\n\n— ".config('terrain.app_name'), fn ($msg) => $msg->to($m->email)->subject($title));
            }
            if ($m->phone && $m->notify_sms) {
                $this->twilio($m->phone, $text, false);
            }
            if ($m->phone && $m->notify_whatsapp) {
                $this->twilio($m->phone, $text, true);
            }
        } catch (Throwable $e) {
            Log::warning('Notification non envoyée : '.$e->getMessage());
        }
    }

    public function sendMany(iterable $keys, string $type, string $title, ?string $body = null, ?string $url = null): int
    {
        $n = 0;
        foreach ($keys as $k) {
            $this->send($k, $type, $title, $body, $url);
            $n++;
        }

        return $n;
    }

    /** Envoi direct d'un SMS (codes de connexion). */
    public function sms(string $phone, string $text): bool
    {
        return $this->twilio($phone, $text, false);
    }

    public static function smsConfigured(): bool
    {
        return (bool) (config('terrain.twilio.sid') && config('terrain.twilio.token') && config('terrain.twilio.sms_from'));
    }

    private function twilio(string $phone, string $text, bool $whatsapp): bool
    {
        $c = config('terrain.twilio');
        $from = $whatsapp ? ($c['whatsapp_from'] ?? '') : ($c['sms_from'] ?? '');
        if (! $c['sid'] || ! $c['token'] || ! $from) {
            return false;
        }
        $to = self::e164($phone);
        $r = Http::asForm()->withBasicAuth($c['sid'], $c['token'])->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$c['sid']}/Messages.json", [
                'To' => ($whatsapp ? 'whatsapp:' : '').$to, 'From' => ($whatsapp ? 'whatsapp:' : '').$from, 'Body' => mb_substr($text, 0, 1500),
            ]);
        if (! $r->successful()) {
            Log::warning('Twilio : '.$r->status().' '.$r->body());
        }

        return $r->successful();
    }

    /** « 622 12 34 56 » → « +224622123456 » (indicatif de la Guinée par défaut). */
    /** Numéro au format international ; sans indicatif, celui du pays donné (sinon du visiteur) est ajouté. */
    public static function e164(string $phone, ?string $country = null): string
    {
        $d = preg_replace('/\D/', '', $phone);
        if (str_starts_with(trim($phone), '+')) {
            return '+'.$d;
        }
        if (str_starts_with($d, '00')) {
            return '+'.substr($d, 2);
        }

        $dial = \App\Terrain\Region::dial($country ?? \App\Terrain\Region::viewer());
        if (str_starts_with($d, $dial) && strlen($d) > strlen($dial) + 7) {
            return '+'.$d;   // indicatif déjà tapé sans le « + »
        }

        return '+'.$dial.ltrim($d, '0');
    }

    // ------------------------------------------------------------------ cloche

    public function forUser(string $key, int $limit = 50): array
    {
        return DB::table('notifications_feed')->where('user_key', $key)->orderByDesc('id')->limit($limit)->get()->all();
    }

    public function unread(?string $key): int
    {
        if (! $key) {
            return 0;
        }
        try {
            return DB::table('notifications_feed')->where('user_key', $key)->whereNull('read_at')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public function markAllRead(string $key): void
    {
        DB::table('notifications_feed')->where('user_key', $key)->whereNull('read_at')->update(['read_at' => now()]);
    }
}
