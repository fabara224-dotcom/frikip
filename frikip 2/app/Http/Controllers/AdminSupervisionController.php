<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Algorithm;
use App\Terrain\Growth\ContentGuard;
use App\Terrain\Growth\Moderation;
use App\Terrain\Growth\Notifier;
use App\Terrain\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

/**
 * Espace admin : supervision du site.
 *  - Vue d'ensemble : membres, vidéos, revenus, files d'attente ;
 *  - Membres : score, avertissements, sanctions (avertir, suspendre, bloquer, débloquer, vérifier) ;
 *  - Modération : contenus signalés ou bloqués automatiquement, avec la règle qui les a bloqués ;
 *  - Algorithme : poids du classement, ce que voit chaque visiteur, seuils, mots interdits ;
 *  - Journal : chaque action d'un admin (ne se modifie pas).
 */
class AdminSupervisionController extends Controller
{
    public function __construct(private DataSource $data, private Algorithm $algo, private ContentGuard $guard, private Moderation $moderation, private Notifier $notifier)
    {
    }

    private static function count(string $table, ?callable $where = null): int
    {
        try {
            $q = DB::table($table);
            if ($where) {
                $where($q);
            }

            return (int) $q->count();
        } catch (Throwable) {
            return 0;
        }
    }

    // ------------------------------------------------------------------ vue d'ensemble

    public function overview(): View
    {
        $since7 = now()->subDays(7);
        $month = now()->startOfMonth();
        $members = self::count('members');
        $revenue = [];
        try {
            $revenue = DB::table('payments')->where('status', 'accepted')->where('provider', '!=', 'simulation')->where('paid_at', '>=', $month)
                ->selectRaw('currency, sum(amount) total, count(*) n')->groupBy('currency')->get()->all();
        } catch (Throwable) {
        }
        $simulated = self::count('payments', fn ($q) => $q->where('status', 'accepted')->where('provider', 'simulation')->where('paid_at', '>=', $month));
        $byCountry = [];
        $signups = [];
        try {
            $byCountry = DB::table('members')->selectRaw("coalesce(country, 'Inconnu') c, count(*) n")->groupBy('c')->orderByDesc('n')->limit(10)->pluck('n', 'c')->all();
            $rows = DB::table('members')->where('created_at', '>=', now()->subDays(29)->startOfDay())->selectRaw('date(created_at) d, count(*) n')->groupBy('d')->pluck('n', 'd')->all();
            for ($i = 29; $i >= 0; $i--) {
                $d = now()->subDays($i)->toDateString();
                $signups[$d] = (int) ($rows[$d] ?? 0);
            }
        } catch (Throwable) {
        }
        $paying = 0;
        try {
            $paying = DB::table('members')->where(fn ($q) => $q->where('premium_until', '>', now())->orWhere('boost_until', '>', now())
                ->orWhere(fn ($q) => Schema::hasColumn('members', 'structure_until') ? $q->where('structure_until', '>', now()) : $q->whereRaw('0 = 1')))->count();
        } catch (Throwable) {
        }

        return view('admin.overview', [
            'kpi' => [
                'members' => $members,
                'new7' => self::count('members', fn ($q) => $q->where('created_at', '>=', $since7)),
                'athletes' => self::count('members', fn ($q) => $q->where('role', 'athlete')),
                'recruiters' => self::count('members', fn ($q) => $q->where('role', 'recruiter')),
                'active7' => self::count('members', fn ($q) => $q->where('last_seen_at', '>=', $since7)),
                'videos' => self::count('local_videos'),
                'videosMonth' => self::count('local_videos', fn ($q) => $q->where('created_at', '>=', $month)),
                'paying' => $paying,
                'profiles' => count($this->data->athletes([])),
            ],
            'revenue' => $revenue, 'simulated' => $simulated, 'byCountry' => $byCountry, 'signups' => $signups,
            'queues' => [
                ['admin.moderation', 'Contenus à modérer', self::count('moderation_events', fn ($q) => $q->where('status', 'a_traiter')) + self::count('reports', fn ($q) => $q->where('status', 'nouveau'))],
                ['admin.verifications', 'Vérifications en attente', self::count('verification_requests', fn ($q) => $q->where('status', 'en_attente'))],
                ['admin.montages', 'Montages à faire', self::count('video_orders', fn ($q) => $q->whereIn('status', ['payee', 'en_cours']))],
                ['admin.deletions', 'Demandes de suppression', self::count('deletion_requests', fn ($q) => $q->where('status', 'nouvelle'))],
                ['admin.partners', 'Demandes de partenariat', self::count('partnership_requests', fn ($q) => $q->where('status', 'nouvelle'))],
            ],
            'sanctions' => self::count('moderation_actions', fn ($q) => $q->where('active', true)->whereIn('type', ['block_account', 'suspend_account'])),
            'autoBlocked' => self::count('moderation_events', fn ($q) => $q->where('created_at', '>=', $since7)),
        ]);
    }

