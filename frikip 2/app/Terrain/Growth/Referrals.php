<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;

/**
 * Parrainage : chaque membre a un lien /i/CODE. Tous les 3 inscrits grâce à lui, il gagne 30 jours de Boost
 * (sportif) ou de Premium (recruteur).
 */
class Referrals
{
    public const PER_REWARD = 3;

    public const REWARD_DAYS = 30;

    public function count(string $key): int
    {
        return DB::table('members')->where('referred_by', $key)->count();
    }

    /** Appelé quand un nouveau membre s'inscrit : récompense le parrain tous les 3 filleuls. */
    public function credit(string $newKey): void
    {
        $ref = DB::table('members')->where('user_key', $newKey)->value('referred_by');
        if (! $ref) {
            return;
        }
        $n = $this->count($ref);
        $notifier = app(Notifier::class);
        $notifier->send($ref, 'referral', 'Un nouvel inscrit grâce à toi', "{$n} inscrit(s) avec ton lien.", route('referral'));
        if ($n % self::PER_REWARD === 0) {
            $role = DB::table('members')->where('user_key', $ref)->value('role');
            $product = $role === 'recruiter' ? 'premium' : 'boost';
            app(Members::class)->extend($ref, $product, self::REWARD_DAYS);
            $notifier->send($ref, 'referral', 'Récompense débloquée', self::REWARD_DAYS.' jours de '.($product === 'boost' ? 'Boost (ton profil remonte en tête)' : 'Premium').' offerts.', route('referral'));
        }
    }
}
