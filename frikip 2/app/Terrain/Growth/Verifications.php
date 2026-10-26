<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Vérification des comptes : demandes envoyées depuis le site (pièce d'identité, licence, accord du tuteur),
 * validées dans l'espace admin. Un compte validé reçoit le badge ✓ partout sur le site.
 */
class Verifications
{
    private ?array $keys = null;

    /** @return list<string> comptes et profils sportifs validés */
    public function verifiedIds(): array
    {
        if ($this->keys === null) {
            try {
                $rows = DB::table('verification_requests')->where('status', 'validee')->pluck('user_key')->all();
                $athletes = $rows ? DB::table('members')->whereIn('user_key', $rows)->whereNotNull('athlete_id')->pluck('athlete_id')->all() : [];
                $this->keys = array_values(array_unique(array_merge($rows, $athletes)));
            } catch (Throwable) {
                $this->keys = [];
            }
        }

        return $this->keys;
    }

    public function isVerified(?string $id): bool
    {
        return $id !== null && in_array($id, $this->verifiedIds(), true);
    }

    /** Ajoute le badge aux profils validés sur le site. */
    public function applyToAthletes(array $athletes): array
    {
        $ids = $this->verifiedIds();
        if (! $ids) {
            return $athletes;
        }
        foreach ($athletes as &$a) {
            if (in_array($a['id'], $ids, true) || in_array($a['contact_id'] ?? '', $ids, true)) {
                $a['verified'] = true;
            }
        }

        return $athletes;
    }

    public function latestFor(string $key): ?object
    {
        try {
            return DB::table('verification_requests')->where('user_key', $key)->orderByDesc('id')->first();
        } catch (Throwable) {
            return null;
        }
    }
}
