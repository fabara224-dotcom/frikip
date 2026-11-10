<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\ContentGuard;
use App\Terrain\Growth\Shortlists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/** Outils des recruteurs : listes de présélection et historique des contacts. */
class RecruitController extends Controller
{
    public function __construct(private DataSource $data, private Shortlists $lists)
    {
    }

    public function index(): View
    {
        $me = $this->data->user();

        return view('web.shortlists', ['lists' => $this->lists->of($me['id'])]);
    }

    public function show(int $id): View
    {
        $me = $this->data->user();
        $list = $this->lists->find($me['id'], $id);
        abort_unless($list, 404);
        $rows = [];
        foreach ($this->lists->items($list) as $it) {
            if ($a = $this->data->athlete($it->athlete_id)) {
                $rows[] = ['a' => $a, 'note' => $it->note, 'at' => $it->created_at];
            }
        }

        return view('web.shortlist', ['list' => $list, 'rows' => $rows]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $me = $this->data->user();
        $d = $request->validate(['name' => ['required', 'string', 'max:80'], 'athlete' => ['nullable', 'string', 'max:64']], ['name.required' => __('Donne un nom à ta liste.')]);
        app(ContentGuard::class)->enforce(['name' => $d['name']], 'liste', $me['id']);
        $id = $this->lists->create($me['id'], $d['name']);
        if (! empty($d['athlete']) && $this->data->athlete($d['athlete'])) {
            $this->lists->toggle($this->lists->find($me['id'], $id), $d['athlete']);
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $id, 'lists' => $this->lists->of($me['id'])]);
        }

        return redirect()->route('shortlists.show', $id)->with('status', __('Liste créée.'));
    }

    /** Ajouter / retirer un joueur d'une liste. */
    public function toggle(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $me = $this->data->user();
        $list = $this->lists->find($me['id'], $id);
        abort_unless($list, 404);
        $athlete = (string) $request->input('athlete');
        abort_unless($this->data->athlete($athlete), 404);
        $added = $this->lists->toggle($list, $athlete);
        $msg = $added ? __('Ajouté à « :l ».', ['l' => $list->name]) : __('Retiré de « :l ».', ['l' => $list->name]);

        return $request->expectsJson() ? response()->json(['ok' => true, 'added' => $added, 'message' => $msg]) : back()->with('status', $msg);
    }

    public function note(Request $request, int $id): RedirectResponse
    {
        $me = $this->data->user();
        $list = $this->lists->find($me['id'], $id);
        abort_unless($list, 404);
        $d = $request->validate(['athlete' => ['required', 'string', 'max:64'], 'note' => ['nullable', 'string', 'max:300']]);
        $this->lists->note($list, $d['athlete'], (string) ($d['note'] ?? ''));

        return back()->with('status', __('Note enregistrée.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $me = $this->data->user();
        $list = $this->lists->find($me['id'], $id);
        abort_unless($list, 404);
        $this->lists->delete($list);

        return redirect()->route('shortlists')->with('status', __('Liste supprimée.'));
    }

    /** Historique des contacts : les personnes à qui j'ai écrit, quand, et si elles ont répondu. */
    public function contacts(): View
    {
        $me = $this->data->user();
        $rows = [];
        try {
            $sent = DB::table('demo_messages')->where('sender', $me['id'])->selectRaw('recipient, min(created_at) as first_at, max(created_at) as last_at, count(*) as n')
                ->groupBy('recipient')->orderByDesc('last_at')->limit(200)->get();
            foreach ($sent as $s) {
                $reply = DB::table('demo_messages')->where('sender', $s->recipient)->where('recipient', $me['id'])->max('created_at');
                $p = $this->data->person($s->recipient);
                $rows[] = ['id' => $s->recipient, 'name' => $p['name'] ?? $s->recipient, 'first' => $s->first_at, 'last' => $s->last_at, 'n' => (int) $s->n, 'reply' => $reply];
            }
        } catch (Throwable) {
        }
        $events = [];
        try {
            $events = DB::table('events')->where('owner_key', $me['id'])->orderByDesc('id')->limit(30)->get()->all();
        } catch (Throwable) {
        }

        return view('web.contacts', ['rows' => $rows, 'events' => $events]);
    }
}
