<?php

namespace App\Terrain;

use App\Terrain\Growth\Algorithm;

/**
 * Visite depuis l'application mobile FootPro (Flutter). L'app ajoute « FrikipApp/x.y » à l'identifiant du navigateur.
 * Google Play et l'App Store imposent leur propre système de paiement pour les achats numériques
 * (Premium, Sans pub, Boost, Structure Pro) : tant que ce n'est pas branché, ces achats se font sur le site web.
 * Le coaching (service rendu par une personne) peut se payer dans l'app.
 */
class InApp
{
    public static function active(): bool
    {
        return str_contains((string) request()->userAgent(), 'FrikipApp/');
    }

    /** 'android', 'ios' ou null (navigateur). */
    public static function platform(): ?string
    {
        if (! self::active()) {
            return null;
        }
        $ua = (string) request()->userAgent();

        return preg_match('/iPhone|iPad|iPod|iOS/i', $ua) ? 'ios' : 'android';
    }

    /** Achat numérique autorisé ici ? Toujours sur le site web ; dans l'app, selon Admin > Algorithme > Application mobile. */
    public static function canBuyDigital(): bool
    {
        $p = self::platform();
        if ($p === null) {
            return true;
        }

        return (bool) app(Algorithm::class)->get($p === 'ios' ? 'app_pay_ios' : 'app_pay_android');
    }
}
