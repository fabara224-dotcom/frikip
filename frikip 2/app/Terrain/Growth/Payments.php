<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paiements Mobile Money via CinetPay (Orange Money, MTN MoMo, carte).
 * Documentation : https://docs.cinetpay.com/api/1.0-fr/checkout/initialisation
 * Sans CINETPAY_API_KEY / CINETPAY_SITE_ID : paiement simulé (code à 4 chiffres).
 */
class Payments
{
    private const API = 'https://api-checkout.cinetpay.com/v2/payment';

    public static function live(): bool
    {
        return (bool) (config('terrain.cinetpay.api_key') && config('terrain.cinetpay.site_id'));
    }

    /** @return array{0: string, 1: int, 2: int, 3: string} [libellé, prix, jours, précision] */
    public static function plan(string $product, int $index, ?string $currency = null): array
    {
        $plans = self::plans($product, $currency);
        if (! isset($plans[$index])) {
            throw new RuntimeException('Offre inconnue');
        }

        return $plans[$index];
    }

    /** Monnaie de paiement du visiteur (celle de son pays), GNF par défaut. */
    public static function currency(): string
    {
        $c = \App\Terrain\Region::currency(\App\Terrain\Region::viewer());

        return $c === 'GNF' || config("terrain.prices.{$c}") ? $c : (string) config('terrain.currency', 'GNF');
    }

    /** Produits payants : premium, boost (sportif), structure (académie / club), montage (service vidéo). */
    public const PRODUCTS = ['premium', 'boost', 'structure', 'montage', 'coaching', 'sans_pub'];

    /** Option « Sans pub » par défaut (modifiable dans config/terrain.php : plans.sans_pub et prices.XOF.sans_pub). */
    public const ADFREE_DEFAULT = ['GNF' => [['1 mois', 15000, 30, 'sans publicité'], ['1 an', 120000, 365, '4 mois offerts']],
        'XOF' => [['1 mois', 1000, 30, 'sans publicité'], ['1 an', 8000, 365, '4 mois offerts']]];

    public static function label(string $product): string
    {
        return ['premium' => 'Premium', 'boost' => 'Boost', 'structure' => 'Structure Pro', 'montage' => 'Montage vidéo', 'coaching' => 'Coaching', 'sans_pub' => 'Sans pub'][$product] ?? ucfirst($product);
    }

    /** @return list<array{0: string, 1: int, 2: int, 3: string}> */
    public static function plans(string $product, ?string $currency = null): array
    {
        $currency ??= self::currency();
        if ($product === 'coaching') {
            return Coaching::offers();
        }
        if ($product === 'sans_pub') {
            return ($currency !== 'GNF' ? config("terrain.prices.{$currency}.sans_pub") : config('terrain.plans.sans_pub'))
                ?: (self::ADFREE_DEFAULT[$currency] ?? self::ADFREE_DEFAULT['GNF']);
        }
        if ($currency !== 'GNF' && ($local = config("terrain.prices.{$currency}.{$product}"))) {
            return $local;
        }

        return config("terrain.plans.{$product}") ?: config("terrain.offers.{$product}", []);
    }

