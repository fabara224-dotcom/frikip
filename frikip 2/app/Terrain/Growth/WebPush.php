<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Notifications sur le téléphone ou l'ordinateur, même quand le site est fermé (standard Web Push).
 * Fonctionne avec Chrome, Edge, Firefox, Samsung Internet sur Android et ordinateur, et Safari sur iPhone
 * (iOS 16.4 et plus, quand le site est ajouté à l'écran d'accueil). Aucun compte externe n'est nécessaire.
 *
 * Écrit sans bibliothèque supplémentaire, d'après les normes :
 *  - RFC 8291 (chiffrement du message, aes128gcm) ;
 *  - RFC 8292 (identification VAPID du serveur, jeton ES256).
 * Clés : php artisan terrain:cles-push (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY dans .env).
 * Le site doit être en HTTPS (ou ouvert sur 127.0.0.1 / localhost pour les essais).
 */
class WebPush
{
    // Préfixes DER (courbe P-256) pour transformer des clés brutes en clés lisibles par OpenSSL
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    private const SEC1_PREFIX = '30770201010420';

    private const SEC1_MIDDLE = 'a00a06082a8648ce3d030107a144034200';

    public static function configured(): bool
    {
        return (string) config('terrain.push.public_key') !== '' && (string) config('terrain.push.private_key') !== '';
    }

