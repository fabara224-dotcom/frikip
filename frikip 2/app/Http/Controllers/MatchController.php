<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\ScoreChoice;
use App\Terrain\RealScores;
use App\Terrain\SportFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/** Scores en direct, résultats, classement, fiche de match, vote, saisie organisateur. */
class MatchController extends Controller
{
    public function __construct(private DataSource $data, private RealScores $real)
    {
    }

    public function index(Request $request): View
    {
        return view('web.matches', $this->listData($request));
    }

    /** Fragment HTML rafraîchi toutes les 10 à 30 secondes par la page (scores en direct). */
    public function listPartial(Request $request): View
    {
        return view('web.partials.match-list', $this->listData($request));
    }

    /**
     * Onglets : « jour » (matchs du jour de toutes les compétitions), une compétition (numéro API-Football),
     * ou « local » (matchs saisis par les organisateurs sur Terrain, ou démo).
     */
    private function listData(Request $request): array
    {
        $comps = $this->real->competitions();
        $tab = (string) $request->query('onglet', 'jour');
        if (! in_array($tab, ['jour', 'local'], true) && ! isset($comps[(int) $tab])) {
            $tab = 'jour';
        }
        $sport = in_array($request->query('sport'), SportFields::sports(), true) ? $request->query('sport') : 'Tous';

        $data = [
            'tab' => $tab, 'sport' => $sport, 'sports' => SportFields::sports(), 'comps' => $comps,
            'realEnabled' => $this->real->enabled(), 'realError' => null,
            'today' => [], 'league' => null,
            'lastSeason' => RealScores::lastSeason(), 'lastSeasonSource' => RealScores::LAST_SEASON_SOURCE,
            'live' => [], 'done' => [], 'next' => [], 'standings' => [],
            'refresh' => 30,
        ];

        if ($tab === 'jour' && $this->real->enabled()) {
            $groups = [];
            foreach ($this->real->today() as $m) {
                $groups[$m['league_id']]['comp'] ??= $comps[$m['league_id']] ?? ['id' => $m['league_id'], 'name' => $m['competition_name'], 'logo' => $m['league_logo']];
                $groups[$m['league_id']]['matches'][] = $m;
            }
            // Ordre : compétitions avec un match en direct d'abord, puis l'ordre des réglages.
            $order = array_flip(array_keys($comps));
            uasort($groups, fn ($a, $b) => [! collect($a['matches'])->contains('status', 'live'), $order[$a['comp']['id']] ?? 99]
                <=> [! collect($b['matches'])->contains('status', 'live'), $order[$b['comp']['id']] ?? 99]);
            $data['today'] = array_values($groups);
            $data['refresh'] = collect($data['today'])->flatMap(fn ($g) => $g['matches'])->contains('status', 'live') ? 15 : 60;
        } elseif ($tab !== 'jour' && $tab !== 'local') {
            $data['league'] = $this->real->league((int) $tab);
            $data['refresh'] = $data['league'] && count($data['league']['live']) ? 15 : 60;
        }

        if ($tab === 'local' || ! $this->real->enabled()) {
            $all = $this->data->matches();
            $keep = fn ($m) => $sport === 'Tous' || $m['sport'] === $sport;
            $data['live'] = array_values(array_filter($all, fn ($m) => $keep($m) && $m['status'] === 'live'));
            $data['done'] = array_values(array_filter($all, fn ($m) => $keep($m) && $m['status'] === 'finished'));
            $data['next'] = array_values(array_filter($all, fn ($m) => $keep($m) && $m['status'] === 'upcoming'));
            $data['standings'] = in_array($sport, ['Tous', 'Football'], true) ? $this->data->standings() : [];
            $algo = app(\App\Terrain\Growth\Algorithm::class);
            foreach (['live', 'done', 'next'] as $k) {
                $data[$k] = $algo->bySport($data[$k]);
            }
            $data['refresh'] = 10;
        }
        $data['realError'] = $this->real->enabled() ? $this->real->lastError() : null;

        return $data;
    }

    public function show(string $id): View
    {
        return view('web.match', $this->detailData($id));
    }

    public function detailPartial(string $id): View
    {
        return view('web.partials.match-detail', $this->detailData($id));
    }

