<?php

namespace App\Http\Controllers;

use App\Terrain\Growth\Ads;
use App\Terrain\Growth\Algorithm;
use App\Terrain\Growth\Coaching;
use App\Terrain\Growth\Live;
use App\Terrain\Growth\Notifier;
use App\Terrain\SportFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Espace admin : publicités, matchs en direct et coaching. */
class AdminMediaController extends Controller
{
    // ================================================================== Publicités

    public function ads(Request $request, Algorithm $algo): View
    {
        $rows = DB::table('ads')->orderByDesc('active')->orderByDesc('id')->get();
        $since = now()->subDays(29)->toDateString();
        $daily = DB::table('ad_daily')->where('day', '>=', $since)->selectRaw('day, sum(impressions) as i, sum(clicks) as c')->groupBy('day')->pluck('i', 'day');
        $edit = $request->filled('modifier') ? DB::table('ads')->find((int) $request->query('modifier')) : null;
        $adfree = 0;
        try {
            $adfree = DB::table('members')->where('adfree_until', '>', now())->count();
        } catch (\Throwable) {
        }

        return view('admin.ads', [
            'rows' => $rows, 'daily' => $daily, 'edit' => $edit, 'settings' => Algorithm::SETTINGS, 'values' => $algo->all(),
            'countries' => SportFields::COUNTRIES, 'sports' => SportFields::sports(), 'chunk' => PublishController::CHUNK,
            'totals' => ['imp' => (int) $rows->sum('impressions'), 'clk' => (int) $rows->sum('clicks'), 'active' => $rows->where('active', true)->count(),
                'revenue' => (int) $rows->where('currency', 'GNF')->sum('price'), 'adfree' => $adfree],
        ]);
    }

    /** Réception des morceaux d'une vidéo de pub (même mécanisme que la publication des sportifs). */
    public function adChunk(Request $request): JsonResponse
    {
        return app(PublishController::class)->asOwner('admin')->chunk($request);
    }

