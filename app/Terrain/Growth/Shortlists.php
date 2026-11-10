<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Throwable;

/** Listes de joueurs présélectionnés des recruteurs (« Attaquants U20 », « Essai de juin »…). */
class Shortlists
{
    public function of(string $owner): array
    {
        try {
            return DB::table('shortlists')->where('owner_key', $owner)->orderByDesc('updated_at')->get()
                ->map(function ($l) {
                    $l->count = (int) DB::table('shortlist_items')->where('shortlist_id', $l->id)->count();

                    return $l;
                })->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function find(string $owner, int $id): ?object
    {
        return DB::table('shortlists')->where('owner_key', $owner)->where('id', $id)->first();
    }

    public function create(string $owner, string $name): int
    {
        return (int) DB::table('shortlists')->insertGetId(['owner_key' => $owner, 'name' => mb_substr(trim($name) ?: 'Ma liste', 0, 80), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Ajoute (ou retire si déjà présent). @return bool true = ajouté */
    public function toggle(object $list, string $athleteId): bool
    {
        $q = DB::table('shortlist_items')->where('shortlist_id', $list->id)->where('athlete_id', $athleteId);
        if ($q->exists()) {
            $q->delete();
            $added = false;
        } else {
            DB::table('shortlist_items')->insert(['shortlist_id' => $list->id, 'athlete_id' => $athleteId, 'created_at' => now()]);
            $added = true;
        }
        DB::table('shortlists')->where('id', $list->id)->update(['updated_at' => now()]);

        return $added;
    }

    public function items(object $list): array
    {
        return DB::table('shortlist_items')->where('shortlist_id', $list->id)->orderByDesc('id')->get()->all();
    }

    public function note(object $list, string $athleteId, string $note): void
    {
        DB::table('shortlist_items')->where('shortlist_id', $list->id)->where('athlete_id', $athleteId)->update(['note' => mb_substr($note, 0, 300)]);
    }

    public function delete(object $list): void
    {
        DB::table('shortlist_items')->where('shortlist_id', $list->id)->delete();
        DB::table('shortlists')->where('id', $list->id)->delete();
    }

    /** Listes qui contiennent ce joueur. */
    public function containing(string $owner, string $athleteId): array
    {
        try {
            return DB::table('shortlist_items')->join('shortlists', 'shortlists.id', '=', 'shortlist_items.shortlist_id')
                ->where('shortlists.owner_key', $owner)->where('shortlist_items.athlete_id', $athleteId)->pluck('shortlists.id')->all();
        } catch (Throwable) {
            return [];
        }
    }
}
