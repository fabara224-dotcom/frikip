<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Challenges;
use App\Terrain\Growth\Moderation;
use App\Terrain\Growth\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Espace admin (suite) : signalements et modération, vérifications, témoignages, partenaires, paiements, défis, clubs.
 * Protégé par le mot de passe TERRAIN_ADMIN_PASSWORD (middleware AdminOnly).
 */
class AdminGrowthController extends Controller
{
    public function __construct(private DataSource $data, private Moderation $moderation, private Notifier $notifier)
    {
    }

    // ------------------------------------------------------------------ signalements

    public function reports(Request $request): View
    {
        $status = $request->query('statut', 'nouveau');
        $reports = DB::table('reports')->when($status !== 'tous', fn ($q) => $q->where('status', $status))->orderByDesc('id')->limit(200)->get();
        $actions = DB::table('moderation_actions')->where('active', true)->orderByDesc('id')->get();

        return view('admin.reports', ['reports' => $reports, 'actions' => $actions, 'status' => $status,
            'counts' => DB::table('reports')->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')]);
    }

    public function handleReport(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:hide_video,block_account,dismiss,done'], 'target' => ['nullable', 'string', 'max:64'], 'note' => ['nullable', 'string', 'max:1000']]);
        $r = DB::table('reports')->find($id);
        abort_unless($r, 404);
        $target = $data['target'] ?: $r->target_id;
        if (in_array($data['action'], ['hide_video', 'block_account'], true) && $target) {
            $this->moderation->apply($data['action'], $target, 'Signalement n°'.$id.' : '.mb_substr($r->reason, 0, 150));
        }
        DB::table('reports')->where('id', $id)->update([
            'status' => $data['action'] === 'dismiss' ? 'classe' : 'traite', 'action' => $data['action'], 'admin_note' => $data['note'] ?? null, 'updated_at' => now(),
        ]);
        if ($r->reporter_key) {
            $this->notifier->send($r->reporter_key, 'report', 'Ton signalement a été traité', 'Merci de nous aider à protéger la communauté.');
        }

        return back()->with('saved', "Signalement n°{$id} traité.");
    }

    public function moderate(Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:hide_video,block_account'], 'target' => ['required', 'string', 'max:64'], 'reason' => ['nullable', 'string', 'max:300']]);
        $this->moderation->apply($data['type'], $data['target'], $data['reason'] ?? 'Décision de l’équipe');

        return back()->with('saved', $data['type'] === 'hide_video' ? 'Vidéo masquée.' : 'Compte bloqué.');
    }

    public function lift(int $id): RedirectResponse
    {
        $this->moderation->lift($id);

        return back()->with('saved', 'Décision levée.');
    }

    // ------------------------------------------------------------------ vérifications

    public function verifications(Request $request): View
    {
        $status = $request->query('statut', 'en_attente');
        $rows = DB::table('verification_requests')->when($status !== 'toutes', fn ($q) => $q->where('status', $status))->orderByDesc('id')->limit(200)->get();

        return view('admin.verifications', ['rows' => $rows, 'status' => $status]);
    }

    public function decideVerification(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:validee,refusee,en_attente'], 'note' => ['nullable', 'string', 'max:1000']]);
        $v = DB::table('verification_requests')->find($id);
        abort_unless($v, 404);
        DB::table('verification_requests')->where('id', $id)->update(['status' => $data['status'], 'admin_note' => $data['note'] ?? null, 'updated_at' => now()]);
        if ($data['status'] !== 'en_attente') {
            $ok = $data['status'] === 'validee';
            $this->notifier->send($v->user_key, 'verification', $ok ? 'Ton compte est vérifié ✓' : 'Vérification refusée',
                $ok ? 'Le badge vérifié apparaît maintenant sur ton profil.' : ($data['note'] ?? 'Envoie un document lisible et à jour.'), route('verification'));
        }

        return back()->with('saved', "Demande n°{$id} : ".($data['status'] === 'validee' ? 'validée' : ($data['status'] === 'refusee' ? 'refusée' : 'remise en attente')).'.');
    }

    /** Pièces justificatives (stockage privé, jamais publiques). */
    public function file(int $id, string $which): BinaryFileResponse
    {
        $v = DB::table('verification_requests')->find($id);
        $path = $v ? ($which === 'tuteur' ? $v->guardian_document_path : $v->document_path) : null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'no-store']);
    }