    public function saveAd(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $ad = $id ? DB::table('ads')->find($id) : null;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'advertiser' => ['required', 'string', 'max:120'],
            'video_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'upload_id' => ['nullable', 'string', 'max:40'],
            'file_name' => ['nullable', 'string', 'max:160'],
            'poster' => ['nullable', 'image', 'max:3072'],
            'link' => ['nullable', 'url', 'starts_with:https://,http://', 'max:500'],
            'cta' => ['nullable', 'string', 'max:40'],
            'countries' => ['nullable', 'array'], 'countries.*' => [Rule::in(SportFields::COUNTRIES)],
            'sport' => ['nullable', Rule::in(SportFields::sports())],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_impressions' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:10'],
            'price' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', Rule::in(['GNF', 'XOF', 'USD', 'EUR'])],
        ], ['video_url.starts_with' => 'Le lien de la vidéo doit commencer par https://']);

        $row = [
            'title' => $data['title'], 'advertiser' => $data['advertiser'], 'video_url' => $data['video_url'] ?? null,
            'link' => $data['link'] ?? null, 'cta' => $data['cta'] ?: 'En savoir plus',
            'countries' => ! empty($data['countries']) ? json_encode(array_values($data['countries'])) : null, 'sport' => $data['sport'] ?? null,
            'starts_on' => $data['starts_on'] ?? null, 'ends_on' => $data['ends_on'] ?? null, 'max_impressions' => (int) ($data['max_impressions'] ?? 0),
            'weight' => (int) ($data['weight'] ?? 1), 'price' => (int) ($data['price'] ?? 0), 'currency' => $data['currency'] ?? 'GNF', 'updated_at' => now(),
        ];
        if (! empty($data['upload_id'])) {
            $file = app(PublishController::class)->asOwner('admin')->assembled($data['upload_id'], $data['file_name'] ?? 'pub.mp4');
            $name = 'pub-'.Str::random(16).'.'.($file->getClientOriginalExtension() ?: 'mp4');
            rename($file->getPathname(), Ads::dir().'/'.$name);
            $row['video_path'] = $name;
            $row['video_url'] = null;
            if ($ad && $ad->video_path) {
                @unlink(Ads::dir().'/'.basename($ad->video_path));
            }
        } elseif (! empty($data['video_url'])) {
            $row['video_path'] = null;
        }
        if ($request->hasFile('poster')) {
            $name = 'affiche-'.Str::random(16).'.'.$request->file('poster')->extension();
            $request->file('poster')->move(Ads::dir(), $name);
            $row['poster_path'] = $name;
        }
        if (! ($row['video_path'] ?? $ad->video_path ?? null) && ! $row['video_url']) {
            return back()->withInput()->withErrors(['video_url' => 'Ajoute la vidéo de la pub (fichier ou lien https://…mp4).']);
        }
        if ($ad) {
            DB::table('ads')->where('id', $ad->id)->update($row);
        } else {
            DB::table('ads')->insert($row + ['active' => true, 'created_at' => now()]);
        }

        return redirect()->route('admin.ads')->with('saved', $ad ? 'Publicité modifiée.' : 'Publicité ajoutée : elle apparaît maintenant dans le fil.');
    }

    public function adAction(Request $request, int $id): RedirectResponse
    {
        $ad = DB::table('ads')->find($id);
        abort_unless($ad, 404);
        if ($request->input('action') === 'supprimer') {
            foreach (['video_path', 'poster_path'] as $c) {
                if ($ad->{$c}) {
                    @unlink(Ads::dir().'/'.basename($ad->{$c}));
                }
            }
            DB::table('ads')->where('id', $id)->delete();
            DB::table('ad_daily')->where('ad_id', $id)->delete();

            return back()->with('saved', 'Publicité supprimée.');
        }
        DB::table('ads')->where('id', $id)->update(['active' => ! $ad->active, 'updated_at' => now()]);

        return back()->with('saved', $ad->active ? 'Publicité mise en pause.' : 'Publicité réactivée.');
    }

    /** Réglages des pubs et des directs (enregistrés avec ceux de l'algorithme, donc dans le journal). */
    public function saveSettings(Request $request, Algorithm $algo): RedirectResponse
    {
        $group = in_array($request->input('group'), ['pub', 'direct', 'defis', 'partenaire'], true) ? $request->input('group') : 'pub';
        $input = $request->all();
        if ($group === 'partenaire') {
            $input['partner_url'] = trim((string) ($input['partner_url'] ?? ''));
            if ($input['partner_url'] !== '' && ! \App\Terrain\Growth\PartnerLink::valid($input['partner_url'])) {
                return back()->withInput()->withErrors(['partner_url' => 'Le lien doit commencer par https:// (copie-le en entier depuis le message du partenaire).']);
            }
            if (! empty($input['partner_on']) && $input['partner_url'] === '') {
                return back()->withInput()->withErrors(['partner_url' => 'Colle d’abord le lien du partenaire.']);
            }
        }
        if (isset($input['ads_every'])) {
            $input['ads_every'] = max(3, min(30, (int) $input['ads_every']));
        }
        $algo->save($input, $group);

        return back()->with('saved', 'Réglages enregistrés.');
    }

    // ================================================================== Matchs en direct

    public function live(Request $request, Algorithm $algo): View
    {
        $rows = DB::table('live_streams')->orderByRaw("case when approved = 0 then 0 when status = 'direct' then 1 when status = 'prevu' then 2 else 3 end")
            ->orderByDesc('starts_at')->limit(200)->get();
        $edit = $request->filled('modifier') ? DB::table('live_streams')->find((int) $request->query('modifier')) : null;

        return view('admin.live', ['rows' => $rows, 'edit' => $edit, 'sports' => SportFields::sports(), 'countries' => SportFields::COUNTRIES,
            'settings' => Algorithm::SETTINGS, 'values' => $algo->all(), 'ytReady' => \App\Terrain\Growth\LiveFinder::configured(),
            'ytLast' => \Illuminate\Support\Facades\Cache::get('frikip.yt.last'), 'ytEvery' => app(\App\Terrain\Growth\LiveFinder::class)->interval(),
            'muxReady' => \App\Terrain\Growth\MuxLive::configured()]);
    }

    public function saveLive(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $mux = ! $id && $request->boolean('mux') && \App\Terrain\Growth\MuxLive::configured();
        if ($mux) {
            $request->merge(['stream_url' => 'https://stream.mux.com/attente.m3u8']);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'sport' => ['required', Rule::in(SportFields::sports())],
            'competition' => ['nullable', 'string', 'max:120'], 'home' => ['required', 'string', 'max:80'], 'away' => ['required', 'string', 'max:80'],
            'country' => ['required', Rule::in(SportFields::COUNTRIES)], 'city' => ['nullable', 'string', 'max:80'], 'starts_at' => ['required', 'date'],
            'stream_url' => ['required', 'url', 'starts_with:https://', 'max:500'], 'replay_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'status' => ['required', Rule::in(array_keys(Live::STATUS))], 'home_score' => ['nullable', 'integer', 'min:0', 'max:999'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:999'], 'minute' => ['nullable', 'string', 'max:12'],
            'fixture_id' => ['nullable', 'string', 'max:40', 'regex:/^(api-)?\d+$/'],
        ], ['stream_url.starts_with' => 'Le lien du direct doit commencer par https://']);
        $data['home_score'] = (int) ($data['home_score'] ?? 0);
        $data['away_score'] = (int) ($data['away_score'] ?? 0);
        $data['fixture_id'] = ! empty($data['fixture_id']) ? 'api-'.preg_replace('/^api-/', '', $data['fixture_id']) : null;
        if ($mux) {
            try {
                $m = app(\App\Terrain\Growth\MuxLive::class)->create();
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors(['stream_url' => $e->getMessage()]);
            }
            $data = ['stream_url' => \App\Terrain\Growth\MuxLive::hls($m['playback_id']), 'source' => 'mux', 'mux_id' => $m['id'], 'stream_key' => $m['stream_key'],
                'playback_id' => $m['playback_id'], 'thumbnail' => \App\Terrain\Growth\MuxLive::poster($m['playback_id'])] + $data;
            $data['status'] = $data['status'] === 'direct' ? 'prevu' : $data['status'];   // il passe « En direct » tout seul quand la vidéo arrive
        }
        if ($id) {
            DB::table('live_streams')->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            $id = DB::table('live_streams')->insertGetId($data + ['source' => 'admin', 'approved' => true, 'rights_ok' => true, 'owner_name' => config('terrain.app_name'), 'created_at' => now(), 'updated_at' => now()]);
            if ($mux) {
                return redirect()->route('admin.live', ['modifier' => $id])->with('saved', 'Direct créé : copie l’adresse du serveur et la clé de diffusion dans ton application de diffusion.');
            }

            return redirect()->route('admin.live')->with('saved', 'Direct ajouté.');
        }

        return redirect()->route('admin.live')->with('saved', 'Direct modifié.');
    }

    /** Bouton « Chercher maintenant » : lance la recherche YouTube tout de suite. */
    public function syncLive(\App\Terrain\Growth\LiveFinder $finder): RedirectResponse
    {
        if (! \App\Terrain\Growth\LiveFinder::configured()) {
            return back()->withErrors(['yt' => 'Ajoute d’abord YOUTUBE_API_KEY dans le fichier .env.']);
        }
        \Illuminate\Support\Facades\Cache::put('frikip.yt.sync', now()->toIso8601String(), now()->addMinutes($finder->interval()));
        $r = $finder->sync();
        if ($r['error'] && ! $r['found']) {
            return back()->withErrors(['yt' => 'YouTube : '.$r['error']]);
        }

        return back()->with('saved', "{$r['found']} match(s) en direct trouvé(s), {$r['added']} nouveau(x), {$r['ended']} terminé(s).");
    }

    public function liveAction(Request $request, int $id): RedirectResponse
    {
        $s = DB::table('live_streams')->find($id);
        abort_unless($s, 404);
        $action = (string) $request->input('action');
        if ($action === 'supprimer') {
            if ($s->mux_id ?? null) {
                app(\App\Terrain\Growth\MuxLive::class)->finish($s->mux_id);
            }
            DB::table('live_streams')->where('id', $id)->delete();
            DB::table('live_viewers')->where('stream_id', $id)->delete();

            return back()->with('saved', 'Direct supprimé.');
        }
        if ($action === 'valider' || $action === 'retirer') {
            DB::table('live_streams')->where('id', $id)->update(['approved' => $action === 'valider', 'updated_at' => now()]);
            if ($s->owner_key) {
                app(Notifier::class)->send($s->owner_key, 'event', $action === 'valider' ? 'Ton direct est validé' : 'Ton direct a été retiré',
                    $s->home.' – '.$s->away, route('live.manage', $id));
            }

            return back()->with('saved', $action === 'valider' ? 'Direct validé : il est visible.' : 'Direct retiré du site.');
        }
        if ($action === 'termine' && $s->status !== 'termine') {
            app(\App\Terrain\Growth\MuxLive::class)->end($s);
        }
        if (in_array($action, array_keys(Live::STATUS), true)) {
            DB::table('live_streams')->where('id', $id)->update(['status' => $action, 'updated_at' => now()]);
        }

        return back()->with('saved', 'Direct mis à jour.');
    }

    // ================================================================== Diffuseurs officiels

    public function broadcasters(Request $request): View
    {
        return view('admin.broadcasters', [
            'settings' => \App\Terrain\Growth\Algorithm::SETTINGS, 'values' => app(\App\Terrain\Growth\Algorithm::class)->all(),
            'partner' => app(\App\Terrain\Growth\PartnerLink::class)->get(),
            'rows' => DB::table('broadcasters')->orderByDesc('active')->orderBy('priority')->orderBy('name')->get(),
            'edit' => $request->filled('modifier') ? DB::table('broadcasters')->find((int) $request->query('modifier')) : null,
            'comps' => \App\Terrain\RealScores::COMPETITIONS, 'countries' => SportFields::COUNTRIES,
            'apiReady' => app(\App\Terrain\RealScores::class)->enabled(),
        ]);
    }

    public function saveBroadcaster(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'url' => ['required', 'url', 'max:500'], 'note' => ['nullable', 'string', 'max:160'],
            'competitions' => ['nullable', 'array'], 'competitions.*' => ['integer'], 'countries' => ['nullable', 'array'], 'countries.*' => [Rule::in(SportFields::COUNTRIES)],
            'embed_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'], 'priority' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $row = ['name' => $data['name'], 'url' => $data['url'], 'note' => $data['note'] ?? null,
            'competitions' => ! empty($data['competitions']) ? json_encode(array_map('intval', $data['competitions'])) : null,
            'countries' => ! empty($data['countries']) ? json_encode(array_values($data['countries'])) : null,
            'embed_url' => $data['embed_url'] ?? null, 'contract' => $request->boolean('contract') && ! empty($data['embed_url']),
            'active' => $request->boolean('active'), 'priority' => (int) ($data['priority'] ?? 5), 'updated_at' => now()];
        if ($id) {
            DB::table('broadcasters')->where('id', $id)->update($row);
        } else {
            DB::table('broadcasters')->insert($row + ['created_at' => now()]);
        }

        return redirect()->route('admin.broadcasters')->with('saved', $id ? 'Diffuseur modifié.' : 'Diffuseur ajouté.');
    }

    public function broadcasterAction(Request $request, int $id): RedirectResponse
    {
        $b = DB::table('broadcasters')->find($id);
        abort_unless($b, 404);
        if ($request->input('action') === 'supprimer') {
            DB::table('broadcasters')->where('id', $id)->delete();

            return back()->with('saved', 'Diffuseur supprimé.');
        }
        DB::table('broadcasters')->where('id', $id)->update(['active' => ! $b->active, 'updated_at' => now()]);

        return back()->with('saved', $b->active ? 'Diffuseur masqué.' : 'Diffuseur affiché sur les matchs.');
    }

    // ================================================================== Coaching

    public function coaching(Request $request): View
    {
        $status = $request->query('statut', 'a_traiter');
        $rows = DB::table('coaching_requests')
            ->when($status === 'a_traiter', fn ($q) => $q->whereIn('status', ['payee', 'en_cours']))
            ->when(! in_array($status, ['a_traiter', 'toutes'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'payee' then 0 when 'en_cours' then 1 else 2 end")->orderByDesc('id')->limit(200)->get();
        $unread = DB::table('coaching_messages')->where('author', 'sportif')->where('read', false)->selectRaw('request_id, count(*) as n')->groupBy('request_id')->pluck('n', 'request_id');

        return view('admin.coaching', [
            'rows' => $rows, 'status' => $status, 'unread' => $unread, 'coaches' => DB::table('coaches')->orderByDesc('active')->orderBy('name')->get(),
            'names' => $this->names($rows->pluck('user_key')->all()),
            'labels' => Coaching::STATUS, 'sports' => SportFields::sports(),
            'edit' => $request->filled('coach') ? DB::table('coaches')->find((int) $request->query('coach')) : null,
            'revenue' => (int) DB::table('coaching_requests')->whereNotNull('paid_at')->where('currency', 'GNF')->sum('amount'),
            'stats' => ['plans' => (int) DB::table('coaching_stats')->count(), 'paid' => (int) DB::table('coaching_requests')->whereNotNull('paid_at')->count()],
        ]);
    }

    /** Nom affiché des membres (fiche membre, sinon compte). */
    private function names(array $keys): array
    {
        $out = array_filter(DB::table('members')->whereIn('user_key', $keys)->pluck('name', 'user_key')->all());
        try {
            $out += DB::table('accounts')->whereIn('id', array_diff($keys, array_keys($out)))->pluck('name', 'id')->all();
        } catch (\Throwable) {
        }

        return $out;
    }

    public function coachingRequest(int $id): View
    {
        $r = DB::table('coaching_requests')->find($id);
        abort_unless($r, 404);
        DB::table('coaching_messages')->where('request_id', $id)->where('author', 'sportif')->update(['read' => true]);
        $stats = app(Coaching::class)->lastStats($r->user_key);

        return view('admin.coaching-request', [
            'r' => $r, 'msgs' => DB::table('coaching_messages')->where('request_id', $id)->orderBy('id')->get(),
            'coaches' => DB::table('coaches')->where('active', true)->orderBy('name')->get(), 'labels' => Coaching::STATUS,
            'member' => DB::table('members')->where('user_key', $r->user_key)->first(), 'name' => $this->names([$r->user_key])[$r->user_key] ?? 'Sportif',
            'plan' => $stats ? json_decode((string) $stats->plan, true) : null, 'stats' => $stats ? json_decode((string) $stats->stats, true) : null,
        ]);
    }

    public function coachingUpdate(Request $request, int $id): RedirectResponse
    {
        $r = DB::table('coaching_requests')->find($id);
        abort_unless($r, 404);
        $data = $request->validate([
            'coach_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(array_keys(Coaching::STATUS))],
            'body' => ['nullable', 'string', 'max:4000'], 'kind' => ['nullable', Rule::in(['message', 'programme', 'bilan'])],
        ]);
        $upd = [];
        if (array_key_exists('coach_id', $data) && (int) $data['coach_id'] !== (int) $r->coach_id) {
            $upd['coach_id'] = $data['coach_id'] ?: null;
            if ($data['coach_id'] && ($c = DB::table('coaches')->find($data['coach_id']))) {
                app(Notifier::class)->send($r->user_key, 'message', 'Ton coach : '.$c->name, $c->specialty, route('coaching.thread', $r->reference));
                if ($c->user_key) {
                    app(Notifier::class)->send($c->user_key, 'message', 'Nouveau sportif à suivre', $r->package_label.' · '.$r->goal, route('coaching.thread', $r->reference));
                }
            }
        }
        if (! empty($data['status']) && $data['status'] !== $r->status) {
            $upd['status'] = $data['status'];
        }
        if ($upd) {
            DB::table('coaching_requests')->where('id', $id)->update($upd + ['updated_at' => now()]);
        }
        if (! empty($data['body'])) {
            $coach = $r->coach_id ? DB::table('coaches')->where('id', $upd['coach_id'] ?? $r->coach_id)->value('name') : null;
            app(Coaching::class)->message($id, 'coach', $data['body'], $data['kind'] ?? 'message', $coach ?: 'Équipe '.config('terrain.app_name'));
            if (($upd['status'] ?? $r->status) === 'payee') {
                DB::table('coaching_requests')->where('id', $id)->update(['status' => 'en_cours']);
            }
            app(Notifier::class)->send($r->user_key, 'message', 'Nouveau message de ton coach', Str::limit($data['body'], 90), route('coaching.thread', $r->reference));
        }

        return back()->with('saved', 'Enregistré.');
    }

    public function saveCoach(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'sport' => ['nullable', Rule::in(SportFields::sports())],
            'specialty' => ['nullable', 'string', 'max:160'], 'bio' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', Rule::in(SportFields::COUNTRIES)], 'contact' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
        ]);
        // Rattacher le compte Frikip du coach (par e-mail ou téléphone) : il verra ses sportifs dans « Espace coach »
        $key = null;
        if (! empty($data['contact'])) {
            $c = trim($data['contact']);
            $digits = preg_replace('/\D/', '', $c);
            try {
                $key = DB::table('accounts')->where('email', $c)->when(strlen($digits) >= 8, fn ($q) => $q->orWhere('phone', 'like', '%'.substr($digits, -8)))->value('id');
            } catch (\Throwable) {
            }
            $key ??= DB::table('members')->where('email', $c)->when(strlen($digits) >= 8, fn ($q) => $q->orWhere('phone', 'like', '%'.substr($digits, -8)))->value('user_key');
        }
        $row = $data + ['user_key' => $key, 'active' => $request->boolean('active', true), 'updated_at' => now()];
        if ($id) {
            DB::table('coaches')->where('id', $id)->update($row);
        } else {
            DB::table('coaches')->insert($row + ['created_at' => now()]);
        }
        $msg = $id ? 'Coach modifié.' : 'Coach ajouté.';
        if (! empty($data['contact'])) {
            $msg .= $key ? ' Compte Frikip rattaché : il a accès à l’espace coach.' : ' Aucun compte Frikip trouvé avec ce contact : le coach doit d’abord s’inscrire, puis enregistre à nouveau.';
        }

        return redirect()->route('admin.coaching', ['statut' => 'coachs'])->with('saved', $msg);
    }
}
