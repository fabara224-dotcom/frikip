<?php

namespace App\Terrain\Messaging;

use App\Terrain\DemoData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Messagerie et appels du mode démo, enregistrés dans la base du site.
 *
 * Chaque visiteur a sa « salle ». Le sportif de démo (a1) et le recruteur de démo (r1) d'une même salle
 * se parlent pour de vrai : il suffit d'ouvrir le lien d'invitation sur un deuxième appareil.
 * Les autres profils sont fictifs : ils répondent automatiquement aux messages et ne décrochent jamais.
 */
class DemoMessenger implements Messenger
{
    /** Les deux comptes de démo « réels » d'une salle */
    public const LIVE = ['a1', 'r1'];

    private const RING_SECONDS = 40;

    private const FICTIONAL_RING_SECONDS = 8;

    public function __construct(protected DemoData $data)
    {
    }

    // ------------------------------------------------------------------ salle

    public function room(): string
    {
        $room = session('demo_room');
        if (! $room) {
            $room = Str::lower(Str::random(10));
            session(['demo_room' => $room]);
        }
        $this->seed($room);

        return $room;
    }

    /** Rejoindre la salle d'un autre appareil (lien d'invitation). */
    public function join(string $room): void
    {
        session(['demo_room' => $room]);
        Cache::put("demo-room:{$room}:joined", true, now()->addDays(2));
        $this->seed($room);
    }

    public function joined(): bool
    {
        return (bool) Cache::get('demo-room:'.$this->room().':joined', false);
    }

    protected function seed(string $room): void
    {
        if (DB::table('demo_messages')->where('room', $room)->exists()) {
            return;
        }
        $t = now();
        DB::table('demo_messages')->insert([
            ['room' => $room, 'sender' => 'r1', 'recipient' => 'a1', 'kind' => 'text', 'body' => 'Bonjour Mamadou, nous avons vu ta compilation. Es-tu disponible pour un essai le 15 novembre ?', 'media_path' => null, 'duration' => null, 'read_at' => $t->copy()->subHours(2), 'created_at' => $t->copy()->subHours(3)],
            ['room' => $room, 'sender' => 'a1', 'recipient' => 'r1', 'kind' => 'text', 'body' => 'Merci beaucoup ! Oui, je suis disponible.', 'media_path' => null, 'duration' => null, 'read_at' => $t->copy()->subHours(2), 'created_at' => $t->copy()->subHours(2)->subMinutes(50)],
        ]);
    }

    /** Ce correspondant est-il une vraie personne connectée dans la salle ? */
    protected function isLive(string $id): bool
    {
        return in_array($id, self::LIVE, true) && $this->joined();
    }

    // ------------------------------------------------------------------ messages

    public function me(): ?string
    {
        $u = $this->data->user();

        return $u ? ($u['athlete_id'] ?? $u['id']) : null;
    }

