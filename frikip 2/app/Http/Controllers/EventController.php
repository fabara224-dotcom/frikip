<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\DemoSeed;
use App\Terrain\Growth\Members;
use App\Terrain\Growth\Notifier;
use App\Terrain\SportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/** Journées de détection : calendrier, inscription en ligne, billet avec QR code, pointage le jour J. */
class EventController extends Controller
{
    public function __construct(private DataSource $data, private Members $members, private Notifier $notifier)
    {
    }

    public function index(Request $request, DemoSeed $seed): View
    {
        $seed->ensure();
        $sport = in_array($request->query('sport'), SportFields::sports(), true) ? $request->query('sport') : null;
        $rows = DB::table('events')->leftJoin('organizations', 'organizations.id', '=', 'events.organization_id')
            ->where('events.date', '>=', today()->toDateString())->when($sport, fn ($q) => $q->where('events.sport', $sport))
            ->orderBy('events.date')->get(['events.*', 'organizations.name as org_name', 'organizations.slug as org_slug', 'organizations.verified as org_verified']);
        $counts = DB::table('event_registrations')->selectRaw('event_id, count(*) n')->groupBy('event_id')->pluck('n', 'event_id');
        $me = $this->data->user();
        $mine = $me ? DB::table('event_registrations')->where('user_key', $me['id'])->pluck('code', 'event_id') : collect();

        return view('web.events', ['rows' => $rows, 'counts' => $counts, 'mine' => $mine, 'sport' => $sport, 'sports' => SportFields::sports()]);
    }

    public function show(int $id): View
    {
        $e = $this->event($id);
        $me = $this->data->user();
        $reg = $me ? DB::table('event_registrations')->where('event_id', $id)->where('user_key', $me['id'])->first() : null;
        $a = $me && $me['athlete_id'] ? $this->data->athlete($me['athlete_id']) : null;

        return view('web.event', ['e' => $e, 'reg' => $reg, 'count' => DB::table('event_registrations')->where('event_id', $id)->count(),
            'athlete' => $a, 'isOwner' => $me && $e->owner_key === $me['id']]);
    }

    public function register(int $id): RedirectResponse
    {
        $e = $this->event($id);
        $me = $this->data->user();
        $a = $me['athlete_id'] ? $this->data->athlete($me['athlete_id']) : null;
        if (! $a) {
            return back()->with('status', 'Crée d’abord ton profil de sportif.');
        }
        if (($e->age_min && $a['age'] < $e->age_min) || ($e->age_max && $a['age'] > $e->age_max)) {
            return back()->with('status', "Cette détection est réservée aux {$e->age_min}-{$e->age_max} ans.");
        }
        if (DB::table('event_registrations')->where('event_id', $id)->count() >= $e->capacity) {
            return back()->with('status', 'Complet. Surveille les prochaines détections !');
        }
        $this->members->touch();
        $existing = DB::table('event_registrations')->where('event_id', $id)->where('user_key', $me['id'])->first();
        $code = $existing->code ?? Str::upper(Str::random(8));
        if (! $existing) {
            DB::table('event_registrations')->insert(['event_id' => $id, 'user_key' => $me['id'], 'athlete_id' => $a['id'], 'name' => $a['name'], 'code' => $code, 'created_at' => now(), 'updated_at' => now()]);
            $this->notifier->send($me['id'], 'event', 'Inscription confirmée : '.$e->title, \Illuminate\Support\Carbon::parse($e->date)->translatedFormat('l j F').' à '.$e->time.' – '.$e->place, route('tickets.show', $code));
            if ($e->owner_key) {
                $this->notifier->send($e->owner_key, 'event', 'Nouvel inscrit : '.$a['name'], $e->title, route('events.manage', $id));
            }
        }

        return redirect()->route('tickets.show', $code);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'sport' => ['required', 'in:'.implode(',', SportFields::sports())],
            'date' => ['required', 'date', 'after_or_equal:today'], 'time' => ['nullable', 'string', 'max:10'],
            'place' => ['required', 'string', 'max:150'], 'city' => ['required', 'string', 'max:80'],
            'capacity' => ['required', 'integer', 'min:1', 'max:5000'], 'age_min' => ['nullable', 'integer', 'min:8', 'max:40'],
            'age_max' => ['nullable', 'integer', 'min:8', 'max:45'], 'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $key = $this->data->user()['id'];
        app(\App\Terrain\Growth\ContentGuard::class)->enforce(['title' => $data['title'], 'description' => $data['description'] ?? null, 'place' => $data['place']], 'event', $key);
        $org = DB::table('organizations')->where('owner_key', $key)->value('id');
        $id = DB::table('events')->insertGetId($data + ['organization_id' => $org, 'owner_key' => $key, 'demo' => false, 'created_at' => now(), 'updated_at' => now()]);
        // Prévenir les sportifs du même sport inscrits sur le site
        $targets = DB::table('members')->where('role', 'athlete')->where('sport', $data['sport'])->pluck('user_key');
        $this->notifier->sendMany($targets, 'event', 'Nouvelle détection : '.$data['title'], $data['city'].' · '.$data['date'], route('events.show', $id));

        return redirect()->route('events.show', $id)->with('status', 'Détection publiée. Les sportifs de ce sport sont prévenus.');
    }

    public function manage(int $id): View
    {
        $e = $this->event($id);
        abort_unless($e->owner_key === $this->data->user()['id'], 403);

        return view('web.event-manage', ['e' => $e, 'rows' => DB::table('event_registrations')->where('event_id', $id)->orderBy('name')->get()]);
    }

    public function ticket(string $code): View
    {
        $r = DB::table('event_registrations')->where('code', strtoupper($code))->first();
        abort_unless($r, 404);
        $e = $this->event($r->event_id);
        $me = $this->data->user();
        $isOwner = $e->owner_key === $me['id'];
        abort_unless($isOwner || $r->user_key === $me['id'], 403);

        return view('web.ticket', ['r' => $r, 'e' => $e, 'isOwner' => $isOwner]);
    }

    public function checkIn(string $code): RedirectResponse
    {
        $r = DB::table('event_registrations')->where('code', strtoupper($code))->first();
        abort_unless($r, 404);
        $e = $this->event($r->event_id);
        abort_unless($e->owner_key === $this->data->user()['id'], 403);
        DB::table('event_registrations')->where('id', $r->id)->whereNull('checked_in_at')->update(['checked_in_at' => now()]);

        return back()->with('status', $r->checked_in_at ? 'Déjà pointé.' : $r->name.' est présent ✓');
    }

    private function event(int $id): object
    {
        try {
            $e = DB::table('events')->leftJoin('organizations', 'organizations.id', '=', 'events.organization_id')->where('events.id', $id)
                ->first(['events.*', 'organizations.name as org_name', 'organizations.slug as org_slug', 'organizations.verified as org_verified']);
        } catch (Throwable) {
            $e = null;
        }
        abort_unless($e, 404);

        return $e;
    }
}
