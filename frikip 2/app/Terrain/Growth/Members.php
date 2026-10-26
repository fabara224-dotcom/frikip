<?php

namespace App\Terrain\Growth;

use App\Terrain\DataSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registre des comptes qui utilisent le site (dans la base du site) : préférences de notification,
 * code de parrainage, Premium / Boost payés, dernière visite.
 * Clé = identifiant du compte connecté (DataSource::user()['id']).
 */
class Members
{
    public function __construct(private DataSource $data)
    {
    }

    public function me(): ?array
    {
        return $this->data->user();
    }

    public function key(): ?string
    {
        return $this->me()['id'] ?? null;
    }

    /** Enregistre / met à jour le compte connecté (au plus une fois toutes les 5 minutes). */
    public function touch(): ?object
    {
        $u = $this->me();
        if (! $u) {
            return null;
        }
        $stamp = session('growth.touched');
        if ($stamp && $stamp > time() - 300 && session('growth.touched_key') === $u['id']) {
            return null;
        }
        try {
            $row = DB::table('members')->where('user_key', $u['id'])->first();
            $athlete = $u['athlete_id'] ? $this->data->athlete($u['athlete_id']) : null;
            $values = [
                'role' => $u['role'], 'athlete_id' => $u['athlete_id'], 'name' => $u['name'] ?? null,
                'sport' => $athlete['sport'] ?? ($row->sport ?? null), 'city' => $athlete['city'] ?? ($row->city ?? null),
                // pays : celui du profil sportif, sinon celui déjà connu, sinon celui du visiteur
                'country' => $athlete['country'] ?? ($row->country ?? \App\Terrain\Region::viewer()),
                'score' => $athlete ? app(Algorithm::class)->score($athlete) : ($row->score ?? 0),
                'email' => $u['email'] ?? ($row->email ?? null), 'last_seen_at' => now(), 'updated_at' => now(),
            ];
            if ($row) {
                DB::table('members')->where('user_key', $u['id'])->update($values);
            } else {
                DB::table('members')->insert($values + [
                    'user_key' => $u['id'], 'referral_code' => $this->newCode(), 'phone' => $u['phone'] ?? null,
                    'referred_by' => $this->referrerFromCookie($u['id']), 'created_at' => now(),
                ]);
                app(Referrals::class)->credit($u['id']);
            }
            session(['growth.touched' => time(), 'growth.touched_key' => $u['id']]);

            return DB::table('members')->where('user_key', $u['id'])->first();
        } catch (Throwable) {
            return null; // base pas encore migrée : le site continue de fonctionner
        }
    }

    /** Pays du membre connecté (choisi à l'inscription ou dans son profil). */
    public function setCountry(?string $country): void
    {
        $key = $this->key();
        if (! $key || ! $country || ! isset(\App\Terrain\Region::COUNTRIES[$country])) {
            return;
        }
        try {
            DB::table('members')->where('user_key', $key)->update(['country' => $country, 'updated_at' => now()]);
            if (\Illuminate\Support\Facades\Schema::hasColumn('accounts', 'country')) {
                DB::table('accounts')->where('id', $key)->update(['country' => $country]);
            }
        } catch (Throwable) {
        }
    }

    public function current(): ?object
    {
        $key = $this->key();
        if (! $key) {
            return null;
        }
        $this->touch();

        return DB::table('members')->where('user_key', $key)->first();
    }

    /** Trouve un membre par son id de compte OU par l'id de son profil sportif (messagerie, profils). */
    public function find(string $keyOrAthlete): ?object
    {
        return DB::table('members')->where('user_key', $keyOrAthlete)->orWhere('athlete_id', $keyOrAthlete)->first();
    }

    private function newCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (DB::table('members')->where('referral_code', $code)->exists());

