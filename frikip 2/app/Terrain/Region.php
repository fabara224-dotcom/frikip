<?php

namespace App\Terrain;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pays couverts par Frikip : les 16 pays d'Afrique de l'Ouest (et quelques voisins).
 * Pour chaque pays : code ISO, indicatif téléphonique, monnaie, langue du site, vague d'ouverture
 * et moyen de paiement en ligne disponible.
 *
 * Paiement : CinetPay pour la Guinée (GNF) et les pays de l'UEMOA (XOF). Pour les autres pays,
 * le paiement en ligne est affiché « bientôt disponible » (Paystack ou Flutterwave à brancher).
 */
class Region
{
    /** nom => [iso, indicatif, monnaie, langue, vague, paiement] */
    public const COUNTRIES = [
        'Guinée' => ['GN', '224', 'GNF', 'fr', 1, 'cinetpay'],
        'Sénégal' => ['SN', '221', 'XOF', 'fr', 1, 'cinetpay'],
        "Côte d'Ivoire" => ['CI', '225', 'XOF', 'fr', 1, 'cinetpay'],
        'Mali' => ['ML', '223', 'XOF', 'fr', 1, 'cinetpay'],
        'Burkina Faso' => ['BF', '226', 'XOF', 'fr', 2, 'cinetpay'],
        'Bénin' => ['BJ', '229', 'XOF', 'fr', 2, 'cinetpay'],
        'Togo' => ['TG', '228', 'XOF', 'fr', 2, 'cinetpay'],
        'Ghana' => ['GH', '233', 'GHS', 'en', 2, null],
        'Nigeria' => ['NG', '234', 'NGN', 'en', 2, null],
        'Niger' => ['NE', '227', 'XOF', 'fr', 3, 'cinetpay'],
        'Libéria' => ['LR', '231', 'USD', 'en', 3, null],
        'Sierra Leone' => ['SL', '232', 'SLE', 'en', 3, null],
        'Gambie' => ['GM', '220', 'GMD', 'en', 3, null],
        'Guinée-Bissau' => ['GW', '245', 'XOF', 'fr', 3, null],
        'Cap-Vert' => ['CV', '238', 'CVE', 'fr', 3, null],
        'Mauritanie' => ['MR', '222', 'MRU', 'fr', 3, null],
        // Voisins acceptés à l'inscription (profils d'exemple, joueurs de la diaspora)
        'Cameroun' => ['CM', '237', 'XAF', 'fr', 4, null],
        'Maroc' => ['MA', '212', 'MAD', 'fr', 4, null],
        'RDC' => ['CD', '243', 'CDF', 'fr', 4, null],
    ];

    public const DEFAULT = 'Guinée';

    /** @return list<string> */
    public static function names(bool $westAfricaOnly = false): array
    {
        return array_keys($westAfricaOnly ? array_filter(self::COUNTRIES, fn ($c) => $c[4] <= 3) : self::COUNTRIES);
    }

    public static function info(?string $country): array
    {
        [$iso, $dial, $cur, $lang, $wave, $pay] = self::COUNTRIES[$country] ?? self::COUNTRIES[self::DEFAULT];

        return compact('iso', 'dial', 'cur', 'lang', 'wave', 'pay') + ['name' => isset(self::COUNTRIES[$country]) ? $country : self::DEFAULT];
    }

    public static function dial(?string $country): string
    {
        return self::info($country)['dial'];
    }

    public static function currency(?string $country): string
    {
        return self::info($country)['cur'];
    }

    public static function byIso(?string $iso): ?string
    {
        foreach (self::COUNTRIES as $name => $c) {
            if ($iso && $c[0] === strtoupper($iso)) {
                return $name;
            }
        }

        return null;
    }

    /** Le paiement en ligne est-il ouvert dans ce pays ? (monnaie autorisée sur le compte CinetPay) */
    public static function canPay(?string $country): bool
    {
        $i = self::info($country);

        return $i['pay'] === 'cinetpay' && in_array($i['cur'], self::enabledCurrencies(), true);
    }

    /** Monnaies ouvertes sur le compte marchand CinetPay (CINETPAY_CURRENCIES, GNF et XOF par défaut). */
    public static function enabledCurrencies(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config('terrain.cinetpay.currencies', 'GNF,XOF')))));
    }

    /**
     * Pays du visiteur : celui de son compte, sinon celui qu'il a choisi (cookie), sinon celui de son
     * adresse internet (en-tête CF-IPCountry de Cloudflare), sinon le pays par défaut.
     */
    public static function viewer(): string
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        try {
            $u = app(DataSource::class)->user();
            if ($u) {
                $c = DB::table('members')->where('user_key', $u['id'])->value('country');
                if ($c && isset(self::COUNTRIES[$c])) {
                    return $memo = $c;
                }
                if (! empty($u['athlete_id']) && ($a = app(DataSource::class)->athlete($u['athlete_id'])) && isset(self::COUNTRIES[$a['country']])) {
                    return $memo = $a['country'];
                }
            }
        } catch (Throwable) {
        }
        $cookie = request()->cookie('terrain_pays');
        if ($cookie && isset(self::COUNTRIES[$cookie])) {
            return $memo = $cookie;
        }

        return $memo = self::byIso(request()->header('CF-IPCountry')) ?? (string) config('terrain.country_default', self::DEFAULT);
    }

    /** Montant arrondi comme l'exige CinetPay (multiple de 5) et affichage « 10 000 XOF ». */
    public static function format(int|float $amount, string $currency): string
    {
        return number_format($amount, 0, ',', ' ').' '.($currency === 'XOF' ? 'FCFA' : $currency);
    }
}