    public static function b64(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    /**
     * Nouvelle clé P-256. Plusieurs méthodes, car certaines installations de PHP (Herd / PHP 8.5 sur Mac)
     * n'ont pas de fichier openssl.cnf et refusent openssl_pkey_new() sans réglages.
     *
     * @return \OpenSSLAsymmetricKey
     */
    public static function newEcKey(?int $only = null)
    {
        $attempts = [
            fn () => @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]),
            // avec un fichier de configuration minimal et une longueur de clé indiquée
            function () {
                $cnf = sys_get_temp_dir().'/frikip-openssl.cnf';
                if (! is_file($cnf)) {
                    @file_put_contents($cnf, "[ req ]\ndistinguished_name = dn\n[ dn ]\n");
                }

                return @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'private_key_bits' => 2048, 'config' => $cnf]);
            },
            // avec la commande openssl du système (présente sur Mac et Linux)
            function () {
                if (! function_exists('proc_open')) {
                    return false;
                }
                $p = @proc_open(['openssl', 'ecparam', '-name', 'prime256v1', '-genkey', '-noout'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (! is_resource($p)) {
                    return false;
                }
                $pem = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($p);

                return $pem ? @openssl_pkey_get_private($pem) : false;
            },
        ];
        foreach ($attempts as $i => $try) {
            if ($only !== null && $only !== $i) {
                continue;
            }
            try {
                $k = $try();
            } catch (\Throwable) {
                $k = false;
            }
            if ($k && (openssl_pkey_get_details($k)['ec']['curve_name'] ?? null) === 'prime256v1') {
                return $k;
            }
        }
        while (openssl_error_string() !== false) {
        }
        throw new RuntimeException('OpenSSL ne sait pas créer de clé P-256 sur ce serveur.');
    }

    /** Nouvelle paire de clés VAPID. @return array{public: string, private: string} (base64url) */
    public static function generateKeys(): array
    {
        $k = self::newEcKey();
        $d = openssl_pkey_get_details($k)['ec'];

        return [
            'public' => self::b64("\x04".str_pad($d['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['y'], 32, "\0", STR_PAD_LEFT)),
            'private' => self::b64(str_pad($d['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    private static function pem(string $der, string $label): string
    {
        return "-----BEGIN {$label}-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END {$label}-----\n";
    }

    private static function publicKeyPem(string $point): string
    {
        return self::pem(hex2bin(self::SPKI_PREFIX).$point, 'PUBLIC KEY');
    }

    private static function privateKeyPem(string $d, string $point): string
    {
        return self::pem(hex2bin(self::SEC1_PREFIX).$d.hex2bin(self::SEC1_MIDDLE).$point, 'EC PRIVATE KEY');
    }

    /** Signature DER (OpenSSL) → 64 octets r||s (format JWT). */
    private static function derToRaw(string $der): string
    {
        $pos = 2;                                // 0x30, longueur
        $out = '';
        for ($i = 0; $i < 2; $i++) {             // 0x02, longueur, entier (r puis s)
            $len = ord($der[$pos + 1]);
            $out .= str_pad(ltrim(substr($der, $pos + 2, $len), "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    /** En-tête Authorization VAPID pour l'adresse du service de notification. */
    public static function vapidHeader(string $endpoint, ?int $now = null): string
    {
        $pub = self::unb64((string) config('terrain.push.public_key'));
        $priv = self::unb64((string) config('terrain.push.private_key'));
        $p = parse_url($endpoint);
        $aud = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
        $subject = (string) config('terrain.push.subject') ?: (str_starts_with((string) config('app.url'), 'https://') ? (string) config('app.url') : 'mailto:'.(config('terrain.email') ?: 'contact@terrain.app'));
        $jwtHead = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $jwtBody = self::b64(json_encode(['aud' => $aud, 'exp' => ($now ?? time()) + 12 * 3600, 'sub' => $subject], JSON_UNESCAPED_SLASHES));
        $key = openssl_pkey_get_private(self::privateKeyPem($priv, $pub));
        if (! $key || ! openssl_sign("{$jwtHead}.{$jwtBody}", $sig, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Clé VAPID invalide : relance « php artisan terrain:cles-push --force ».');
        }

        return 'vapid t='.$jwtHead.'.'.$jwtBody.'.'.self::b64(self::derToRaw($sig)).', k='.self::b64($pub);
    }

    /**
     * Chiffre le message pour un abonné (RFC 8291). $salt et $local ne servent qu'aux tests.
     *
     * @param  string  $p256dh  clé publique de l'abonné (base64url, 65 octets)
     * @param  string  $auth  secret de l'abonné (base64url, 16 octets)
     */
    public static function encrypt(string $payload, string $p256dh, string $auth, ?string $salt = null, $local = null): string
    {
        $uaPublic = self::unb64($p256dh);
        $authSecret = self::unb64($auth);
        if (strlen($uaPublic) !== 65 || strlen($authSecret) < 16) {
            throw new RuntimeException('Abonnement invalide.');
        }
        $local ??= self::newEcKey();
        $ld = openssl_pkey_get_details($local)['ec'];
        $asPublic = "\x04".str_pad($ld['x'], 32, "\0", STR_PAD_LEFT).str_pad($ld['y'], 32, "\0", STR_PAD_LEFT);
        $shared = openssl_pkey_derive(openssl_pkey_get_public(self::publicKeyPem($uaPublic)), $local, 32);
        if ($shared === false) {
            throw new RuntimeException('Échange de clés impossible.');
        }
        $shared = str_pad($shared, 32, "\0", STR_PAD_LEFT);
        $hmac = fn (string $key, string $data) => hash_hmac('sha256', $data, $key, true);

        $prkKey = $hmac($authSecret, $shared);
        $ikm = $hmac($prkKey, "WebPush: info\0".$uaPublic.$asPublic."\x01");
        $salt ??= random_bytes(16);
        $prk = $hmac($salt, $ikm);
        $cek = substr($hmac($prk, "Content-Encoding: aes128gcm\0\x01"), 0, 16);
        $nonce = substr($hmac($prk, "Content-Encoding: nonce\0\x01"), 0, 12);

        $tag = '';
        $cipher = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        return $salt.pack('N', 4096).chr(65).$asPublic.$cipher.$tag;
    }

    /**
     * Envoie une notification à tous les appareils d'un membre.
     *
     * @param  array{title: string, body?: string, url?: string, tag?: string, type?: string}  $msg
     * @return int nombre d'appareils atteints
     */
    public function sendTo(string $userKey, array $msg): int
    {
        if (! self::configured()) {
            return 0;
        }
        try {
            $subs = DB::table('push_subscriptions')->where('user_key', $userKey)->get();
        } catch (Throwable) {
            return 0;
        }
        $payload = json_encode($msg + ['icon' => asset('icons/icon-192.png')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ok = 0;
        foreach ($subs as $s) {
            try {
                $r = Http::timeout(10)->withHeaders([
                    'Authorization' => self::vapidHeader($s->endpoint),
                    'TTL' => ($msg['type'] ?? '') === 'call' ? '60' : '86400',
                    'Urgency' => in_array($msg['type'] ?? '', ['message', 'call', 'call_missed'], true) ? 'high' : 'normal',
                    'Content-Encoding' => 'aes128gcm',
                ])->withBody(self::encrypt($payload, $s->p256dh, $s->auth), 'application/octet-stream')->post($s->endpoint);
                if ($r->successful()) {
                    $ok++;
                    DB::table('push_subscriptions')->where('id', $s->id)->update(['last_used_at' => now()]);
                } elseif (in_array($r->status(), [404, 410], true)) {
                    DB::table('push_subscriptions')->where('id', $s->id)->delete();   // appareil désabonné
                } else {
                    Log::warning('Web Push '.$r->status().' : '.mb_substr($r->body(), 0, 300));
                }
            } catch (Throwable $e) {
                Log::warning('Web Push non envoyé : '.$e->getMessage());
            }
        }

        return $ok;
    }
}
