<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\SportFields;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Recherche, comparaison et fiche d'un sportif. */
class AthleteController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    public function search(Request $request): View
    {
        $filters = [
            'q' => (string) $request->query('q', ''),
            'sport' => (string) $request->query('sport', 'Tous'),
            'country' => (string) $request->query('country', 'Tous'),
            'age' => (string) $request->query('age', 'Tous'),
            'verified' => $request->boolean('verified'),
            'sort' => (string) $request->query('sort', 'score'),
        ];

        $all = $this->data->athletes($filters);
        $per = 30;
        $pages = max(1, (int) ceil(count($all) / $per));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return view('web.search', [
            'filters' => $filters, 'results' => array_slice($all, ($page - 1) * $per, $per), 'total' => count($all), 'page' => $page, 'pages' => $pages,
            'sports' => SportFields::sports(), 'countries' => SportFields::COUNTRIES,
            'selected' => array_filter(explode(',', (string) $request->query('cmp', ''))),
        ]);
    }

    public function compare(Request $request): View
    {
        $ids = array_slice(array_filter(explode(',', (string) $request->query('ids', ''))), 0, 2);
        $people = array_values(array_filter(array_map(fn ($id) => $this->data->athlete($id), $ids)));
        abort_if(count($people) < 2, 404);

        return view('web.compare', ['a' => $people[0], 'b' => $people[1]]);
    }

    public function show(string $id): View
    {
        $a = $this->data->athlete($id) ?? abort(404);
        $played = array_values(array_filter($this->data->matches(), fn ($m) => collect($m['ratings'])->contains('athlete_id', $a['id'])));
        $insights = app(\App\Terrain\Growth\Insights::class);
        $insights->profileView($a, $this->data->user());

        return view('web.athlete', [
            'a' => $a, 'played' => $played,
            'following' => in_array($a['id'], $this->data->following(), true),
            'badges' => $insights->badges($a),
            'followers' => \Illuminate\Support\Facades\DB::table('follows_local')->where('athlete_id', $a['id'])->count(),
            'shareUrl' => route('athletes.short', $a['id']),
        ]);
    }

    /** Profils fictifs du mode démo pour l'application mobile (format attendu par Athlete.fromMap). */
    public function demoApi(): \Illuminate\Http\JsonResponse
    {
        abort_unless($this->data->isDemo(), 404);
        $rows = array_map(fn (array $a) => [
            'id' => $a['id'], 'full_name' => $a['name'], 'sport' => $a['sport'], 'age' => $a['age'], 'country' => $a['country'], 'city' => $a['city'],
            'height' => $a['height'], 'weight' => $a['weight'], 'level' => $a['level'], 'club' => $a['club'], 'availability' => $a['availability'],
            'verified' => $a['verified'], 'score' => $a['score'], 'specialty' => $a['specialty'] ?? '', 'demo' => true, 'sport_fields' => (object) $a['fields'],
            'videos' => array_map(fn (array $v) => [
                'id' => $v['id'], 'athlete_id' => $a['id'], 'title' => $v['title'], 'duration' => $v['duration'], 'url' => $v['url'],
                'poster' => $v['poster'] ?? '', 'views' => $v['views'], 'likes' => $v['likes'],
                'credit' => $v['credit'] ?? '', 'credit_url' => $v['credit_url'] ?? '', 'source_name' => $v['source_name'] ?? '',
            ], $a['videos']),
        ], array_filter($this->data->athletes([]), fn ($a) => ! empty($a['demo'])));

        return response()->json(['athletes' => array_values($rows)]);
    }
}
