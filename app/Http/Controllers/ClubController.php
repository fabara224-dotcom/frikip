<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\DemoSeed;
use App\Terrain\SportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Pages des clubs, académies, agents, universités et fédérations. */
class ClubController extends Controller
{
    public const TYPES = ['club' => 'Club', 'academie' => 'Académie', 'agent' => 'Agent', 'universite' => 'Université', 'federation' => 'Fédération'];

    public function __construct(private DataSource $data)
    {
    }

    public function index(Request $request, DemoSeed $seed): View
    {
        $seed->ensure();
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : null;
        $pro = app(\App\Terrain\Growth\Members::class)->structureKeys();
        // Structures abonnées (Structure Pro) en tête, puis structures vérifiées
        $rows = DB::table('organizations')->when($type, fn ($q) => $q->where('type', $type))->orderByDesc('verified')->orderBy('name')->get()
            ->sortByDesc(fn ($o) => $o->owner_key && in_array($o->owner_key, $pro, true) ? 1 : 0)->values();

        return view('web.clubs', ['rows' => $rows, 'type' => $type, 'types' => self::TYPES, 'pro' => $pro]);
    }

    /** Présentation de l'abonnement Structure Pro (visible sans compte). */
    public function offer(): View
    {
        $u = $this->data->user();
        $until = $u ? DB::table('members')->where('user_key', $u['id'])->value('structure_until') : null;

        return view('web.structure-offer', ['plans' => \App\Terrain\Growth\Payments::plans('structure'), 'until' => $until,
            'mine' => $u ? DB::table('organizations')->where('owner_key', $u['id'])->first() : null]);
    }

    public function show(string $slug): View
    {
        $o = DB::table('organizations')->where('slug', $slug)->first();
        abort_unless($o, 404);
        $name = Str::lower(Str::before($o->name, ' ('));
        $offers = array_values(array_filter($this->data->offers(), fn ($of) => Str::lower(Str::before($of['organization'], ' (')) === $name || ($o->owner_key && $of['mine'] && ($this->data->user()['id'] ?? null) === $o->owner_key)));
        $players = array_values(array_filter($this->data->athletes([]), fn ($a) => str_contains(Str::lower($a['club']), $name)));
        $events = DB::table('events')->where('organization_id', $o->id)->where('date', '>=', today()->toDateString())->orderBy('date')->get();
        $mine = $o->owner_key && $o->owner_key === ($this->data->user()['id'] ?? null);
        if (! $mine && ! session()->has("club_vu.{$o->id}")) {
            session(["club_vu.{$o->id}" => 1]);   // une visite par personne et par session
            DB::table('organizations')->where('id', $o->id)->increment('views');
        }

        return view('web.club', ['o' => $o, 'offers' => $offers, 'players' => $players, 'events' => $events, 'types' => self::TYPES,
            'mine' => $mine, 'pro' => app(\App\Terrain\Growth\Members::class)->hasStructure($o->owner_key)]);
    }

    public function edit(): View
    {
        $o = DB::table('organizations')->where('owner_key', $this->data->user()['id'])->first();

        return view('web.club-edit', ['o' => $o, 'types' => self::TYPES, 'sports' => SportFields::sports()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'sport' => ['required', 'in:'.implode(',', SportFields::sports())], 'city' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:60'], 'description' => ['nullable', 'string', 'max:1500'],
            'palmares' => ['nullable', 'string', 'max:250'], 'licence' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $key = $this->data->user()['id'];
        app(\App\Terrain\Growth\ContentGuard::class)->enforce(['name' => $data['name'], 'description' => $data['description'] ?? null, 'palmares' => $data['palmares'] ?? null], 'club', $key);
        $o = DB::table('organizations')->where('owner_key', $key)->first();
        $values = $data + ['color' => $data['color'] ?? '#1F7A4A', 'updated_at' => now()];
        if ($o) {
            DB::table('organizations')->where('id', $o->id)->update($values);
            $slug = $o->slug;
        } else {
            $slug = Str::slug($data['name']) ?: 'club';
            $base = $slug;
            for ($i = 2; DB::table('organizations')->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            DB::table('organizations')->insert($values + ['slug' => $slug, 'owner_key' => $key, 'verified' => false, 'demo' => false, 'created_at' => now()]);
        }

        return redirect()->route('clubs.show', $slug)->with('status', 'Page enregistrée. Le badge vérifié est ajouté après contrôle de la licence.');
    }
}