        return $code;
    }

    private function referrerFromCookie(string $self): ?string
    {
        $code = session('ref_code') ?: request()->cookie('terrain_ref');
        if (! $code) {
            return null;
        }
        $ref = DB::table('members')->where('referral_code', strtoupper((string) $code))->value('user_key');

        return $ref && $ref !== $self ? $ref : null;
    }

    // ------------------------------------------------------------------ droits payés

    public function isPremium(?string $key = null): bool
    {
        $u = $this->me();
        $key ??= $u['id'] ?? null;
        if (! $key) {
            return false;
        }
        if ($key === ($u['id'] ?? null) && ! empty($u['premium'])) {
            return true;
        }
        $m = DB::table('members')->where('user_key', $key)->first();
        if ($m && $m->premium_until && Carbon::parse($m->premium_until)->isFuture()) {
            return true;
        }
        // Accès gratuit offert aux clubs / agents vérifiés pendant la période de lancement
        $free = config('terrain.free_club_access_until');
        if ($free && Carbon::parse($free)->endOfDay()->isFuture() && $m && $m->role === 'recruiter') {
            return $key === ($u['id'] ?? null) ? ! empty($u['verified']) || app(Verifications::class)->isVerified($key) : app(Verifications::class)->isVerified($key);
        }

        return false;
    }

    private ?array $boosted = null;

    /** Profils sportifs dont le Boost payé (ou gagné par parrainage) est en cours. */
    public function boostedAthletes(): array
    {
        if ($this->boosted === null) {
            try {
                $this->boosted = DB::table('members')->where('boost_until', '>', now())->whereNotNull('athlete_id')->pluck('athlete_id')->all();
            } catch (Throwable) {
                $this->boosted = [];
            }
        }

        return $this->boosted;
    }

    public function isBoosted(string $athleteId): bool
    {
        return in_array($athleteId, $this->boostedAthletes(), true);
    }

    /** Prolonge Premium ou Boost (paiement, parrainage). */
    public function extend(string $key, string $product, int $days): void
    {
        $m = DB::table('members')->where('user_key', $key)->first();
        if (! $m) {
            return;
        }
        if ($product === 'structure') {
            // Structure Pro : inclut les contacts illimités (Premium) pendant la même durée
            $this->extend($key, 'premium', $days);
            $m = DB::table('members')->where('user_key', $key)->first();
        }
        $col = ['boost' => 'boost_until', 'structure' => 'structure_until', 'sans_pub' => 'adfree_until'][$product] ?? 'premium_until';
        $from = $m->{$col} && Carbon::parse($m->{$col})->isFuture() ? Carbon::parse($m->{$col}) : now();
        DB::table('members')->where('user_key', $key)->update([$col => $from->addDays($days), 'updated_at' => now()]);
        if ($key === $this->key() && $product === 'premium') {
            $u = session('terrain.user');
            $u['premium'] = true;
            session(['terrain.user' => $u]);
        }
    }

    /** Pas de publicité pour ce visiteur : option « Sans pub », Premium ou Structure Pro en cours. */
    public function isAdFree(): bool
    {
        $key = $this->key();
        if (! $key) {
            return false;
        }
        try {
            $m = DB::table('members')->where('user_key', $key)->first();
            if (! $m) {
                return false;
            }
            foreach (['adfree_until', 'premium_until', 'structure_until'] as $col) {
                if (! empty($m->{$col}) && Carbon::parse($m->{$col})->isFuture()) {
                    return true;
                }
            }
        } catch (Throwable) {
        }

        return $this->isPremium();
    }

    /** Abonnement Structure Pro en cours ? */
    public function hasStructure(?string $key): bool
    {
        if (! $key) {
            return false;
        }
        try {
            $until = DB::table('members')->where('user_key', $key)->value('structure_until');

            return $until && Carbon::parse($until)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<string> comptes avec Structure Pro en cours */
    public function structureKeys(): array
    {
        try {
            return DB::table('members')->where('structure_until', '>', now())->pluck('user_key')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function updatePrefs(array $prefs): void
    {
        $key = $this->key();
        if ($key) {
            $this->touch();
            DB::table('members')->where('user_key', $key)->update($prefs + ['updated_at' => now()]);
        }
    }
}