    private function detailData(string $id): array
    {
        $m = (str_starts_with($id, 'api-') ? $this->real->match($id) : $this->data->match($id)) ?? abort(404);
        $athletes = [];
        foreach ($m['ratings'] as $r) {
            $athletes[$r['athlete_id']] = $this->data->athlete($r['athlete_id']);
        }

        return ['m' => $m, 'athletes' => $athletes];
    }

    public function liveTicker(): JsonResponse
    {
        $m = app(ScoreChoice::class)->pick();
        if (! $m) {
            return response()->json([]);   // aucun score à afficher
        }
        [$lead, $text] = ScoreChoice::text($m);

        return response()->json(['id' => $m['id'], 'lead' => $lead, 'rest' => $text, 'text' => "{$lead} {$text}", 'status' => $m['status'], 'url' => route('matches.show', $m['id'])]);
    }

    /** Le visiteur choisit le score affiché en haut de son fil (gardé 1 an, et dans son compte). */
    public function chooseScore(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:'.implode(',', ScoreChoice::MODES),
            'value' => 'nullable|string|max:80',
            'label' => 'nullable|string|max:120',
        ]);
        $mode = $data['mode'];
        $value = trim((string) ($data['value'] ?? ''));
        if (in_array($mode, ['team', 'league', 'match'], true) && $value === '') {
            $mode = 'auto';   // « Mon équipe » sans nom : on revient au choix automatique
        }
        $json = app(ScoreChoice::class)->store($mode, $value, (string) ($data['label'] ?? ''));
        $cookie = cookie(ScoreChoice::COOKIE, $json, 60 * 24 * 365);
        $msg = [
            'auto' => __('Le fil affiche le premier match en direct.'),
            'off' => __('Plus aucun score en haut du fil.'),
            'team' => __('Le fil affiche les matchs de :t.', ['t' => $value]),
            'league' => __('Le fil affiche les matchs de :t.', ['t' => $data['label'] ?? $value]),
            'match' => __('Ce match s’affiche maintenant en haut de ton fil.'),
        ][$mode];

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $msg])->withCookie($cookie);
        }

        return redirect()->to((string) $request->input('back', url()->previous() ?: route('feed')))->with('status', $msg)->withCookie($cookie);
    }

    /**
     * Données des matchs pour l'app mobile : la clé API-Football reste sur le serveur.
     * ?onglet=jour (défaut) | numéro de compétition | local
     */
    public function api(Request $request): JsonResponse
    {
        $tab = (string) $request->query('onglet', 'jour');
        $payload = [
            'updated_at' => now()->toIso8601String(),
            'real_enabled' => $this->real->enabled(),
            'competitions' => array_values($this->real->competitions()),
        ];
        if ($tab === 'local') {
            $payload['local'] = $this->data->matches();
        } elseif ($tab !== 'jour' && $this->real->enabled()) {
            $payload['league'] = $this->real->league((int) $tab);
        } else {
            $payload['today'] = $this->real->enabled() ? $this->real->today() : [];
            $payload['last_season'] = ['title' => 'Ligue 1 Pro (Guinée) · classement final 2025-26', 'source' => RealScores::LAST_SEASON_SOURCE, 'standings' => RealScores::lastSeason()];
        }

        return response()->json($payload)->header('Access-Control-Allow-Origin', '*');
    }

    public function vote(Request $request, string $id): RedirectResponse
    {
        $i = (int) $request->validate(['candidate' => ['required', 'integer', 'min:0', 'max:20']])['candidate'];
        $this->data->vote($id, $i);

        return back()->with('status', 'Vote enregistré.');
    }

    public function organizer(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:score,start,end'],
            'side' => ['nullable', 'in:h,a'],
            'points' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);
        try {
            match ($data['action']) {
                'score' => $this->data->organizerScore($id, $data['side'] ?? 'h', (int) ($data['points'] ?? 1)),
                'start' => $this->data->organizerStatus($id, 'live'),
                'end' => $this->data->organizerStatus($id, 'finished'),
            };
        } catch (Throwable $e) {
            return back()->withErrors(['organizer' => "Action refusée : seul l'organisateur du match peut saisir le score."]);
        }

        return back()->with('status', 'Score mis à jour et envoyé aux abonnés.');
    }
}
