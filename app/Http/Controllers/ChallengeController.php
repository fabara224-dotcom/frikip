<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Algorithm;
use App\Terrain\Growth\Challenges;
use App\Terrain\Growth\Members;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Défis : la page des défis en cours, la page de chaque défi (participer, voter, classement) et les gagnants. */
class ChallengeController extends Controller
{
    public function __construct(private DataSource $data, private Challenges $challenges)
    {
    }

    /** Tous les défis en cours, les gagnants et mes participations. */
    public function index(): View
    {
        $active = $this->challenges->active();
        abort_unless($active || $this->challenges->current(), 503);
        $me = $this->data->user();
        $a = $me && ! empty($me['athlete_id']) ? $this->data->athlete($me['athlete_id']) : null;
        $cards = [];
        foreach ($active as $c) {
            $entries = $this->challenges->entries($c);
            $cards[] = ['c' => $c, 'count' => count($entries), 'votes' => array_sum(array_map(fn ($e) => $e['entry']->votes, $entries)), 'top' => array_slice($entries, 0, 3)];
        }

        return view('web.challenges', [
            'cards' => $cards, 'winners' => $this->challenges->winners(8), 'athlete' => $a,
            'mine' => $a ? $this->challenges->entriesOf($a['id']) : [], 'boost' => (int) app(Algorithm::class)->get('defi_boost_days'),
        ]);
    }

    public function show(int $id): View
    {
        $this->challenges->current();   // clôture les défis terminés
        $c = $this->challenges->find($id);
        abort_unless($c, 404);
        $me = $this->data->user();
        $a = $me && ! empty($me['athlete_id']) ? $this->data->athlete($me['athlete_id']) : null;
        $entries = $this->challenges->entries($c);
        $winner = $c->winner_entry_id ? collect($entries)->first(fn ($e) => $e['entry']->id === (int) $c->winner_entry_id) : null;

        return view('web.challenge', [
            'c' => $c, 'entries' => $entries, 'winner' => $winner, 'theme' => Challenges::THEMES[$c->theme] ?? [$c->title, null, ''],
            'myVote' => $this->challenges->myVote($c, $me['id'] ?? null), 'athlete' => $a,
            'myEntry' => $a ? collect($entries)->first(fn ($e) => $e['athlete']['id'] === $a['id']) : null,
            'canEnter' => $a && $c->status === 'ouvert' && (! $c->sport || $c->sport === $a['sport']) && (! $c->country || $c->country === ($a['country'] ?? $c->country)),
            'others' => array_values(array_filter($this->challenges->active(), fn ($o) => $o->id !== $c->id)),
            'boost' => (int) app(Algorithm::class)->get('defi_boost_days'),
        ]);
    }

    public function enter(Request $request, Members $members): RedirectResponse
    {
        $data = $request->validate(['video_id' => ['required', 'string', 'max:64'], 'challenge_id' => ['nullable', 'integer']]);
        $c = ! empty($data['challenge_id']) ? $this->challenges->find((int) $data['challenge_id']) : $this->challenges->current();
        $me = $this->data->user();
        $a = $me['athlete_id'] ? $this->data->athlete($me['athlete_id']) : null;
        abort_unless($c && $c->status === 'ouvert' && $a && collect($a['videos'])->contains('id', $data['video_id']), 422);
        if ($c->sport && $a['sport'] !== $c->sport) {
            return back()->with('status', __('Ce défi est réservé au :s. Regarde les autres défis en cours !', ['s' => __($c->sport)]));
        }
        if ($c->country && ($a['country'] ?? $c->country) !== $c->country) {
            return back()->with('status', __('Ce défi est réservé aux sportifs du pays : :c.', ['c' => $c->country]));
        }
        $members->touch();
        $this->challenges->enter($c, $a, $data['video_id'], $me['id']);

        return redirect()->route('challenges.show', $c->id)->with('status', __('Ta vidéo participe au défi ! Partage-la pour récolter des votes.'));
    }

    public function vote(Request $request, int $entry): JsonResponse|RedirectResponse
    {
        $e = DB::table('challenge_entries')->find($entry);
        $c = $e ? $this->challenges->find((int) $e->challenge_id) : null;
        $me = $this->data->user();
        $minHours = (int) app(Algorithm::class)->get('defi_vote_age_hours');
        $young = false;
        if ($minHours > 0) {
            $since = DB::table('members')->where('user_key', $me['id'])->value('created_at');
            $young = ! $since || \Illuminate\Support\Carbon::parse($since)->gt(now()->subHours($minHours));
        }
        if ($young) {
            $ok = false;
            $msg = __('Ton compte est trop récent pour voter. Reviens dans quelques heures.');
        } else {
            $ok = $c && $this->challenges->vote($c, $entry, $me['id']);
            $msg = $ok ? __('Vote enregistré, merci !') : ($e && $e->user_key === $me['id'] ? __('Tu ne peux pas voter pour toi-même.') : __('Tu as déjà voté pour ce défi.'));
        }

        return $request->expectsJson() ? response()->json(['ok' => $ok, 'message' => $msg]) : back()->with('status', $msg);
    }
}
