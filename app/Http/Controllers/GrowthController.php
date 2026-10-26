<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\CardImage;
use App\Terrain\Growth\DemoSeed;
use App\Terrain\Growth\Insights;
use App\Terrain\Growth\Members;
use App\Terrain\Growth\Notifier;
use App\Terrain\Growth\Referrals;
use App\Terrain\SportFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Fonctions pour attirer et garder les utilisateurs : carte de joueur, CV, lien court, statistiques,
 * notifications, préférences, parrainage, classements, « Ils ont été recrutés », partenaires.
 */
class GrowthController extends Controller
{
    public function __construct(private DataSource $data, private Members $members, private Insights $insights)
    {
    }

    private function athleteOr404(string $id): array
    {
        $a = $this->data->athlete($id);
        abort_unless($a, 404);

        return $a;
    }

    // ------------------------------------------------------------------ partage

    /** Carte de joueur à partager (image téléchargeable + QR code vers le profil). */
    public function card(string $id): View
    {
        $a = $this->athleteOr404($id);

        return view('web.card', ['a' => $a, 'badges' => $this->insights->badges($a), 'rating' => $this->insights->rating($a),
            'url' => route('athletes.short', $a['id'])]);
    }

    /** Image de la carte (aperçu WhatsApp / Facebook). */
    public function cardImage(string $id): Response
    {
        $a = $this->athleteOr404($id);
        $png = CardImage::render($a, $this->insights->rating($a), route('athletes.short', $a['id']));
        abort_unless($png, 404);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** CV sportif imprimable (Enregistrer en PDF depuis le navigateur). */
    public function cv(string $id): View
    {
        $a = $this->athleteOr404($id);
        $this->insights->profileView($a, $this->data->user());

        return view('web.cv', ['a' => $a, 'badges' => $this->insights->badges($a), 'url' => route('athletes.short', $a['id'])]);
    }

    /** Lien court à partager : /j/a12 */
    public function short(string $id): RedirectResponse
    {
        $this->athleteOr404($id);

        return redirect()->route('athletes.show', $id);
    }

    public function videoView(Request $request, string $video): JsonResponse
    {
        // Vue réelle : comptée après 2 secondes de lecture, une fois par personne toutes les 30 minutes
        $athlete = (string) $request->input('athlete');
        if ($athlete !== '') {
            app(\App\Terrain\Growth\Engagement::class)->view($video, $athlete, \App\Terrain\Growth\Engagement::viewerKey($this->data->user()));
        }
        // L'algorithme retient les sports regardés pendant la visite
        $sport = (string) $request->input('sport');
        if (in_array($sport, \App\Terrain\SportFields::sports(), true)) {
            $w = (array) session('frikip.sports', []);
            $w[$sport] = min(50, ($w[$sport] ?? 0) + 1);
            session(['frikip.sports' => $w]);
        }

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------ statistiques, notifications, préférences

    public function stats(): View|RedirectResponse
    {
        $u = $this->data->user();
        $a = $u['athlete_id'] ? $this->data->athlete($u['athlete_id']) : null;
        if (! $a) {
            return redirect()->route('me');
        }

        return view('web.stats', ['a' => $a, 's' => $this->insights->summary($a), 'badges' => $this->insights->badges($a)]);
    }

    public function notifications(Notifier $notifier): View
    {
        $key = $this->members->key();
        $rows = $notifier->forUser($key, 80);
        $notifier->markAllRead($key);

        return view('web.notifications', ['rows' => $rows]);
    }

    public function readNotifications(Notifier $notifier): JsonResponse
    {
        $notifier->markAllRead($this->members->key());

        return response()->json(['ok' => true]);
    }

    public function prefs(Request $request): RedirectResponse
    {
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:200']]);
        $this->members->updatePrefs([
            'notify_email' => $request->boolean('notify_email'), 'notify_sms' => $request->boolean('notify_sms'),
            'notify_whatsapp' => $request->boolean('notify_whatsapp'), 'data_saver' => $request->boolean('data_saver'),
            'phone' => $data['phone'] ?? null,
        ] + (($data['email'] ?? null) ? ['email' => $data['email']] : []));

        return back()->with('status', 'Préférences enregistrées.')->withCookie(cookie('terrain_lite', $request->boolean('data_saver') ? '1' : '0', 60 * 24 * 365));
    }

    // ------------------------------------------------------------------ parrainage

    public function invite(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $code), 0, 12));
        if (DB::table('members')->where('referral_code', $code)->exists()) {
            session(['ref_code' => $code]);
        }

