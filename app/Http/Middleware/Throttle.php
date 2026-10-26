<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Limite de requêtes PAR ROUTE et PAR UTILISATEUR (au lieu d'un seul compteur par adresse IP pour tout le site).
 * Important en Guinée : beaucoup de téléphones partagent la même adresse IP chez l'opérateur mobile, et la
 * messagerie interroge le site toutes les quelques secondes.
 * Utilisateur connecté → son identifiant ; sinon → adresse IP (pour bloquer les essais de mot de passe en série).
 */
class Throttle extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $name = $route?->getName() ?? $route?->uri() ?? 'route';
        $user = $request->hasSession() ? $request->session()->get('terrain.user.id') : null;

        return sha1($name.'|'.($user ? 'u:'.$user : 'ip:'.$request->ip()));
    }
}
