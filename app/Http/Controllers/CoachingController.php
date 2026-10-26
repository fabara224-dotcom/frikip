<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Coaching;
use App\Terrain\Growth\ContentGuard;
use App\Terrain\Growth\Members;
use App\Terrain\Growth\Notifier;
use App\Terrain\Growth\Payments;
use App\Terrain\SportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Coaching : plan d'entraînement gratuit selon les chiffres de la saison, et suivi payant par un coach. */
class CoachingController extends Controller
{
    public function __construct(private DataSource $data, private Coaching $coaching)
    {
    }

    /** Page d'accueil du coaching : formulaire du plan gratuit + formules de suivi. */
    public function index(Request $request): View
    {
        $me = $this->data->user();
        $athlete = ! empty($me['athlete_id']) ? $this->safeAthlete($me['athlete_id']) : null;
        $last = $me ? $this->coaching->lastStats($me['id']) : null;
        $sport = in_array($request->query('sport'), SportFields::sports(), true) ? $request->query('sport')
            : ($last->sport ?? ($athlete['sport'] ?? 'Football'));
        $positions = Coaching::positions($sport);
        $position = $request->query('poste');
        if (! in_array($position, $positions, true)) {
            $position = ($last && $last->sport === $sport) ? $last->position : ($athlete['fields']['Poste'] ?? $athlete['fields']['Discipline'] ?? null);
        }
        $stats = $last && $last->sport === $sport ? (json_decode((string) $last->stats, true) ?: []) : [];
        if (! $stats && $athlete && $athlete['sport'] === $sport) {
            $stats = array_filter(['buts' => $athlete['fields']['Buts cette saison'] ?? null, 'passes' => $athlete['fields']['Passes décisives'] ?? null,
                'points' => $athlete['fields']['Points / match'] ?? null, 'rebonds' => $athlete['fields']['Rebonds / match'] ?? null,
                'victoires' => $athlete['fields']['Victoires'] ?? null, 'defaites' => $athlete['fields']['Défaites'] ?? null], fn ($v) => $v !== null && $v !== '');
        }

        return view('web.coaching', [
            'sports' => SportFields::sports(), 'sport' => $sport, 'positions' => $positions, 'position' => $position,
            'fields' => Coaching::statFields($sport, $position), 'stats' => $stats, 'ratings' => $stats['_notes'] ?? [], 'goal' => $stats['_objectif'] ?? null,
            'offers' => Coaching::offers(), 'cur' => Payments::currency(), 'me' => $me, 'hasPlan' => (bool) ($last || session('coaching.plan')),
            'mine' => $me ? $this->myRequests($me['id'])->first() : null,
        ]);
    }

