<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Salons en direct : chaque match (m-<id du match>) et chaque live (l-<id du direct>) a son salon où
 * les supporters commentent et réagissent pendant la rencontre. Mêmes règles que les commentaires
 * (mots interdits, signalement : un message signalé 3 fois est masqué).
 */
class Rooms
{
    public const REACTIONS = ['⚽', '🔥', '👏', '😮', '😂', '🟨', '🟥', '🧤'];

    public static function valid(string $room): bool
    {
        return (bool) preg_match('/^(m|l)-[A-Za-z0-9\-]{1,60}$/', $room);
    }

    /** Messages après $after (0 = les 60 derniers), sans les auteurs bloqués par le visiteur. */
    public function since(string $room, int $after, ?string $viewer = null): array
    {
        $blocked = $viewer ? app(Blocks::class)->blockedBy($viewer) : [];
        $q = DB::table('room_messages')->where('room', $room)->where('hidden', false)->when($blocked, fn ($x) => $x->whereNotIn('user_key', $blocked));
        $rows = $after > 0 ? $q->where('id', '>', $after)->orderBy('id')->limit(100)->get() : $q->orderByDesc('id')->limit(60)->get()->reverse()->values();

        return $rows->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'kind' => $r->kind, 'body' => $r->body, 'mine' => $viewer && $r->user_key === $viewer,
            'at' => \Illuminate\Support\Carbon::parse($r->created_at)->format('H:i')])->all();
    }

    public function post(string $room, array $user, string $body, string $kind = 'text'): int
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body));
        if ($kind === 'react') {
            if (! in_array($body, self::REACTIONS, true)) {
                throw new RuntimeException('Réaction inconnue.');
            }
        } else {
            if ($body === '' || mb_strlen($body) > 280) {
                throw new RuntimeException(__('Écris un message de 1 à 280 caractères.'));
            }
            app(ContentGuard::class)->enforce(['body' => $body], 'salon', $user['id']);
        }
        // Pas plus d'un message toutes les 2 secondes par personne
        $last = DB::table('room_messages')->where('user_key', $user['id'])->orderByDesc('id')->value('created_at');
        if ($last && now()->diffInSeconds(\Illuminate\Support\Carbon::parse($last)) < 2 && $kind === 'text') {
            throw new RuntimeException(__('Doucement : attends deux secondes entre deux messages.'));
        }

        return (int) DB::table('room_messages')->insertGetId([
            'room' => $room, 'user_key' => $user['id'], 'name' => mb_substr((string) ($user['name'] ?? 'Membre'), 0, 120),
            'kind' => $kind, 'body' => mb_substr($body, 0, 300), 'created_at' => now(),
        ]);
    }

    public function report(int $id): void
    {
        DB::table('room_messages')->where('id', $id)->increment('reports');
        DB::table('room_messages')->where('id', $id)->where('reports', '>=', 3)->update(['hidden' => true]);
    }

    /** Nombre de personnes qui ont écrit dans les 10 dernières minutes. */
    public function active(string $room): int
    {
        return (int) DB::table('room_messages')->where('room', $room)->where('created_at', '>', now()->subMinutes(10))->distinct()->count('user_key');
    }
}
