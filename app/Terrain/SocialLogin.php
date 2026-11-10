<?php

namespace App\Terrain;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Connexion avec Google, Facebook et TikTok (OAuth 2). Aucune bibliothèque à installer :
 * il suffit de mettre les clés de chaque service dans .env (voir config/terrain.php, « social »).
 */
class SocialLogin
{
    public const NAMES = ['google' => 'Google', 'facebook' => 'Facebook', 'tiktok' => 'TikTok'];

    /** @return list<string> services dont les clés sont configurées */
    public static function providers(): array
    {
        return array_values(array_filter(array_keys(self::NAMES), fn ($p) => self::configured($p)));
    }

    public static function configured(string $p): bool
    {
        $c = config("terrain.social.{$p}");

        return is_array($c) && ($c['id'] ?? '') !== '' && ($c['secret'] ?? '') !== '';
    }

    public static function redirectUri(string $p): string
    {
        return route('social.callback', $p);
    }

    public function authUrl(string $p, string $state): string
    {
        $id = (string) config("terrain.social.{$p}.id");
        $back = self::redirectUri($p);

        return match ($p) {
            'google' => 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
                'client_id' => $id, 'redirect_uri' => $back, 'response_type' => 'code', 'scope' => 'openid email profile',
                'state' => $state, 'prompt' => 'select_account',
            ]),
            'facebook' => 'https://www.facebook.com/v21.0/dialog/oauth?'.http_build_query([
                'client_id' => $id, 'redirect_uri' => $back, 'state' => $state, 'scope' => 'email,public_profile', 'response_type' => 'code',
            ]),
            'tiktok' => 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
                'client_key' => $id, 'redirect_uri' => $back, 'state' => $state, 'scope' => 'user.info.basic', 'response_type' => 'code',
            ]),
            default => throw new RuntimeException('Service inconnu.'),
        };
    }

    /**
     * Échange le code contre l'identité de la personne.
     *
     * @return array{id: string, email: ?string, verified: bool, name: string, avatar: ?string}
     */
    public function user(string $p, string $code): array
    {
        $id = (string) config("terrain.social.{$p}.id");
        $secret = (string) config("terrain.social.{$p}.secret");
        $back = self::redirectUri($p);
        $http = fn () => Http::acceptJson()->connectTimeout(5)->timeout(12);

        if ($p === 'google') {
            $tok = $http()->asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code, 'client_id' => $id, 'client_secret' => $secret, 'redirect_uri' => $back, 'grant_type' => 'authorization_code',
            ])->throw()->json();
            $u = $http()->withToken((string) ($tok['access_token'] ?? ''))->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();

            return ['id' => (string) $u['sub'], 'email' => $u['email'] ?? null, 'verified' => (bool) ($u['email_verified'] ?? false),
                'name' => (string) ($u['name'] ?? ''), 'avatar' => $u['picture'] ?? null];
        }
        if ($p === 'facebook') {
            $tok = $http()->get('https://graph.facebook.com/v21.0/oauth/access_token', [
                'client_id' => $id, 'client_secret' => $secret, 'redirect_uri' => $back, 'code' => $code,
            ])->throw()->json();
            $u = $http()->get('https://graph.facebook.com/v21.0/me', [
                'fields' => 'id,name,email,picture.type(large)', 'access_token' => (string) ($tok['access_token'] ?? ''),
            ])->throw()->json();

            return ['id' => (string) $u['id'], 'email' => $u['email'] ?? null, 'verified' => ! empty($u['email']),
                'name' => (string) ($u['name'] ?? ''), 'avatar' => $u['picture']['data']['url'] ?? null];
        }
        if ($p === 'tiktok') {
            $tok = $http()->asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => $id, 'client_secret' => $secret, 'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => $back,
            ])->throw()->json();
            if (empty($tok['access_token'])) {
                throw new RuntimeException((string) ($tok['error_description'] ?? 'TikTok a refusé la connexion.'));
            }
            $u = $http()->withToken((string) $tok['access_token'])->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,union_id,avatar_url,display_name',
            ])->throw()->json('data.user') ?? [];

            // TikTok ne donne pas d'adresse e-mail
            return ['id' => (string) ($u['union_id'] ?? $u['open_id'] ?? $tok['open_id'] ?? ''), 'email' => null, 'verified' => false,
                'name' => (string) ($u['display_name'] ?? ''), 'avatar' => $u['avatar_url'] ?? null];
        }

        throw new RuntimeException('Service inconnu.');
    }
}