    protected function between(string $partnerId)
    {
        $me = $this->me();

        return DB::table('demo_messages')->where('room', $this->room())->where('created_at', '<=', now())
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('sender', $me)->where('recipient', $partnerId))
                ->orWhere(fn ($q) => $q->where('sender', $partnerId)->where('recipient', $me)));
    }

    public function threads(): array
    {
        $this->release();
        $me = $this->me();
        if (! $me) {
            return [];
        }
        $rows = DB::table('demo_messages')->where('room', $this->room())->where('created_at', '<=', now())
            ->where(fn ($q) => $q->where('sender', $me)->orWhere('recipient', $me))
            ->orderBy('created_at')->orderBy('id')->get();
        $by = [];
        foreach ($rows as $r) {
            $other = $r->sender === $me ? $r->recipient : $r->sender;
            $by[$other]['last'] = $this->format($r);
            $by[$other]['unread'] = ($by[$other]['unread'] ?? 0) + (($r->recipient === $me && ! $r->read_at) ? 1 : 0);
        }
        uasort($by, fn ($a, $b) => $b['last']['ts'] <=> $a['last']['ts']);
        $out = [];
        foreach ($by as $id => $t) {
            $out[] = ['partner' => $this->data->person((string) $id), 'last' => $t['last'], 'unread' => $t['unread']];
        }

        return $out;
    }

    public function unreadTotal(): int
    {
        $this->release();
        $me = $this->me();

        return $me ? DB::table('demo_messages')->where('room', $this->room())->where('recipient', $me)
            ->whereNull('read_at')->where('created_at', '<=', now())->count() : 0;
    }

    private function markRead(string $partnerId): void
    {
        DB::table('demo_messages')->where('room', $this->room())->where('sender', $partnerId)->where('recipient', $this->me())
            ->whereNull('read_at')->where('created_at', '<=', now())->update(['read_at' => now()]);
    }

    public function conversation(string $partnerId): array
    {
        $this->release();
        $this->markRead($partnerId);

        return ['partner' => $this->data->person($partnerId), 'messages' => $this->between($partnerId)->orderBy('created_at')->orderBy('id')->get()->map(fn ($r) => $this->format($r))->all()];
    }

    public function since(string $partnerId, ?string $afterId): array
    {
        $this->release();
        $this->markRead($partnerId);
        $rows = $this->between($partnerId)->when($afterId, fn ($q) => $q->where('id', '>', (int) $afterId))->orderBy('id')->get();
        $readUpto = DB::table('demo_messages')->where('room', $this->room())->where('sender', $this->me())->where('recipient', $partnerId)
            ->whereNotNull('read_at')->where('read_at', '<=', now())->max('id');

        return ['messages' => $rows->map(fn ($r) => $this->format($r))->all(), 'read_upto' => $readUpto ? (string) $readUpto : null];
    }

    public function canContact(string $partnerId): bool
    {
        $u = $this->data->user();
        if (($u['role'] ?? '') !== 'recruiter' || $this->data->isPremium() || app(\App\Terrain\Growth\Members::class)->isPremium()) {
            return true;
        }
        $mine = DB::table('demo_messages')->where('room', $this->room())->where('sender', $this->me())->distinct()->pluck('recipient')->all();
        $mine = array_diff($mine, ['a1']);   // la conversation d'exemple ne compte pas

        return $partnerId === 'a1' || in_array($partnerId, $mine, true) || count($mine) < (int) config('terrain.free_contacts', 3);
    }

    public function sendText(string $partnerId, string $text): array
    {
        $id = $this->insert($partnerId, 'text', $text);
        $this->fakeReply($partnerId, 'text');

        return $this->find($id);
    }

    public function sendMedia(string $partnerId, string $kind, UploadedFile $file, ?int $duration = null): array
    {
        $ext = strtolower((string) $file->guessExtension());
        $allowed = $kind === 'voice' ? ['webm', 'weba', 'ogg', 'oga', 'm4a', 'mp4', 'mp3', 'aac', 'wav'] : ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
        if (! in_array($ext, $allowed, true)) {
            $ext = $kind === 'voice' ? 'webm' : 'jpg';
        }
        $path = $file->storeAs('demo-media/'.$this->room(), Str::uuid().'.'.$ext, 'local');
        $id = $this->insert($partnerId, $kind, null, $path, $duration);
        $this->fakeReply($partnerId, $kind);

        return $this->find($id);
    }

    public function localMedia(string $messageId): ?string
    {
        $me = $this->me();
        $r = DB::table('demo_messages')->where('room', $this->room())->where('id', (int) $messageId)
            ->where(fn ($q) => $q->where('sender', $me)->orWhere('recipient', $me))->first();

        return $r && $r->media_path && Storage::disk('local')->exists($r->media_path) ? Storage::disk('local')->path($r->media_path) : null;
    }

    protected function insert(string $to, string $kind, ?string $body, ?string $media = null, ?int $duration = null, ?string $from = null, ?Carbon $at = null): int
    {
        return (int) DB::table('demo_messages')->insertGetId([
            'room' => $this->room(), 'sender' => $from ?? $this->me(), 'recipient' => $to, 'kind' => $kind, 'body' => $body,
            'media_path' => $media, 'duration' => $duration, 'read_at' => null, 'created_at' => $at ?? now(),
        ]);
    }

    protected function find(int $id): array
    {
        return $this->format(DB::table('demo_messages')->find($id));
    }

    /** Les profils fictifs (et l'autre compte de démo tant que personne n'a rejoint la salle) répondent tout seuls. */
    protected function fakeReply(string $partnerId, string $kind): void
    {
        if ($this->isLive($partnerId)) {
            return;
        }
        // l'autre « lit » le message au bout de 2 s et répond au bout de 4 s
        DB::table('demo_messages')->where('room', $this->room())->where('sender', $this->me())->where('recipient', $partnerId)
            ->whereNull('read_at')->update(['read_at' => now()->addSeconds(2)]);
        $recruiter = ($this->data->user()['role'] ?? '') === 'recruiter';
        $text = match (true) {
            $kind === 'voice' => 'Bien reçu ton vocal, merci ! Je te réponds avec les détails.',
            $kind === 'image' => 'Merci pour la photo.',
            $recruiter => 'Merci ! Je vous envoie mes disponibilités pour un essai.',
            default => "Parfait. Nous t'enverrons l'adresse du stade. L'essai est gratuit.",
        };
        // La réponse est mise en attente et apparaît au bout de 4 s (voir release()).
        $key = 'demo-pending:'.$this->room();
        $pending = Cache::get($key, []);
        $pending[] = ['due' => now()->addSeconds(4)->timestamp, 'to' => $this->me(), 'from' => $partnerId, 'text' => $text];
        Cache::put($key, $pending, now()->addHour());
    }

    /** Publie les réponses automatiques dont l'heure est venue (les identifiants restent dans l'ordre d'arrivée). */
    protected function release(): void
    {
        $key = 'demo-pending:'.$this->room();
        $pending = Cache::get($key, []);
        if (! $pending) {
            return;
        }
        $keep = [];
        foreach ($pending as $p) {
            if ($p['due'] <= now()->timestamp) {
                $this->insert($p['to'], 'text', $p['text'], null, null, $p['from']);
            } else {
                $keep[] = $p;
            }
        }
        Cache::put($key, $keep, now()->addHour());
    }

    protected function format(object $r): array
    {
        $me = $this->me();
        $at = Carbon::parse($r->created_at);
        $msg = [
            'id' => (string) $r->id, 'cursor' => (string) $r->id, 'mine' => $r->sender === $me, 'kind' => $r->kind, 'text' => (string) $r->body,
            'media_url' => $r->media_path ? route('messages.media', $r->id) : null, 'duration' => $r->duration,
            'at' => $at->format('H:i'), 'day' => $at->format('Y-m-d'), 'ts' => $at->toIso8601String(),
            'read' => $r->sender === $me && $r->read_at && Carbon::parse($r->read_at)->lte(now()),
        ];
        if ($r->kind === 'call') {
            [$kind, $status, $caller] = array_pad(explode('|', (string) $r->body), 3, '');
            $msg['call'] = ['kind' => $kind, 'status' => $status, 'outgoing' => $caller === $me];
            $msg['text'] = CallText::label($kind, $status, $caller === $me, $r->duration);
        }

        return $msg;
    }

    // ------------------------------------------------------------------ appels

    public function canCall(string $partnerId): array
    {
        $me = $this->me();
        if (! $me || $partnerId === $me) {
            return [false, 'Connecte-toi pour appeler.'];
        }
        $u = $this->data->user();
        $myAthlete = $u['athlete_id'] ? $this->data->athlete($u['athlete_id']) : null;
        if ($this->data->person($partnerId)['minor'] || ($myAthlete && $myAthlete['age'] < 18)) {
            return [false, CallText::MINOR];
        }
        if (! DB::table('demo_messages')->where('room', $this->room())->where('sender', $partnerId)->where('recipient', $me)->where('created_at', '<=', now())->exists()) {
            return [false, CallText::NO_REPLY];
        }

        return [true, ''];
    }

    public function startCall(string $partnerId, string $kind): array
    {
        $me = $this->me();
        DB::table('demo_calls')->where('room', $this->room())->where('caller', $me)->where('status', 'ringing')
            ->update(['status' => 'cancelled', 'ended_at' => now(), 'updated_at' => now()]);
        $id = (string) Str::uuid();
        DB::table('demo_calls')->insert([
            'id' => $id, 'room' => $this->room(), 'caller' => $me, 'callee' => $partnerId, 'kind' => $kind === 'video' ? 'video' : 'audio',
            'status' => 'ringing', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->call($id);
    }

    public function incomingCall(): ?array
    {
        $me = $this->me();
        if (! $me) {
            return null;
        }
        $r = DB::table('demo_calls')->where('room', $this->room())->where('callee', $me)->where('status', 'ringing')
            ->where('created_at', '>', now()->subSeconds(self::RING_SECONDS))->orderByDesc('created_at')->first();

        return $r ? $this->formatCall($r) : null;
    }

    public function call(string $callId): ?array
    {
        $me = $this->me();
        $r = DB::table('demo_calls')->where('room', $this->room())->where('id', $callId)
            ->where(fn ($q) => $q->where('caller', $me)->orWhere('callee', $me))->first();
        if (! $r) {
            return null;
        }
        // Personne ne décroche : profil fictif (8 s) ou délai dépassé (40 s)
        $limit = $this->isLive($r->callee) ? self::RING_SECONDS : self::FICTIONAL_RING_SECONDS;
        if ($r->status === 'ringing' && Carbon::parse($r->created_at)->lt(now()->subSeconds($limit))) {
            $this->finish($r, 'missed');
            $r = DB::table('demo_calls')->find($callId);
        }

        return $this->formatCall($r);
    }

    public function setCallStatus(string $callId, string $status): ?array
    {
        $call = $this->call($callId);
        if (! $call) {
            return null;
        }
        $r = DB::table('demo_calls')->find($callId);
        $me = $this->me();
        $ok = match ($status) {
            'accepted', 'declined' => $r->status === 'ringing' && $r->callee === $me,
            'cancelled' => $r->status === 'ringing' && $r->caller === $me,
            'ended' => $r->status === 'accepted' || $r->status === 'ringing',
            default => false,
        };
        if ($ok) {
            if ($status === 'accepted') {
                DB::table('demo_calls')->where('id', $callId)->update(['status' => 'accepted', 'answered_at' => now(), 'updated_at' => now()]);
            } else {
                $this->finish($r, $status === 'ended' && $r->status === 'ringing' ? ($r->caller === $me ? 'cancelled' : 'declined') : $status);
            }
        }

        return $this->call($callId);
    }

    /** Fin d'appel + ligne dans la conversation (« Appel vidéo · 2 min 13 s », « Appel manqué »…). */
    private function finish(object $r, string $status): void
    {
        $updated = DB::table('demo_calls')->where('id', $r->id)->whereIn('status', ['ringing', 'accepted'])
            ->update(['status' => $status, 'ended_at' => now(), 'updated_at' => now()]);
        if (! $updated) {
            return;
        }
        $duration = $r->answered_at ? Carbon::parse($r->answered_at)->diffInSeconds(now()) : null;
        DB::table('demo_messages')->insert([
            'room' => $r->room, 'sender' => $r->caller, 'recipient' => $r->callee, 'kind' => 'call',
            'body' => "{$r->kind}|{$status}|{$r->caller}", 'media_path' => null, 'duration' => $duration ? (int) $duration : null,
            'read_at' => in_array($status, ['missed'], true) ? null : now(), 'created_at' => now(),
        ]);
    }

    public function sendSignal(string $callId, string $type, string $payload): void
    {
        $call = $this->call($callId);
        if ($call && in_array($call['status'], ['ringing', 'accepted'], true)) {
            DB::table('demo_call_signals')->insert(['call_id' => $callId, 'sender' => $this->me(), 'type' => $type, 'payload' => $payload, 'created_at' => now()]);
        }
    }

    public function signals(string $callId, ?string $afterId): array
    {
        if (! $this->call($callId)) {
            return [];
        }

        return DB::table('demo_call_signals')->where('call_id', $callId)->where('sender', '!=', $this->me())
            ->when($afterId, fn ($q) => $q->where('id', '>', (int) $afterId))->orderBy('id')->get()
            ->map(fn ($s) => ['id' => (string) $s->id, 'type' => $s->type, 'payload' => $s->payload])->all();
    }

    private function formatCall(object $r): array
    {
        $outgoing = $r->caller === $this->me();

        return [
            'id' => $r->id, 'kind' => $r->kind, 'status' => $r->status, 'outgoing' => $outgoing,
            'partner' => $this->data->person($outgoing ? $r->callee : $r->caller),
            'created_ts' => Carbon::parse($r->created_at)->toIso8601String(),
            'answered_ts' => $r->answered_at ? Carbon::parse($r->answered_at)->toIso8601String() : null,
            'fictional' => $outgoing && ! $this->isLive($r->callee),
        ];
    }
}