    // ------------------------------------------------------------------ membres

    public function members(Request $request): View
    {
        $f = [
            'q' => trim((string) $request->query('q', '')),
            'country' => (string) $request->query('country', ''),
            'role' => in_array($request->query('role'), ['athlete', 'recruiter'], true) ? $request->query('role') : '',
            'state' => in_array($request->query('state'), ['actif', 'averti', 'suspendu', 'bloque', 'payant'], true) ? $request->query('state') : '',
            'sort' => in_array($request->query('sort'), ['score', 'recent', 'actif'], true) ? $request->query('sort') : 'recent',
        ];
        $sanctions = DB::table('moderation_actions')->where('active', true)->whereIn('type', ['block_account', 'suspend_account'])
            ->where(fn ($q) => $q->where('type', 'block_account')->orWhere('until', '>', now()))->get()->keyBy('target_id');
        $strikes = DB::table('member_strikes')->whereNull('revoked_at')->selectRaw('user_key, count(*) n')->groupBy('user_key')->pluck('n', 'user_key');
        $q = DB::table('members')
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$f['q']}%")->orWhere('email', 'like', "%{$f['q']}%")->orWhere('phone', 'like', "%{$f['q']}%")->orWhere('user_key', $f['q'])))
            ->when($f['country'] !== '', fn ($q) => $q->where('country', $f['country']))
            ->when($f['role'] !== '', fn ($q) => $q->where('role', $f['role']))
            ->when($f['state'] === 'payant', fn ($q) => $q->where(fn ($q) => $q->where('premium_until', '>', now())->orWhere('boost_until', '>', now())->orWhere('structure_until', '>', now())))
            ->when($f['state'] === 'averti', fn ($q) => $q->whereIn('user_key', $strikes->keys()->all() ?: ['-']))
            ->when(in_array($f['state'], ['suspendu', 'bloque'], true), function ($q) use ($sanctions, $f) {
                $ids = $sanctions->filter(fn ($s) => $s->type === ($f['state'] === 'bloque' ? 'block_account' : 'suspend_account'))->keys()->all();
                $q->where(fn ($q) => $q->whereIn('user_key', $ids ?: ['-'])->orWhereIn('athlete_id', $ids ?: ['-']));
            })
            ->when($f['state'] === 'actif', fn ($q) => $q->whereNotIn('user_key', $sanctions->keys()->all() ?: ['-']));
        $q = match ($f['sort']) {
            'score' => $q->orderByDesc('score'),
            'actif' => $q->orderByDesc('last_seen_at'),
            default => $q->orderByDesc('created_at'),
        };
        $page = max(1, (int) $request->query('page', 1));
        $total = (clone $q)->count();
        $rows = $q->offset(($page - 1) * 50)->limit(50)->get();

