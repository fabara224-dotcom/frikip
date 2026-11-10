<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bloquer un utilisateur : il ne peut plus m'écrire, et ses vidéos, commentaires et messages de salon sont cachés pour moi.
 * Bloquer un joueur bloque aussi le compte qui tient son profil (et inversement).
 */
class Blocks
{
    private array $cache = [];

    public function block(string $me, string $other): void
    {
        if ($me === $other) {
            return;
        }
        DB::table('user_blocks')->insertOrIgnore(['blocker' => $me, 'blocked' => $other, 'created_at' => now()]);
        $this->cache = [];
    }

    public function unblock(string $me, string $other): void
    {
        DB::table('user_blocks')->where('blocker', $me)->where('blocked', $other)->delete();
        $this->cache = [];
    }

    /** Identifiants (comptes et profils de joueur liés) que $me a bloqués. */
    public function blockedBy(string $me): array
    {
        if (isset($this->cache[$me])) {
            return $this->cache[$me];
        }
        try {
            $ids = DB::table('user_blocks')->where('blocker', $me)->pluck('blocked')->all();
            if ($ids) {
                // compte ↔ profil de joueur
                $more = DB::table('accounts')->whereIn('id', $ids)->whereNotNull('athlete_id')->pluck('athlete_id')->all();
                $more = array_merge($more, DB::table('accounts')->whereIn('athlete_id', $ids)->pluck('id')->all());
                $ids = array_values(array_unique(array_merge($ids, $more)));
            }
        } catch (Throwable) {
            $ids = [];
        }

        return $this->cache[$me] = $ids;
    }

    /** L'un des deux a-t-il bloqué l'autre ? */
    public function between(string $a, string $b): bool
    {
        return in_array($b, $this->blockedBy($a), true) || in_array($a, $this->blockedBy($b), true);
    }

    public function list(string $me): array
    {
        try {
            return DB::table('user_blocks')->where('blocker', $me)->orderByDesc('id')->get()->all();
        } catch (Throwable) {
            return [];
        }
    }
}