    /** Calcule le plan gratuit (sans compte : affiché sans être enregistré). */
    public function plan(Request $request): RedirectResponse
    {
        $sport = $request->input('sport');
        $data = $request->validate([
            'sport' => ['required', Rule::in(SportFields::sports())],
            'position' => ['nullable', 'string', 'max:60'],
            'goal' => ['nullable', Rule::in(Coaching::GOALS)],
            'stats' => ['array'], 'stats.*' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'notes' => ['array'], 'notes.*' => ['nullable', 'integer', 'min:1', 'max:5'],
        ], ['stats.*.numeric' => __('Entre des nombres (ex. 3).')]);
        $position = in_array($data['position'] ?? null, Coaching::positions($sport), true) ? $data['position'] : null;
        $stats = array_intersect_key(array_filter($data['stats'] ?? [], fn ($v) => $v !== null && $v !== ''), Coaching::statFields($sport, $position));
        $ratings = array_intersect_key(array_map('intval', array_filter($data['notes'] ?? [])), Coaching::QUALITIES);
        $plan = $this->coaching->plan($sport, $position, $stats, $ratings, $data['goal'] ?? null);
        session(['coaching.plan' => $plan]);
        if ($me = $this->data->user()) {
            try {
                $this->coaching->saveStats($me['id'], $sport, $position, $stats + ['_notes' => $ratings, '_objectif' => $data['goal'] ?? null], $plan);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('coaching.plan.show');
    }

    public function showPlan(): View|RedirectResponse
    {
        $plan = session('coaching.plan');
        if (! $plan && ($me = $this->data->user()) && ($last = $this->coaching->lastStats($me['id']))) {
            $plan = json_decode((string) $last->plan, true);
        }
        if (! $plan) {
            return redirect()->route('coaching');
        }

        return view('web.coaching-plan', ['plan' => $plan, 'offers' => Coaching::offers(), 'cur' => Payments::currency(), 'me' => $this->data->user()]);
    }

    // ------------------------------------------------------------------ suivi payant

    public function reserve(int $package): View
    {
        abort_unless(isset(Coaching::offers()[$package]), 404);
        $m = app(Members::class)->current();

        return view('web.coaching-reserve', [
            'package' => $package, 'offer' => Coaching::offers()[$package], 'cur' => Payments::currency(), 'goals' => Coaching::GOALS,
            'phone' => $m->phone ?? '', 'closed' => PaymentController::countryClosedMessage(), 'country' => \App\Terrain\Region::viewer(),
            'plan' => session('coaching.plan'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $offers = Coaching::offers();
        $data = $request->validate([
            'package' => ['required', 'integer', 'min:0', 'max:'.(count($offers) - 1)],
            'goal' => ['required', Rule::in(Coaching::GOALS)],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
            'whatsapp' => ['required', 'string', 'max:30', function ($a, $v, $fail) {
                if (strlen(preg_replace('/\D/', '', (string) $v)) < 8) {
                    $fail(__('Entre un numéro valide (8 chiffres minimum).'));
                }
            }],
            'method' => ['required', 'in:om,momo,card'],
        ], ['details.required' => __('Explique au coach ce que tu veux améliorer.'), 'details.min' => __('Explique au coach ce que tu veux améliorer.')]);
        app(ContentGuard::class)->enforce(['details' => $data['details']], 'coaching');
        if ($closed = PaymentController::countryClosedMessage()) {
            return back()->withInput()->withErrors(['whatsapp' => $closed]);
        }
        $pc = app(PaymentController::class);
        if (! Payments::live() && ! $pc->simulationAllowed()) {
            return back()->withInput()->withErrors(['whatsapp' => __('Le paiement en ligne n’est pas encore ouvert sur ce site. Réessaie bientôt.')]);
        }
        $u = $this->data->user();
        app(Members::class)->touch();
        $wa = Notifier::e164($data['whatsapp']);
        $p = app(Payments::class)->create($u['id'], 'coaching', (int) $data['package'], $data['method'], $wa);
        $this->coaching->createRequest($u['id'], $u['athlete_id'] ?? null, (int) $data['package'], $data + ['whatsapp' => $wa, 'name' => $u['name'] ?? null], $p);
        try {
            return redirect()->away($pc->checkoutUrl($p));
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['whatsapp' => $e->getMessage()]);
        }
    }

    /** Reprendre le paiement d'une réservation non payée. */
    public function pay(string $ref): RedirectResponse
    {
        $r = DB::table('coaching_requests')->where('reference', $ref)->where('user_key', $this->data->user()['id'])->where('status', 'attente_paiement')->first();
        abort_unless($r, 404);
        $old = app(Payments::class)->find((string) $r->payment_ref);
        $p = app(Payments::class)->create($r->user_key, 'coaching', (int) $r->package, $old->method ?? 'om', $old->phone ?? (string) $r->whatsapp);
        DB::table('coaching_requests')->where('id', $r->id)->update(['payment_ref' => $p->reference, 'updated_at' => now()]);
        try {
            return redirect()->away(app(PaymentController::class)->checkoutUrl($p));
        } catch (Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    }

    /** Mes suivis (sportif) ou mes sportifs (coach). */
    public function follow(): View
    {
        $me = $this->data->user();
        $coach = $this->coaching->coachFor($me['id']);
        $rows = $this->myRequests($me['id'])->get();
        $coached = $coach ? DB::table('coaching_requests')->where('coach_id', $coach->id)->whereIn('status', ['payee', 'en_cours', 'terminee'])->orderByDesc('updated_at')->get() : collect();

        return view('web.coaching-follow', ['rows' => $rows, 'coach' => $coach, 'coached' => $coached,
            'coaches' => DB::table('coaches')->pluck('name', 'id'), 'names' => $this->names($coached->pluck('user_key')->all())]);
    }

    public function thread(string $ref): View
    {
        [$r, $role] = $this->access($ref);
        $msgs = DB::table('coaching_messages')->where('request_id', $r->id)->orderBy('id')->get();
        // Les messages de l'autre personne sont lus
        DB::table('coaching_messages')->where('request_id', $r->id)->where('author', '!=', $role)->update(['read' => true]);
        $coach = $r->coach_id ? DB::table('coaches')->find($r->coach_id) : null;
        $athleteName = $this->names([$r->user_key])[$r->user_key] ?? null;
        $stats = $role === 'coach' ? $this->coaching->lastStats($r->user_key) : null;

        return view('web.coaching-thread', ['r' => $r, 'role' => $role, 'msgs' => $msgs, 'coach' => $coach, 'athleteName' => $athleteName,
            'plan' => $stats ? json_decode((string) $stats->plan, true) : null]);
    }

    public function send(Request $request, string $ref): RedirectResponse
    {
        [$r, $role] = $this->access($ref);
        abort_if(in_array($r->status, ['attente_paiement', 'annulee'], true), 403);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'kind' => ['nullable', Rule::in($role === 'coach' ? ['message', 'programme', 'bilan'] : ['message', 'demande'])],
        ]);
        app(ContentGuard::class)->enforce(['body' => $data['body']], 'coaching');
        $me = $this->data->user();
        $this->coaching->message($r->id, $role, $data['body'], $data['kind'] ?? 'message', $me['name'] ?? null);
        if ($role === 'coach' && $r->status === 'payee') {
            DB::table('coaching_requests')->where('id', $r->id)->update(['status' => 'en_cours']);
        }
        try {
            $to = $role === 'coach' ? $r->user_key : DB::table('coaches')->where('id', $r->coach_id)->value('user_key');
            if ($to) {
                $kind = ['programme' => 'Nouveau programme', 'bilan' => 'Nouveau bilan', 'demande' => 'Nouvelle demande'][$data['kind'] ?? ''] ?? 'Nouveau message';
                app(Notifier::class)->send($to, 'message', __($kind).' – coaching', \Illuminate\Support\Str::limit($data['body'], 90), route('coaching.thread', $r->reference));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('coaching.thread', $r->reference)->with('status', __('Envoyé.'));
    }

    // ------------------------------------------------------------------ outils

    /** @return array{0: object, 1: string} la demande et le rôle (« sportif » ou « coach ») */
    private function access(string $ref): array
    {
        $r = DB::table('coaching_requests')->where('reference', $ref)->first();
        abort_unless($r, 404);
        $me = $this->data->user();
        if ($r->user_key === $me['id']) {
            return [$r, 'sportif'];
        }
        $coach = $this->coaching->coachFor($me['id']);
        abort_unless($coach && (int) $r->coach_id === (int) $coach->id, 404);

        return [$r, 'coach'];
    }

    private function myRequests(string $key)
    {
        return DB::table('coaching_requests')->where('user_key', $key)->where('status', '!=', 'annulee')->orderByDesc('id');
    }

    private function names(array $keys): array
    {
        $out = array_filter(DB::table('members')->whereIn('user_key', $keys)->pluck('name', 'user_key')->all());
        try {
            $out += DB::table('accounts')->whereIn('id', array_diff($keys, array_keys($out)))->pluck('name', 'id')->all();
        } catch (Throwable) {
        }

        return $out;
    }

    private function safeAthlete(string $id): ?array
    {
        try {
            return $this->data->athlete($id);
        } catch (Throwable) {
            return null;
        }
    }
}
