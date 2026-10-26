<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Ads;
use App\Terrain\Growth\Algorithm;
use App\Terrain\Growth\ContentGuard;
use App\Terrain\Growth\Live;
use App\Terrain\Growth\Members;
use App\Terrain\Growth\Notifier;
use App\Terrain\Growth\Verifications;
use App\Terrain\Region;
use App\Terrain\SportFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Matchs en direct : liste, page du direct (lecteur + score), publication et gestion par les clubs. */
class LiveController extends Controller
{
    public function __construct(private DataSource $data, private Live $live)
    {
    }

    public function index(): View
    {
        app(\App\Terrain\Growth\LiveFinder::class)->maybeSync();
        $country = Region::viewer();
        $base = fn () => DB::table('live_streams')->where('approved', true)->orderByRaw('case when country = ? then 0 else 1 end', [$country]);
        $me = $this->data->user();

        return view('web.live', [
            'onAir' => collect(app(Algorithm::class)->bySport($base()->where('status', 'direct')->orderByDesc('peak')->get()->all())),
            'soon' => $base()->where('status', 'prevu')->orderBy('starts_at')->limit(20)->get(),
            'past' => $base()->where('status', 'termine')->where('updated_at', '>', now()->subDays(30))->orderByDesc('starts_at')->limit(12)->get(),
            'mine' => $me ? DB::table('live_streams')->where('owner_key', $me['id'])->orderByDesc('id')->limit(10)->get() : collect(),
            'canPublish' => ($me['role'] ?? null) === 'recruiter',
            'big' => $this->bigMatches(),
        ]);
    }

    /** Matchs du jour des grands championnats (API-Football), en direct d'abord, avec le diffuseur du pays. */
    private function bigMatches(): array
    {
        $real = app(\App\Terrain\RealScores::class);
        if (! $real->enabled()) {
            return [];
        }
        try {
            $list = array_values(array_filter($real->today(), fn ($m) => in_array((int) $m['league_id'], \App\Terrain\Growth\Broadcasters::BIG, true) && $m['status'] !== 'finished'));
        } catch (\Throwable) {
            return [];
        }
        $bc = app(\App\Terrain\Growth\Broadcasters::class);

        return array_map(fn ($m) => $m + ['tv' => $bc->for((int) $m['league_id'])[0]->name ?? null, 'inside' => (bool) $bc->inFrikip($m)], array_slice($list, 0, 18));
    }

    public function show(Request $request, int $id): View
    {
        $s = app(\App\Terrain\Growth\MuxLive::class)->refresh($this->find($id, true));
        if (! session()->has("live.seen.$id")) {
            session(["live.seen.$id" => true]);
            DB::table('live_streams')->where('id', $id)->increment('views');
        }
        $ads = app(Ads::class);
        $preroll = null;
        if ($s->status !== 'termine' && app(Algorithm::class)->get('ads_live') && $ads->shouldShow($this->data->user()) && ($ad = $ads->pick($s->sport))) {
            $preroll = $ads->present($ad);
        }
        $me = $this->data->user();

        return view('web.live-show', [
            's' => $s, 'player' => Live::embed($s->status === 'termine' && $s->replay_url ? $s->replay_url : $s->stream_url),
            'preroll' => $preroll, 'viewers' => $this->live->viewers($id), 'owner' => $me && $s->owner_key === $me['id'],
            'others' => DB::table('live_streams')->where('approved', true)->where('status', 'direct')->where('id', '!=', $id)->limit(4)->get(),
        ]);
    }

    /** Score, minute, état et spectateurs (appelé toutes les 15 à 30 s). */
    public function state(Request $request, int $id): JsonResponse
    {
        $s = app(\App\Terrain\Growth\MuxLive::class)->refresh($this->find($id, true));
        $viewer = (string) ($this->data->user()['id'] ?? substr(hash('sha256', session()->getId()), 0, 32));
        $n = $s->status === 'direct' ? $this->live->heartbeat($id, $viewer) : 0;

        return response()->json(['home' => $s->home_score, 'away' => $s->away_score, 'minute' => $s->minute, 'status' => $s->status,
            'label' => __(Live::STATUS[$s->status] ?? $s->status), 'viewers' => $n]);
    }

    // ------------------------------------------------------------------ publication par un club

