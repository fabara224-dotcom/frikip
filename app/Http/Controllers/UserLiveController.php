<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\ContentGuard;
use App\Terrain\Growth\ScoreChoice;
use App\Terrain\Growth\UserLive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/** « Lancer un live » : un membre filme et commente en direct depuis son téléphone. */
class UserLiveController extends Controller
{
    public function __construct(private DataSource $data, private UserLive $live)
    {
    }

    public function create(): View|RedirectResponse
    {
        $me = $this->data->user();
        $mine = DB::table('live_streams')->where('owner_key', $me['id'])->where('kind', 'membre')->whereIn('status', ['prevu', 'direct'])->orderByDesc('id')->first();
        if ($mine) {
            return redirect()->route('live.studio', $mine->id);
        }
        $matches = [];
        try {
            foreach (app(ScoreChoice::class)->today() as $m) {
                if ($m['status'] !== 'finished') {
                    $matches[] = ['id' => $m['id'], 'label' => trim($m['home'].($m['away'] ? ' – '.$m['away'] : '')), 'live' => $m['status'] === 'live'];
                }
            }
        } catch (Throwable) {
        }

        return view('web.live-go', ['refusal' => $this->live->refusal($me), 'matches' => array_slice($matches, 0, 40)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $this->data->user();
        if ($why = $this->live->refusal($me)) {
            return back()->withErrors(['title' => $why]);
        }
        $d = $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:120'],
            'match' => ['nullable', 'string', 'max:60'],
            'rules' => ['accepted'],
        ], ['title.required' => __('Donne un titre à ton live.'), 'title.min' => __('Le titre est trop court.'), 'rules.accepted' => __('Accepte les règles du live pour continuer.')]);
        app(ContentGuard::class)->enforce(['title' => $d['title']], 'live', $me['id']);
        $label = null;
        if (! empty($d['match'])) {
            foreach (app(ScoreChoice::class)->today() as $m) {
                if ((string) $m['id'] === $d['match']) {
                    $label = trim($m['home'].($m['away'] ? ' – '.$m['away'] : ''));
                }
            }
        }
        try {
            $s = $this->live->start($me, $d['title'], $label ? $d['match'] : null, $label);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['title' => $e->getMessage()]);
        }

        return redirect()->route('live.studio', $s->id);
    }

    public function studio(int $id): View
    {
        $s = DB::table('live_streams')->find($id);
        abort_unless($s && $s->kind === 'membre' && $s->owner_key === ($this->data->user()['id'] ?? null), 404);

        return view('web.live-studio', ['s' => $s]);
    }

    /** Le studio prévient le site : le live a démarré (« direct ») ou il est arrêté (« stop »). */
    public function state(Request $request, int $id): JsonResponse
    {
        $s = DB::table('live_streams')->find($id);
        abort_unless($s && $s->kind === 'membre' && $s->owner_key === ($this->data->user()['id'] ?? null), 404);
        if ($request->input('action') === 'stop') {
            $this->live->stop($s);

            return response()->json(['ok' => true, 'status' => 'termine', 'url' => route('live.show', $id)]);
        }
        if ($s->status !== 'termine') {
            DB::table('live_streams')->where('id', $id)->update(['status' => 'direct', 'started_live' => true, 'updated_at' => now()]);
        }

        return response()->json(['ok' => true, 'status' => 'direct']);
    }
}
