<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Décisions de modération prises dans l'espace admin : vidéos masquées et comptes bloqués.
 * Appliquées partout sur le site (fil, profils, recherche, classements, messagerie).
 */
class Moderation
{
    private ?array $hidden = null;

    private ?array $blocked = null;

    private function load(): void
    {
        if ($this->hidden !== null) {
            return;
        }
        try {
            $rows = DB::table('moderation_actions')->where('active', true)->get(['type', 'target_id', 'until']);
            // vidéos masquées, ou en attente de validation (comptes nouveaux, voir Admin > Algorithme)
            $this->hidden = $rows->whereIn('type', ['hide_video', 'pending_video'])->pluck('target_id')->all();
            // comptes bloqués, ou suspendus jusqu'à une date
            $this->blocked = $rows->filter(fn ($r) => $r->type === 'block_account' || ($r->type === 'suspend_account' && $r->until && strtotime($r->until) > time()))
                ->pluck('target_id')->all();
        } catch (Throwable) {
            $this->hidden = $this->blocked = [];
        }
    }

    public function isVideoHidden(string $videoId): bool
    {
        $this->load();

        return in_array($videoId, $this->hidden, true);
    }

    /** Compte bloqué (par id de compte ou id de profil sportif). */
    public function isBlocked(?string ...$ids): bool
    {
        $this->load();
        foreach ($ids as $id) {
            if ($id && in_array($id, $this->blocked, true)) {
                return true;
            }
        }

        return false;
    }

    /** Retire les sportifs bloqués et les vidéos masquées d'une liste de profils. */
    public function filterAthletes(array $athletes): array
    {
        $this->load();
        if (! $this->hidden && ! $this->blocked) {
            return $athletes;
        }
        $out = [];
        foreach ($athletes as $a) {
            if ($this->isBlocked($a['id'], $a['contact_id'] ?? null)) {
                continue;
            }
            $a['videos'] = array_values(array_filter($a['videos'] ?? [], fn ($v) => ! $this->isVideoHidden($v['id'])));
            $out[] = $a;
        }

        return $out;
    }

    /** Pareil pour les éléments du fil ({athlete, video}). */
    public function filterFeed(array $items): array
    {
        $this->load();
        if (! $this->hidden && ! $this->blocked) {
            return $items;
        }

        return array_values(array_filter($items, fn ($it) => ! $this->isVideoHidden($it['video']['id'])
            && ! $this->isBlocked($it['athlete']['id'], $it['athlete']['contact_id'] ?? null)));
    }

    public function apply(string $type, string $targetId, ?string $reason = null, ?string $rule = null): void
    {
        if (DB::table('moderation_actions')->where('type', $type)->where('target_id', $targetId)->where('active', true)->exists()) {
            return; // déjà appliquée
        }
        DB::table('moderation_actions')->insert(['type' => $type, 'target_id' => $targetId, 'reason' => $reason, 'rule' => $rule, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->hidden = null;
    }

    /** Lève toutes les décisions actives d'un type sur une cible (ex. remettre une vidéo en ligne). */
    public function liftFor(array $types, string $targetId): int
    {
        $n = DB::table('moderation_actions')->whereIn('type', $types)->where('target_id', $targetId)->where('active', true)->update(['active' => false, 'updated_at' => now()]);
        $this->hidden = null;

        return $n;
    }

    /** Sanction en cours d'un compte : ['type' => block_account|suspend_account, 'until', 'rule'] ou null. */
    public function sanctionOf(?string ...$ids): ?object
    {
        $ids = array_values(array_filter($ids));
        if (! $ids) {
            return null;
        }
        try {
            return DB::table('moderation_actions')->whereIn('target_id', $ids)->where('active', true)->whereIn('type', ['block_account', 'suspend_account'])
                ->where(fn ($q) => $q->where('type', 'block_account')->orWhere('until', '>', now()))->orderByRaw("type = 'block_account' desc")->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function lift(int $id): void
    {
        DB::table('moderation_actions')->where('id', $id)->update(['active' => false, 'updated_at' => now()]);
        $this->hidden = null;
    }
}
