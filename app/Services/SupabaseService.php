<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Échanges avec Supabase (la même base que l'app Flutter).
 * La suppression réelle est faite par la fonction serveur « delete-account »
 * (supabase/functions/delete-account/index.ts), comme depuis l'app.
 */
class SupabaseService
{
    private string $url;

    private string $anonKey;

    public function __construct()
    {
        $this->url = (string) config('terrain.supabase_url');
        $this->anonKey = (string) config('terrain.supabase_anon_key');
    }

    public function enabled(): bool
    {
        return $this->url !== '' && $this->anonKey !== '';
    }

    /**
     * Vérifie l'e-mail et le mot de passe. Renvoie le jeton de session et l'e-mail.
     *
     * @return array{token: string, email: string}
     */
    public function signIn(string $email, string $password): array
    {
        $response = Http::withHeaders(['apikey' => $this->anonKey])
            ->acceptJson()
            ->timeout(15)
            ->post("{$this->url}/auth/v1/token?grant_type=password", [
                'email' => $email,
                'password' => $password,
            ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Identifiants invalides');
        }

        return [
            'token' => (string) $response->json('access_token'),
            'email' => (string) ($response->json('user.email') ?? $email),
        ];
    }

    /** Supprime le compte lié au jeton (via la fonction serveur). */
    public function deleteAccount(string $accessToken): void
    {
        $response = Http::withHeaders(['apikey' => $this->anonKey])
            ->withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->post("{$this->url}/functions/v1/delete-account", ['source' => 'web']);

        if (! $response->successful()) {
            throw new RuntimeException('Échec de la suppression : HTTP '.$response->status());
        }
    }
}
