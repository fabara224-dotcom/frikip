<?php

namespace App\Terrain\Growth;

use App\Terrain\DataSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Défis de la semaine : les sportifs proposent une de leurs vidéos, les membres votent (1 vote par défi),
 * le gagnant devient « Joueur de la semaine » (badge + mise en avant sur l'accueil et le fil).
 * Un nouveau défi s'ouvre chaque lundi (commande « php artisan terrain:defis » planifiée, ou à la première visite).
 */
class Challenges
{
    /** thème => [titre, sport (null = tous), description] */
    public const THEMES = [
        'but' => ['Plus beau but de la semaine', 'Football', 'Frappe, lob, tête, retourné… montre ton plus beau but.'],
        'dribble' => ['Meilleur dribble', 'Football', 'Petit pont, crochet, roulette : élimine ton adversaire.'],
        'arret' => ['Arrêt de la semaine', 'Football', 'Gardiens : votre plus bel arrêt ou votre meilleure sortie.'],
        'panier' => ['Plus beau panier', 'Basket', 'Dunk, tir à 3 points, contre spectaculaire.'],
        'vitesse' => ['Le plus rapide', 'Athlétisme', 'Départ, sprint final : montre ta vitesse.'],
        'combat' => ['Plus belle projection', null, 'Judo, lutte : ta plus belle technique.'],
        'libre' => ['Action de la semaine', null, 'Tous sports : ta meilleure action.'],
    ];

    private const ROTATION = ['but', 'panier', 'dribble', 'vitesse', 'arret', 'combat', 'libre'];

    public function __construct(private DataSource $data)
    {
    }

    /** Défi de la semaine en cours (clôture les défis terminés et ouvre le suivant si besoin). */
    public function current(): ?object
    {
        try {
            foreach (DB::table('challenges')->where('status', 'ouvert')->where('ends_on', '<', today()->toDateString())->get() as $old) {
                $this->close($old);
            }
            $c = DB::table('challenges')->where('status', 'ouvert')->where(fn ($q) => $q->where('kind', 'semaine')->orWhereNull('kind'))->orderByDesc('id')->first();
            if (! $c) {
                $week = (int) now()->isoWeek();
                $c = $this->open(self::ROTATION[$week % count(self::ROTATION)], 7, now()->startOfWeek());
            }
            if ($this->data->isDemo()) {
                $this->seedDemo($c);
            }

            return $c;
        } catch (Throwable) {
            return null;
        }
    }

    public function open(string $theme, int $days = 7, ?Carbon $start = null, array $extra = []): object
    {
        [$title, $sport, $desc] = self::THEMES[$theme] ?? self::THEMES['libre'];
        $start ??= today();
        $prize = app(Algorithm::class)->get('defi_prize');
        $id = DB::table('challenges')->insertGetId($extra + [
            'title' => $title, 'theme' => $theme, 'sport' => $sport, 'description' => $desc, 'kind' => 'semaine', 'prize' => $prize ?: null,
            'starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->addDays($days - 1)->toDateString(), 'status' => 'ouvert',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('challenges')->find($id);
    }

    /** Défi spécial créé par l'admin (lot, sponsor, pays), en plus du défi de la semaine. */
    public function special(array $d): object
    {
        $start = ! empty($d['starts_on']) ? Carbon::parse($d['starts_on']) : today();

        return $this->open('libre', max(1, (int) $d['days']), $start, [
            'title' => $d['title'], 'description' => $d['description'] ?? null, 'sport' => $d['sport'] ?? null, 'country' => $d['country'] ?? null,
            'prize' => $d['prize'] ?? null, 'sponsor' => $d['sponsor'] ?? null, 'sponsor_url' => $d['sponsor_url'] ?? null, 'kind' => 'special',
        ]);
    }

    /** Tous les défis ouverts : celui de la semaine d'abord, puis ceux du pays du visiteur. */
    public function active(): array
    {
        $this->current();
        try {
            $country = \App\Terrain\Region::viewer();

            return DB::table('challenges')->where('status', 'ouvert')->where('starts_on', '<=', today()->toDateString())
                ->where(fn ($q) => $q->whereNull('country')->orWhere('country', '')->orWhere('country', $country))
                ->orderByRaw("case when kind = 'semaine' or kind is null then 0 else 1 end")->orderBy('ends_on')->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function find(int $id): ?object
    {
        try {
            return DB::table('challenges')->find($id);
        } catch (Throwable) {
            return null;
        }
    }

    /** Défis ouverts auxquels ce sportif peut participer (bon sport, bon pays). */
    public function openFor(array $athlete): array
    {
        return array_values(array_filter($this->active(), fn ($c) => (! $c->sport || $c->sport === $athlete['sport'])
            && (! $c->country || $c->country === ($athlete['country'] ?? $c->country))));
    }

    /** Participations d'un sportif (défis en cours et passés). */
    public function entriesOf(string $athleteId): array
    {
        try {
            return DB::table('challenge_entries')->join('challenges', 'challenges.id', '=', 'challenge_entries.challenge_id')
                ->where('challenge_entries.athlete_id', $athleteId)->orderByDesc('challenges.ends_on')->limit(20)
                ->get(['challenges.id', 'challenges.title', 'challenges.status', 'challenges.ends_on', 'challenges.winner_entry_id', 'challenge_entries.id as entry_id', 'challenge_entries.votes', 'challenge_entries.excluded'])->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** Rang d'une participation dans son défi (1 = en tête). */
    public function rank(object $c, int $entryId): ?int
    {
        $ids = DB::table('challenge_entries')->where('challenge_id', $c->id)->where('excluded', false)->orderByDesc('votes')->orderBy('created_at')->pluck('id')->all();
        $i = array_search($entryId, $ids, true);

        return $i === false ? null : $i + 1;
    }

    public function exclude(int $entryId, bool $on = true): void
    {
        DB::table('challenge_entries')->where('id', $entryId)->update(['excluded' => $on, 'updated_at' => now()]);
    }

    public function close(object $c): void
    {
        $winner = DB::table('challenge_entries')->where('challenge_id', $c->id)->where('excluded', false)->orderByDesc('votes')->orderBy('created_at')->first();
        DB::table('challenges')->where('id', $c->id)->update(['status' => 'termine', 'winner_entry_id' => $winner->id ?? null, 'updated_at' => now()]);
        if (! $winner) {
            return;
        }
        $key = $winner->user_key ?: $winner->athlete_id;
        // Récompense automatique : quelques jours de Boost (réglable dans Admin > Défis)
        $days = (int) app(Algorithm::class)->get('defi_boost_days');
        if ($days > 0 && ($m = app(Members::class)->find($key))) {
            app(Members::class)->extend($m->user_key, 'boost', $days);
        }
        $prize = trim(($c->prize ?? '') ?: ($days > 0 ? $days.' jours de Boost' : ''));
        app(Notifier::class)->send($key, 'challenge', 'Tu as gagné le défi ! 🏆',
            "« {$c->title} » : {$winner->votes} votes.".($prize ? " Ton lot : {$prize}." : '').(($c->sponsor ?? null) ? " Offert par {$c->sponsor} : l’équipe te contacte." : ''), route('challenges.show', $c->id));
        // Les participants sont prévenus du résultat
        $others = DB::table('challenge_entries')->where('challenge_id', $c->id)->where('id', '!=', $winner->id)->whereNotNull('user_key')->pluck('user_key');
        app(Notifier::class)->sendMany($others, 'challenge', 'Résultat du défi « '.$c->title.' »', 'Merci d’avoir participé ! Un nouveau défi t’attend.', route('challenges'));
    }

    public function entries(object $c): array
    {
        $rows = DB::table('challenge_entries')->where('challenge_id', $c->id)->where('excluded', false)->orderByDesc('votes')->orderBy('created_at')->get();
        $out = [];
        foreach ($rows as $r) {
            $a = $this->data->athlete($r->athlete_id);
            $v = $a ? collect($a['videos'])->firstWhere('id', $r->video_id) : null;
            if ($a && $v) {
                $out[] = ['entry' => $r, 'athlete' => $a, 'video' => $v];
            }
        }

        return $out;
    }

    public function myVote(object $c, ?string $key): ?int
    {
        return $key ? DB::table('challenge_votes')->where('challenge_id', $c->id)->where('voter_key', $key)->value('entry_id') : null;
    }

    public function vote(object $c, int $entryId, string $key): bool
    {
        $e = DB::table('challenge_entries')->where('id', $entryId)->where('challenge_id', $c->id)->where('excluded', false)->first();
        if ($c->status !== 'ouvert' || ! $e || $e->user_key === $key) {
            return false;   // défi fermé, participation retirée, ou vote pour soi-même
        }
        try {
            DB::table('challenge_votes')->insert(['challenge_id' => $c->id, 'entry_id' => $entryId, 'voter_key' => $key, 'created_at' => now()]);
        } catch (Throwable) {
            return false; // déjà voté pour ce défi
        }
        DB::table('challenge_entries')->where('id', $entryId)->increment('votes');

        return true;
    }

    public function enter(object $c, array $athlete, string $videoId, string $key): void
    {
        DB::table('challenge_entries')->updateOrInsert(
            ['challenge_id' => $c->id, 'athlete_id' => $athlete['id']],
            ['video_id' => $videoId, 'user_key' => $key, 'updated_at' => now(), 'created_at' => now()],
        );
        try {
            if (Cache::add("defi.notif.{$c->id}.{$athlete['id']}", 1, now()->addDays(30))) {
                $fans = DB::table('follows_local')->where('athlete_id', $athlete['id'])->limit(500)->pluck('follower_key');
                app(Notifier::class)->sendMany($fans, 'challenge', $athlete['name'].' participe au défi « '.$c->title.' »', 'Va voter pour lui !', route('challenges.show', $c->id));
            }
        } catch (Throwable) {
        }
    }

    /** Gagnants récents (accueil, fil, badges). */
    public function winners(int $limit = 5): array
    {
        try {
            $rows = DB::table('challenges')->join('challenge_entries', 'challenge_entries.id', '=', 'challenges.winner_entry_id')
                ->where('challenges.status', 'termine')->orderByDesc('challenges.ends_on')->limit($limit)
                ->get(['challenges.id', 'challenges.title', 'challenges.prize', 'challenges.ends_on', 'challenge_entries.athlete_id', 'challenge_entries.video_id', 'challenge_entries.votes']);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            if ($a = $this->data->athlete($r->athlete_id)) {
                $out[] = ['id' => $r->id, 'title' => $r->title, 'prize' => $r->prize, 'ends_on' => $r->ends_on, 'votes' => $r->votes, 'athlete' => $a, 'video_id' => $r->video_id];
            }
        }

        return $out;
    }

    public function winsOf(string $athleteId): int
    {
        try {
            return DB::table('challenges')->join('challenge_entries', 'challenge_entries.id', '=', 'challenges.winner_entry_id')
                ->where('challenge_entries.athlete_id', $athleteId)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Mode démo : quelques participations et un défi passé pour que la page ne soit pas vide. */
    private function seedDemo(object $c): void
    {
        if (DB::table('challenge_entries')->where('challenge_id', $c->id)->exists()) {
            return;
        }
        $athletes = array_values(array_filter($this->data->athletes([]), fn ($a) => count($a['videos']) && (! $c->sport || $a['sport'] === $c->sport)));
        if (! $athletes) {
            $athletes = array_values(array_filter($this->data->athletes([]), fn ($a) => count($a['videos'])));
        }
        mt_srand($c->id * 97);
        foreach (array_slice($athletes, 0, 9) as $i => $a) {
            DB::table('challenge_entries')->insertOrIgnore([
                'challenge_id' => $c->id, 'athlete_id' => $a['id'], 'video_id' => $a['videos'][0]['id'], 'user_key' => null,
                'votes' => mt_rand(3, 60) + (9 - $i) * 4, 'created_at' => now()->subHours(9 - $i), 'updated_at' => now(),
            ]);
        }
        if (! DB::table('challenges')->where('status', 'termine')->exists() && $athletes) {
            $past = $this->open('libre', 7, now()->startOfWeek()->subWeek());
            $a = $athletes[min(2, count($athletes) - 1)];
            $entry = DB::table('challenge_entries')->insertGetId(['challenge_id' => $past->id, 'athlete_id' => $a['id'], 'video_id' => $a['videos'][0]['id'], 'votes' => 214, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('challenges')->where('id', $past->id)->update(['status' => 'termine', 'winner_entry_id' => $entry]);
        }
    }
}