    // ------------------------------------------------------------------ témoignages, partenaires, paiements, clubs

    public function stories(): View
    {
        return view('admin.stories', ['rows' => DB::table('success_stories')->orderByRaw("case status when 'en_attente' then 0 else 1 end")->orderByDesc('id')->get()]);
    }

    public function decideStory(Request $request, int $id): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'in:publie,refuse,en_attente']])['status'];
        DB::table('success_stories')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);

        return back()->with('saved', 'Témoignage mis à jour.');
    }

    public function partners(): View
    {
        return view('admin.partners', ['rows' => DB::table('partnership_requests')->orderByDesc('id')->limit(300)->get()]);
    }

    public function decidePartner(Request $request, int $id): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'in:nouvelle,en_discussion,signee,refusee']])['status'];
        DB::table('partnership_requests')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);

        return back()->with('saved', 'Demande de partenariat mise à jour.');
    }

    public function payments(): View
    {
        $rows = DB::table('payments')->orderByDesc('id')->limit(300)->get();
        $total = DB::table('payments')->where('status', 'accepted')->sum('amount');

        return view('admin.payments', ['rows' => $rows, 'total' => $total]);
    }

    public function clubs(): View
    {
        return view('admin.clubs', ['rows' => DB::table('organizations')->orderBy('verified')->orderBy('name')->get()]);
    }

    public function verifyClub(int $id): RedirectResponse
    {
        $o = DB::table('organizations')->find($id);
        abort_unless($o, 404);
        DB::table('organizations')->where('id', $id)->update(['verified' => ! $o->verified, 'updated_at' => now()]);
        if ($o->owner_key && ! $o->verified) {
            $this->notifier->send($o->owner_key, 'verification', 'Ta page '.$o->name.' est vérifiée ✓', null, route('clubs.show', $o->slug));
        }

        return back()->with('saved', $o->verified ? 'Badge retiré.' : 'Page vérifiée.');
    }

    // ------------------------------------------------------------------ défis

    public function challenges(Challenges $challenges, \App\Terrain\Growth\Algorithm $algo): View
    {
        $challenges->current();
        $rows = DB::table('challenges')->orderByRaw("case status when 'ouvert' then 0 else 1 end")->orderByDesc('ends_on')->limit(40)->get();
        $entries = [];
        foreach ($rows->where('status', 'ouvert') as $c) {
            $entries[$c->id] = DB::table('challenge_entries')->where('challenge_id', $c->id)->orderByDesc('votes')->limit(30)->get();
        }
        $names = DB::table('members')->whereIn('athlete_id', collect($entries)->flatten()->pluck('athlete_id'))->pluck('name', 'athlete_id');

        return view('admin.challenges', [
            'rows' => $rows, 'themes' => Challenges::THEMES, 'entries' => $entries, 'names' => $names,
            'settings' => \App\Terrain\Growth\Algorithm::SETTINGS, 'values' => $algo->all(), 'sports' => \App\Terrain\SportFields::sports(),
            'countries' => \App\Terrain\SportFields::COUNTRIES,
            'stats' => ['votes' => (int) DB::table('challenge_votes')->where('created_at', '>', now()->subDays(30))->count(),
                'entries' => (int) DB::table('challenge_entries')->where('created_at', '>', now()->subDays(30))->count(),
                'open' => $rows->where('status', 'ouvert')->count()],
        ]);
    }

    public function closeChallenge(Challenges $challenges, int $id): RedirectResponse
    {
        $c = DB::table('challenges')->find($id);
        abort_unless($c && $c->status === 'ouvert', 404);
        $challenges->close($c);

        return back()->with('saved', 'Défi clôturé, gagnant annoncé et récompensé.');
    }

    public function createChallenge(Request $request, Challenges $challenges): RedirectResponse
    {
        if ($request->input('mode') === 'special') {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'],
                'sport' => ['nullable', 'in:'.implode(',', \App\Terrain\SportFields::sports())], 'country' => ['nullable', 'in:'.implode(',', \App\Terrain\SportFields::COUNTRIES)],
                'prize' => ['nullable', 'string', 'max:160'], 'sponsor' => ['nullable', 'string', 'max:120'], 'sponsor_url' => ['nullable', 'url', 'max:500'],
                'starts_on' => ['nullable', 'date'], 'days' => ['required', 'integer', 'min:1', 'max:60'],
            ]);
            app(\App\Terrain\Growth\ContentGuard::class)->enforce(['title' => $data['title'], 'description' => $data['description'] ?? null], 'challenge');
            $c = $challenges->special($data);

            return back()->with('saved', 'Défi spécial « '.$c->title.' » ouvert.');
        }
        $data = $request->validate(['theme' => ['required', 'in:'.implode(',', array_keys(Challenges::THEMES))], 'days' => ['required', 'integer', 'min:1', 'max:30']]);
        $challenges->open($data['theme'], (int) $data['days']);

        return back()->with('saved', 'Nouveau défi ouvert.');
    }

    /** Retirer (ou remettre) une participation : faux votes, vidéo qui n'est pas la sienne… */
    public function excludeEntry(Request $request, Challenges $challenges, int $id): RedirectResponse
    {
        $e = DB::table('challenge_entries')->find($id);
        abort_unless($e, 404);
        $challenges->exclude($id, ! $e->excluded);
        if (! $e->excluded && $e->user_key) {
            $this->notifier->send($e->user_key, 'moderation', 'Ta participation au défi a été retirée', 'Elle ne respecte pas les règles du défi. Contacte le support si c’est une erreur.', route('support'));
        }

        return back()->with('saved', $e->excluded ? 'Participation remise.' : 'Participation retirée du classement.');
    }

    // ------------------------------------------------------------------ commandes de montage vidéo

    public function montages(Request $request): View
    {
        $status = $request->query('statut', 'a_faire');
        $rows = DB::table('video_orders')
            ->when($status === 'a_faire', fn ($q) => $q->whereIn('status', ['payee', 'en_cours']))
            ->when(! in_array($status, ['a_faire', 'toutes'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'payee' then 0 when 'en_cours' then 1 else 2 end")->orderByDesc('id')->limit(200)->get();
        $names = DB::table('members')->whereIn('user_key', $rows->pluck('user_key'))->pluck('name', 'user_key');

        return view('admin.montages', ['rows' => $rows, 'names' => $names, 'status' => $status, 'labels' => \App\Http\Controllers\VideoServiceController::STATUS]);
    }

    /** Téléchargement d'un rush envoyé par le sportif. */
    public function montageFile(int $id, int $n): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $o = DB::table('video_orders')->find($id);
        abort_unless($o, 404);
        $f = json_decode((string) $o->files, true)[$n] ?? null;
        $path = $f ? storage_path("app/montages/{$o->reference}/".basename($f['file'])) : null;
        abort_unless($path && is_file($path), 404);

        return response()->download($path, $o->reference.'-'.($n + 1).'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $f['original']));
    }

    public function updateMontage(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $o = DB::table('video_orders')->find($id);
        abort_unless($o, 404);
        $data = $request->validate([
            'status' => ['required', 'in:payee,en_cours,livree,annulee'],
            'delivered_url' => [$request->input('status') === 'livree' ? 'required' : 'nullable', 'url', 'max:500'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ], ['delivered_url.required' => 'Colle le lien du montage livré (Google Drive, YouTube non répertorié…).']);
        DB::table('video_orders')->where('id', $id)->update([
            'status' => $data['status'], 'delivered_url' => $data['delivered_url'] ?? $o->delivered_url, 'admin_note' => $data['admin_note'] ?? $o->admin_note,
            'delivered_at' => $data['status'] === 'livree' ? ($o->delivered_at ?? now()) : $o->delivered_at, 'updated_at' => now(),
        ]);
        if ($data['status'] !== $o->status) {
            $msg = [
                'en_cours' => ['Ton montage est en cours', 'Notre équipe travaille sur ta compilation.'],
                'livree' => ['Ton montage est prêt 🎬', 'Ta compilation est prête. Publie-la sur ton profil !'],
                'annulee' => ['Commande de montage annulée', $data['admin_note'] ?? 'Contacte-nous pour plus d’informations.'],
            ][$data['status']] ?? null;
            if ($msg) {
                $this->notifier->send($o->user_key, 'video', $msg[0], $msg[1].' (réf. '.$o->reference.')', route('video.orders'));
                if ($data['status'] === 'livree' && $o->whatsapp) {
                    $this->notifier->sms($o->whatsapp, config('terrain.app_name').' : '.$msg[1].' '.route('video.orders'));
                }
            }
        }
        // Les rushs sont effacés 30 jours après la livraison (voir la commande terrain:paiements)
        return back()->with('saved', 'Commande '.$o->reference.' mise à jour.');
    }
}