    public function create(string $userKey, string $product, int $planIndex, string $method, string $phone): object
    {
        $currency = self::currency();
        [$label, $amount, $days] = self::plan($product, $planIndex, $currency);
        $ref = 'TR'.now()->format('ymd').Str::upper(Str::random(8));
        DB::table('payments')->insert([
            'reference' => $ref, 'user_key' => $userKey, 'product' => $product, 'months' => $days, 'amount' => (int) (round($amount / 5) * 5),
            'currency' => $currency, 'method' => $method, 'phone' => $phone, 'provider' => self::live() ? 'cinetpay' : 'simulation',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->find($ref);
    }

    public function find(string $ref): ?object
    {
        return DB::table('payments')->where('reference', $ref)->first();
    }

    /** Ouvre la page de paiement CinetPay et renvoie son adresse. */
    public function checkoutUrl(object $p, array $customer): string
    {
        $r = Http::timeout(30)->acceptJson()->post(self::API, [
            'apikey' => config('terrain.cinetpay.api_key'),
            'site_id' => config('terrain.cinetpay.site_id'),
            'transaction_id' => $p->reference,
            'amount' => $p->amount,
            'currency' => $p->currency,
            'description' => config('terrain.app_name').' '.self::label($p->product),
            'notify_url' => route('payments.notify'),
            'return_url' => route('payments.return', $p->reference),
            'channels' => $p->method === 'card' ? 'ALL' : 'MOBILE_MONEY',
            // le numéro saisi sur le site est pré-rempli et bloqué : le client n'a plus qu'à valider avec son code
            'lock_phone_number' => $p->method !== 'card',
            'invoice_data' => ['Offre' => self::label($p->product), 'Durée' => $p->months ? $p->months.' jours' : 'achat unique', 'Référence' => $p->reference],
            'lang' => app()->getLocale() === 'en' ? 'en' : 'fr',
            'metadata' => $p->user_key,
            'customer_id' => $p->user_key,
            'customer_name' => $customer['name'] ?? 'Client',
            'customer_surname' => $customer['surname'] ?? config('terrain.app_name'),
            'customer_phone_number' => Notifier::e164($p->phone ?? ''),
            'customer_email' => ($customer['email'] ?? null) ?: 'client@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'terrain.app'),
            'customer_address' => $customer['city'] ?? 'Conakry',
            'customer_city' => $customer['city'] ?? 'Conakry',
            'customer_country' => 'GN',
            'customer_state' => 'GN',
            'customer_zip_code' => '00000',
        ]);
        $url = $r->json('data.payment_url');
        if (! $r->successful() || ! $url) {
            Log::warning('CinetPay initialisation : '.$r->body());
            throw new RuntimeException('Le service de paiement ne répond pas. Réessaie dans un instant.');
        }

        return $url;
    }

    /** Demande l'état réel du paiement à CinetPay et active l'offre si elle est payée. */
    public function verify(object $p): object
    {
        if ($p->status === 'accepted' || $p->provider !== 'cinetpay') {
            return $p;
        }
        $r = Http::timeout(30)->acceptJson()->post(self::API.'/check', [
            'apikey' => config('terrain.cinetpay.api_key'), 'site_id' => config('terrain.cinetpay.site_id'), 'transaction_id' => $p->reference,
        ]);
        $status = (string) $r->json('data.status', '');
        $amountOk = (int) $r->json('data.amount', 0) >= (int) $p->amount && strtoupper((string) $r->json('data.currency', $p->currency)) === $p->currency;
        if ($status === 'ACCEPTED' && $amountOk) {
            $this->markPaid($p, (string) $r->json('data.payment_method', $p->method));
        } elseif (in_array($status, ['REFUSED', 'CANCELED', 'CANCELLED'], true) || $r->json('code') === '627') {
            DB::table('payments')->where('id', $p->id)->where('status', 'pending')->update(['status' => 'refused', 'provider_status' => $status ?: 'REFUSED', 'updated_at' => now()]);
        } elseif ($status) {
            DB::table('payments')->where('id', $p->id)->update(['provider_status' => $status, 'updated_at' => now()]);
        }

        return $this->find($p->reference);
    }

    /** Paiement confirmé : une seule activation, même si la notification arrive plusieurs fois. */
    public function markPaid(object $p, ?string $method = null): void
    {
        $done = DB::table('payments')->where('id', $p->id)->where('status', '!=', 'accepted')
            ->update(['status' => 'accepted', 'provider_status' => 'ACCEPTED', 'method' => $method ?? $p->method, 'paid_at' => now(), 'updated_at' => now()]);
        if (! $done) {
            return;
        }
        if ($p->product === 'montage') {
            // Commande de montage : elle passe en « payée », l'équipe est prévenue
            $o = DB::table('video_orders')->where('payment_ref', $p->reference)->first();
            DB::table('video_orders')->where('payment_ref', $p->reference)->where('status', 'attente_paiement')->update(['status' => 'payee', 'paid_at' => now(), 'updated_at' => now()]);
            if ($o && (int) $o->package === 2) {
                app(Members::class)->extend($p->user_key, 'boost', 7);   // Pack recrutement : 7 jours de Boost offerts
            }
            app(Notifier::class)->send($p->user_key, 'payment', 'Commande de montage reçue – merci !',
                'Réf. '.($o->reference ?? $p->reference).'. Notre équipe commence ton montage et te prévient dès qu’il est prêt.', route('video.orders'));

            return;
        }
        if ($p->product === 'coaching') {
            $r = app(Coaching::class)->paid($p->reference);
            app(Notifier::class)->send($p->user_key, 'payment', 'Coaching réservé – merci !',
                'Réf. '.($r->reference ?? $p->reference).'. Un coach te répond très vite dans ton espace coaching.', route('coaching.follow'));

            return;
        }
        app(Members::class)->extend($p->user_key, $p->product, (int) $p->months);
        $done = ['premium' => 'Premium activé', 'boost' => 'Boost activé', 'structure' => 'Structure Pro activée', 'sans_pub' => 'Sans pub activé'][$p->product] ?? 'Paiement confirmé';
        app(Notifier::class)->send($p->user_key, 'payment', $done.' – merci !',
            number_format($p->amount, 0, ',', ' ').' '.$p->currency.' · réf. '.$p->reference, route('me'));
    }
}