        return view('admin.members', ['rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / 50),
            'sanctions' => $sanctions, 'strikes' => $strikes, 'countries' => Region::names()]);
    }

    public function member(string $key): View
    {
        $m = DB::table('members')->where('user_key', $key)->first();
        abort_unless($m, 404);
        $athlete = $m->athlete_id ? $this->data->athlete($m->athlete_id) : null;
        $ids = array_values(array_filter([$m->user_key, $m->athlete_id]));
        $videos = [];
        if ($m->athlete_id) {
            try {
                $videos = DB::table('local_videos')->where('athlete_id', $m->athlete_id)->orderByDesc('created_at')->limit(30)->get()->all();
            } catch (Throwable) {
            }
        }
        $hidden = DB::table('moderation_actions')->where('active', true)->whereIn('type', ['hide_video', 'pending_video'])->pluck('type', 'target_id');

        return view('admin.member', [
            'm' => $m, 'athlete' => $athlete, 'explain' => $athlete ? $this->algo->explain($athlete) : null,
            'strikes' => DB::table('member_strikes')->where('user_key', $key)->orderByDesc('id')->get(),
            'history' => DB::table('moderation_actions')->whereIn('target_id', $ids)->orderByDesc('id')->limit(30)->get(),
            'sanction' => $this->moderation->sanctionOf(...$ids),
            'payments' => DB::table('payments')->where('user_key', $key)->orderByDesc('id')->limit(20)->get(),
            'reports' => DB::table('reports')->whereIn('target_id', $ids)->orderByDesc('id')->limit(20)->get(),
            'events' => DB::table('moderation_events')->where('user_key', $key)->orderByDesc('id')->limit(20)->get(),
            'videos' => $videos, 'hidden' => $hidden,
            'verified' => app(\App\Terrain\Growth\Verifications::class)->isVerified($key) || ($m->athlete_id && app(\App\Terrain\Growth\Verifications::class)->isVerified($m->athlete_id)),
        ]);
    }

    public function memberAction(Request $request, string $key): RedirectResponse
    {
        $m = DB::table('members')->where('user_key', $key)->first();
        abort_unless($m, 404);
        $data = $request->validate([
            'action' => ['required', 'in:warn,suspend,ban,unblock,verify,unverify,revoke,country'],
            'rule' => ['nullable', 'in:'.implode(',', array_keys(Algorithm::RULES))],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'note' => ['nullable', 'string', 'max:500'],
            'strike' => ['nullable', 'integer'],
            'country' => ['nullable', 'string', 'max:60'],
        ]);
        $rule = $data['rule'] ?? 'autre';
        $ids = array_values(array_filter([$m->user_key, $m->athlete_id]));
        switch ($data['action']) {
            case 'warn':
                $result = $this->guard->strike($key, $rule, 'admin', null, null, $data['note'] ?? null);
                $msg = ['avertissement' => 'Avertissement donné.', 'suspension' => 'Avertissement donné : le seuil est atteint, compte suspendu.', 'blocage' => 'Avertissement donné : compte bloqué.'][$result];
                break;
            case 'suspend':
                $this->guard->suspend($key, (int) ($data['days'] ?? $this->algo->get('suspension_days')), $rule);
                $msg = 'Compte suspendu.';
                break;
            case 'ban':
                $this->guard->ban($key, $rule);
                $msg = 'Compte bloqué définitivement.';
                break;
            case 'unblock':
                foreach ($ids as $id) {
                    $this->moderation->liftFor(['block_account', 'suspend_account'], $id);
                }
                $this->notifier->send($key, 'moderation', __('Ton compte est de nouveau actif'), __('Merci de respecter les règles de :app.', ['app' => config('terrain.app_name')]), route('feed'));
                $msg = 'Compte débloqué.';
                break;
            case 'verify':
            case 'unverify':
                // badge « identité vérifiée » donné ou retiré par l'équipe
                if ($data['action'] === 'verify') {
                    DB::table('verification_requests')->insert([
                        'user_key' => $key, 'role' => $m->role, 'full_name' => $m->name ?? $key, 'document_type' => 'decision_admin',
                        'status' => 'validee', 'admin_note' => 'Décision directe de l’admin', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    DB::table('verification_requests')->where('user_key', $key)->where('status', 'validee')->update(['status' => 'refusee', 'admin_note' => 'Badge retiré par l’admin', 'updated_at' => now()]);
                }
                $msg = $data['action'] === 'verify' ? 'Compte vérifié.' : 'Badge vérifié retiré.';
                break;
            case 'revoke':
                DB::table('member_strikes')->where('id', (int) $data['strike'])->where('user_key', $key)->update(['revoked_at' => now(), 'updated_at' => now()]);
                $msg = 'Avertissement retiré.';
                break;
            case 'country':
                abort_unless(isset(Region::COUNTRIES[$data['country'] ?? '']), 422);
                DB::table('members')->where('user_key', $key)->update(['country' => $data['country']]);
                $msg = 'Pays modifié.';
                break;
        }

        return back()->with('saved', $msg);
    }

    // ------------------------------------------------------------------ modération

    public function moderation(Request $request): View
    {
        $tab = in_array($request->query('vue'), ['a_traiter', 'refus', 'decisions', 'historique'], true) ? $request->query('vue') : 'a_traiter';
        $events = DB::table('moderation_events')
            ->when($tab === 'a_traiter', fn ($q) => $q->where('status', 'a_traiter'))
            ->when($tab === 'refus', fn ($q) => $q->where('kind', 'texte_refuse'))
            ->when($tab === 'historique', fn ($q) => $q->where('status', '!=', 'a_traiter'))
            ->orderByDesc('id')->limit(200)->get();
        $reports = $tab === 'a_traiter' ? DB::table('reports')->where('status', 'nouveau')->orderByDesc('id')->limit(100)->get() : collect();
        $decisions = $tab === 'decisions' ? DB::table('moderation_actions')->where('active', true)->orderByDesc('id')->limit(200)->get() : collect();
        $names = DB::table('members')->whereIn('user_key', $events->pluck('user_key')->filter()->all())->pluck('name', 'user_key');

        return view('admin.moderation', [
            'tab' => $tab, 'events' => $events, 'reports' => $reports, 'decisions' => $decisions, 'names' => $names,
            'videos' => $this->videoInfo($events->where('target_type', 'video')->pluck('target_id')->merge($reports->where('target_type', 'video')->pluck('target_id'))->filter()->unique()->all()),
            'counts' => [
                'a_traiter' => self::count('moderation_events', fn ($q) => $q->where('status', 'a_traiter')) + self::count('reports', fn ($q) => $q->where('status', 'nouveau')),
                'refus' => self::count('moderation_events', fn ($q) => $q->where('kind', 'texte_refuse')),
                'decisions' => self::count('moderation_actions', fn ($q) => $q->where('active', true)),
            ],
        ]);
    }

    /** Titre, image et auteur des vidéos (vraies vidéos publiées et vidéos d'exemple). */
    private function videoInfo(array $ids): array
    {
        $out = [];
        if (! $ids) {
            return $out;
        }
        try {
            foreach (DB::table('local_videos')->whereIn('id', $ids)->get() as $v) {
                $out[$v->id] = ['title' => $v->title, 'poster' => $v->poster ?? null, 'url' => $v->url, 'athlete' => $v->athlete_id];
            }
        } catch (Throwable) {
        }
        foreach ($this->data->athletes([]) as $a) {
            foreach ($a['videos'] as $v) {
                if (in_array($v['id'], $ids, true) && ! isset($out[$v['id']])) {
                    $out[$v['id']] = ['title' => $v['title'], 'poster' => $v['poster'] ?? null, 'url' => $v['url'], 'athlete' => $a['id']];
                }
            }
        }

        return $out;
    }

    public function decide(Request $request, int $id): RedirectResponse
    {
        $e = DB::table('moderation_events')->find($id);
        abort_unless($e, 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,confirm,ban'], 'rule' => ['nullable', 'in:'.implode(',', array_keys(Algorithm::RULES))]]);
        $rule = ($data['rule'] ?? null) ?: ($e->rule && isset(Algorithm::RULES[$e->rule]) && $e->rule !== 'signalements' ? $e->rule : 'autre');
        if ($data['decision'] === 'approve') {
            // Erreur de l'algorithme : le contenu revient en ligne
            if ($e->target_type === 'video' && $e->target_id) {
                $this->moderation->liftFor(['hide_video', 'pending_video'], $e->target_id);
            }
            DB::table('moderation_events')->where('id', $id)->update(['status' => 'approuve', 'updated_at' => now()]);
            if ($e->kind === 'image' && $e->user_key) {
                // l'avertissement et le blocage automatiques sont annulés
                DB::table('member_strikes')->where('user_key', $e->user_key)->where('target_id', $e->target_id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                foreach (array_filter([$e->user_key, DB::table('members')->where('user_key', $e->user_key)->value('athlete_id')]) as $tid) {
                    $this->moderation->liftFor(['block_account'], $tid);
                }
            }
            if ($e->kind === 'signalements' && $e->target_id) {
                DB::table('reports')->where('target_type', 'video')->where('target_id', $e->target_id)->where('status', 'nouveau')->update(['status' => 'classe', 'action' => 'remis en ligne', 'updated_at' => now()]);
            }
            $msg = 'Contenu remis en ligne.';
        } else {
            if ($e->target_type === 'video' && $e->target_id) {
                $this->moderation->liftFor(['pending_video'], $e->target_id);
                $this->moderation->apply('hide_video', $e->target_id, 'Décision de l’équipe', $rule);
            }
            DB::table('moderation_events')->where('id', $id)->update(['status' => 'confirme', 'rule' => $e->rule ?: $rule, 'updated_at' => now()]);
            if ($e->kind === 'signalements' && $e->target_id) {
                DB::table('reports')->where('target_type', 'video')->where('target_id', $e->target_id)->where('status', 'nouveau')->update(['status' => 'traite', 'action' => 'hide_video', 'updated_at' => now()]);
            }
            $msg = 'Contenu retiré.';
            if ($e->user_key && $e->kind !== 'image') {   // l'analyse d'image a déjà sanctionné
                if ($data['decision'] === 'ban') {
                    $this->guard->ban($e->user_key, $rule);
                    $result = 'blocage';
                } else {
                    $result = $this->guard->strike($e->user_key, $rule, 'admin', $e->target_type, $e->target_id);
                }
                $msg .= ' '.(['avertissement' => 'L’auteur a reçu un avertissement.', 'suspension' => 'L’auteur est suspendu.', 'blocage' => 'L’auteur est bloqué.'][$result] ?? 'L’auteur est bloqué.');
            } elseif ($e->user_key && $data['decision'] === 'ban') {
                $this->guard->ban($e->user_key, $rule);
                $msg .= ' L’auteur est bloqué.';
            }
        }

        return back()->with('saved', $msg);
    }

    // ------------------------------------------------------------------ algorithme

    public function algorithm(Request $request): View
    {
        $test = trim((string) $request->query('tester', ''));
        $preview = [];
        try {
            $athletes = $this->data->athletes([]);
            $scored = array_map(fn ($a) => ['a' => $a, 's' => $this->algo->score($a)], $athletes);
            usort($scored, fn ($x, $y) => $y['s'] <=> $x['s']);
            $preview = array_slice($scored, 0, 8);
        } catch (Throwable) {
        }

        return view('admin.algorithm', [
            'values' => $this->algo->all(), 'settings' => Algorithm::SETTINGS, 'levels' => Algorithm::LEVELS, 'rules' => Algorithm::RULES,
            'test' => $test, 'testResult' => $test !== '' ? $this->guard->check($test) : null, 'preview' => $preview,
            'imageReady' => ContentGuard::imageCheckConfigured(),
        ]);
    }

    public function saveAlgorithm(Request $request): RedirectResponse
    {
        $request->validate(['group' => ['required', 'in:classement,fil,moderation,mots']]);
        if ($request->boolean('reset')) {
            $this->algo->reset($request->input('group'));
            $request->attributes->set('admin_log', ['reset' => $request->input('group')]);

            return back()->with('saved', 'Réglages remis à leur valeur de départ.');
        }
        $group = $request->input('group');
        $input = [];
        foreach (Algorithm::SETTINGS as $k => $s) {
            if ($s[2] === $group) {
                $input[$k] = $request->input($k);
            }
        }
        $changes = $this->algo->save($input, $group);
        $request->attributes->set('admin_log', ['changements' => $changes]);
        // le score des membres est recalculé à leur prochaine visite ; celui des sportifs du site tout de suite
        if ($group === 'classement') {
            $this->refreshScores();
        }

        return back()->with('saved', $changes ? count($changes).' réglage(s) modifié(s) : effet immédiat sur tout le site.' : 'Aucun changement.');
    }

    private function refreshScores(): void
    {
        try {
            foreach (DB::table('members')->whereNotNull('athlete_id')->get(['user_key', 'athlete_id']) as $m) {
                $a = $this->data->athlete($m->athlete_id);
                if ($a) {
                    DB::table('members')->where('user_key', $m->user_key)->update(['score' => $this->algo->score($a)]);
                }
            }
        } catch (Throwable) {
        }
    }

    // ------------------------------------------------------------------ journal

    public function journal(Request $request): View
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = self::count('admin_log');

        return view('admin.journal', ['rows' => DB::table('admin_log')->orderByDesc('id')->offset(($page - 1) * 100)->limit(100)->get(),
            'page' => $page, 'pages' => (int) ceil($total / 100), 'total' => $total]);
    }

    public static function since(?string $date): string
    {
        return $date ? Carbon::parse($date)->diffForHumans() : '–';
    }
}