    public function create(): View
    {
        return view('web.live-form', ['s' => null, 'muxReady' => \App\Terrain\Growth\MuxLive::configured(), 'sports' => SportFields::sports(), 'countries' => SportFields::COUNTRIES, 'country' => Region::viewer()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $frikip = $request->input('mode') === 'frikip' && \App\Terrain\Growth\MuxLive::configured();
        if ($frikip) {
            $request->merge(['stream_url' => 'https://stream.mux.com/attente.m3u8']);   // remplacé juste après par le vrai lien
        }
        $data = $this->validated($request, true);
        if ($frikip) {
            try {
                $mux = app(\App\Terrain\Growth\MuxLive::class)->create();
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors(['stream_url' => $e->getMessage()]);
            }
            $data = ['stream_url' => \App\Terrain\Growth\MuxLive::hls($mux['playback_id']), 'source' => 'mux', 'mux_id' => $mux['id'],
                'stream_key' => $mux['stream_key'], 'playback_id' => $mux['playback_id'], 'thumbnail' => \App\Terrain\Growth\MuxLive::poster($mux['playback_id'])] + $data;
        }
        $me = $this->data->user();
        $auto = app(Algorithm::class)->get('live_auto_verified')
            && (app(Verifications::class)->isVerified($me['id']) || app(Members::class)->hasStructure($me['id']));
        $id = DB::table('live_streams')->insertGetId($data + [
            'owner_key' => $me['id'], 'owner_name' => $me['name'] ?? null, 'approved' => $auto, 'status' => 'prevu',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('live.manage', $id)->with('status', $auto
            ? __('Ton direct est publié. Passe-le « En direct » au coup d’envoi.')
            : __('Ton direct est envoyé : il sera visible dès que l’équipe l’aura validé (en général dans l’heure).'));
    }

    public function manage(int $id): View
    {
        $s = app(\App\Terrain\Growth\MuxLive::class)->refresh($this->mineOr404($id));

        return view('web.live-form', ['s' => $s, 'muxReady' => \App\Terrain\Growth\MuxLive::configured(), 'sports' => SportFields::sports(), 'countries' => SportFields::COUNTRIES, 'country' => $s->country]);
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $s = $this->mineOr404($id);
        // Boutons rapides de score (pendant le match)
        if ($request->has('quick')) {
            $q = $request->input('quick');
            $upd = match ($q) {
                'home+' => ['home_score' => $s->home_score + 1], 'home-' => ['home_score' => max(0, $s->home_score - 1)],
                'away+' => ['away_score' => $s->away_score + 1], 'away-' => ['away_score' => max(0, $s->away_score - 1)],
                'direct' => ['status' => 'direct'], 'termine' => ['status' => 'termine'], 'prevu' => ['status' => 'prevu'],
                default => [],
            };
            if ($request->filled('minute')) {
                $upd['minute'] = mb_substr(preg_replace('/[^0-9+\'a-zA-Zé ]/u', '', (string) $request->input('minute')), 0, 12);
            }
            DB::table('live_streams')->where('id', $id)->update($upd + ['updated_at' => now()]);
            if (($upd['status'] ?? null) === 'termine' && $s->status !== 'termine') {
                app(\App\Terrain\Growth\MuxLive::class)->end($s);
            }
            if ($q === 'cle' && $s->mux_id && ($k = app(\App\Terrain\Growth\MuxLive::class)->resetKey($s->mux_id))) {
                DB::table('live_streams')->where('id', $id)->update(['stream_key' => $k]);
            }
            if (($upd['status'] ?? null) === 'direct' && $s->status !== 'direct' && $s->approved) {
                $this->live->announce($s);
            }
            if ($request->expectsJson()) {
                $s = DB::table('live_streams')->find($id);

                return response()->json(['home' => $s->home_score, 'away' => $s->away_score, 'minute' => $s->minute, 'status' => $s->status]);
            }

            return back();
        }
        DB::table('live_streams')->where('id', $id)->update($this->validated($request, false) + ['updated_at' => now()]);

        return back()->with('status', __('Enregistré.'));
    }

    /** Lien « Regarder » vers un diffuseur officiel : compté (suivi des partenariats) puis redirigé. */
    public function watch(int $id): RedirectResponse
    {
        $b = app(\App\Terrain\Growth\Broadcasters::class)->click($id);
        abort_unless($b && $b->active, 404);

        return redirect()->away($b->url);
    }

    /** Webhook Mux : le direct passe « En direct » dès que la vidéo arrive, puis « Terminé » avec le replay. */
    public function muxWebhook(Request $request): \Illuminate\Http\Response
    {
        $body = $request->getContent();
        abort_unless(\App\Terrain\Growth\MuxLive::validSignature((string) $request->header('Mux-Signature'), $body), 401);
        $e = json_decode($body, true) ?: [];
        $type = (string) ($e['type'] ?? '');
        $muxId = (string) ($e['data']['id'] ?? '');
        if ($muxId && in_array($type, ['video.live_stream.active', 'video.live_stream.idle'], true) && ($s = DB::table('live_streams')->where('mux_id', $muxId)->first())) {
            app(\App\Terrain\Growth\MuxLive::class)->apply($s, $type === 'video.live_stream.active' ? 'active' : 'idle', (array) ($e['data']['recent_asset_ids'] ?? []));
        }

        return response('OK');
    }

    private function validated(Request $request, bool $new): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'sport' => ['required', Rule::in(SportFields::sports())],
            'competition' => ['nullable', 'string', 'max:120'],
            'home' => ['required', 'string', 'max:80'],
            'away' => ['required', 'string', 'max:80'],
            'country' => ['required', Rule::in(SportFields::COUNTRIES)],
            'city' => ['nullable', 'string', 'max:80'],
            'starts_at' => ['required', 'date'],
            'stream_url' => ['required', 'string', 'max:500', function ($a, $v, $fail) {
                if (! Live::validUrl($v)) {
                    $fail(__('Colle le lien complet du direct (il commence par https://).'));
                }
            }],
            'replay_url' => ['nullable', 'string', 'max:500', function ($a, $v, $fail) {
                if ($v && ! Live::validUrl($v)) {
                    $fail(__('Le lien doit commencer par https://'));
                }
            }],
            'rights_ok' => [$new ? 'accepted' : 'nullable'],
        ], ['rights_ok.accepted' => __('Confirme que tu as le droit de diffuser ce match.')]);
        app(ContentGuard::class)->enforce(['title' => $data['title'], 'competition' => $data['competition'] ?? null, 'home' => $data['home'], 'away' => $data['away']], 'live');
        $data['rights_ok'] = true;

        return $data;
    }

    private function find(int $id, bool $public): object
    {
        $s = DB::table('live_streams')->find($id);
        $me = $this->data->user();
        abort_unless($s && ($s->approved || ($me && $s->owner_key === $me['id'])), 404);

        return $s;
    }

    private function mineOr404(int $id): object
    {
        $s = DB::table('live_streams')->find($id);
        abort_unless($s && $s->owner_key === ($this->data->user()['id'] ?? null), 404);

        return $s;
    }
}