        return redirect()->route('register')->withCookie(cookie('terrain_ref', $code, 60 * 24 * 30));
    }

    public function referral(Referrals $referrals): View
    {
        $m = $this->members->current();
        $n = $m ? $referrals->count($m->user_key) : 0;

        return view('web.referral', ['m' => $m, 'count' => $n, 'per' => Referrals::PER_REWARD, 'days' => Referrals::REWARD_DAYS,
            'url' => $m ? route('referral.link', $m->referral_code) : null]);
    }

    // ------------------------------------------------------------------ classements, témoignages, partenaires

    public function rankings(Request $request): View
    {
        $f = [
            'sport' => in_array($request->query('sport'), SportFields::sports(), true) ? $request->query('sport') : 'Football',
            'country' => (string) $request->query('country', \App\Terrain\Region::viewer()),
            'city' => trim((string) $request->query('city', '')),
            'poste' => trim((string) $request->query('poste', '')),
            'cat' => in_array($request->query('cat'), ['Tous', 'U17', 'U20', 'Seniors'], true) ? $request->query('cat') : 'Tous',
        ];
        $all = $this->data->athletes([]);
        $list = array_slice($this->insights->ranking($all, $f), 0, 50);
        $cities = array_values(array_unique(array_map(fn ($a) => $a['city'], array_filter($all, fn ($a) => $f['country'] === 'Tous' || $a['country'] === $f['country']))));
        sort($cities);
        $postes = [];
        foreach (SportFields::FIELDS[$f['sport']] ?? [] as $field) {
            if (in_array($field['label'], ['Poste', 'Discipline'], true)) {
                $postes = $field['options'];
            }
        }

        return view('web.rankings', ['list' => $list, 'f' => $f, 'cities' => $cities, 'postes' => $postes, 'sports' => SportFields::sports(), 'countries' => SportFields::COUNTRIES]);
    }

    public function stories(DemoSeed $seed): View
    {
        $seed->ensure();

        return view('web.stories', ['rows' => DB::table('success_stories')->where('status', 'publie')->orderByDesc('year')->orderByDesc('id')->get()]);
    }

    public function storeStory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recruited_by' => ['required', 'string', 'max:150'], 'year' => ['required', 'digits:4'], 'story' => ['required', 'string', 'min:30', 'max:1500'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Ton accord est nécessaire pour publier ton histoire.', 'story.min' => 'Raconte en quelques phrases (30 caractères minimum).']);
        app(\App\Terrain\Growth\ContentGuard::class)->enforce(['story' => $data['story'], 'recruited_by' => $data['recruited_by']], 'story', $this->data->user()['id'] ?? null);
        $u = $this->data->user();
        $a = $u['athlete_id'] ? $this->data->athlete($u['athlete_id']) : null;
        DB::table('success_stories')->insert([
            'user_key' => $u['id'], 'name' => $u['name'], 'sport' => $a['sport'] ?? '–', 'from_city' => $a['city'] ?? null,
            'recruited_by' => $data['recruited_by'], 'year' => $data['year'], 'story' => $data['story'], 'consent' => true,
            'status' => 'en_attente', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'Merci ! Ton histoire sera publiée après vérification par l’équipe.');
    }

    public function partners(): View
    {
        return view('web.partners');
    }

    public function storePartner(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:club,academie,ecole,universite,federation,media,entreprise,autre'],
            'organization' => ['required', 'string', 'max:150'], 'contact_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'], 'email' => ['nullable', 'email', 'max:200'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], ['phone.required_without' => 'Laisse un téléphone ou un e-mail pour qu’on te rappelle.']);
        DB::table('partnership_requests')->insert($data + ['status' => 'nouvelle', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Merci ! L’équipe te contacte sous 72 h.');
    }
}
