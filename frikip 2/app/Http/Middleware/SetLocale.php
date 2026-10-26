<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue du site : français par défaut, anglais pour les recruteurs étrangers.
 * Choix gardé dans le cookie « terrain_lang » (bouton FR / EN en bas des pages, ou ?lang=en dans un lien partagé).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_keys(config('terrain.locales', ['fr' => 'Français']));
        $lang = $request->query('lang') ?: $request->cookie('terrain_lang');
        if (! in_array($lang, $allowed, true)) {
            $lang = 'fr';
        }
        App::setLocale($lang);
        \Illuminate\Support\Carbon::setLocale($lang);   // dates (« lundi 3 novembre », « il y a 5 minutes »)
        $response = $next($request);
        if ($request->query('lang') && in_array($request->query('lang'), $allowed, true)) {
            $response->headers->setCookie(cookie('terrain_lang', $lang, 60 * 24 * 365));
        }

        return $response;
    }
}
