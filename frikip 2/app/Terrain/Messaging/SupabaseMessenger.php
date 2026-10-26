<?php

namespace App\Terrain\Messaging;

use App\Terrain\SupabaseData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Messagerie et appels avec Supabase (mêmes tables que l'application mobile : voir supabase/messagerie.sql).
 * Les fichiers (vocaux, photos) sont dans le bucket PRIVÉ « message-media » et lus via des liens signés d'une heure.
 */
class SupabaseMessenger implements Messenger
{
    private const BUCKET = 'message-media';

    public function __construct(private SupabaseData $data)
    {
    }

    public function me(): ?string
    {
        return $this->data->user()['id'] ?? null;
    }

    private function get(string $table, array $query): array
    {
        $r = $this->data->rest()->get($this->data->baseUrl()."/rest/v1/{$table}", $query);
        if (! $r->successful()) {
            throw new RuntimeException("Lecture {$table} impossible ({$r->status()})");
        }

        return $r->json() ?? [];
    }

    private function post(string $table, array $row): array
    {
        $r = $this->data->rest()->withHeaders(['Prefer' => 'return=representation'])->post($this->data->baseUrl()."/rest/v1/{$table}", $row);
        if (! $r->successful()) {
            throw new RuntimeException("Écriture {$table} impossible ({$r->status()}) : ".$r->body());
        }

        return $r->json()[0] ?? [];
    }

    private const FIELDS = 'id,sender_id,recipient_id,body,kind,media_path,duration,read_at,created_at';

    private function pair(string $partnerId): string
    {
        $me = $this->me();

        return "(and(sender_id.eq.{$me},recipient_id.eq.{$partnerId}),and(sender_id.eq.{$partnerId},recipient_id.eq.{$me}))";
    }

    // ------------------------------------------------------------------ messages

    public function threads(): array
    {
        $me = $this->me();
        $rows = $this->get('messages', ['or' => "(sender_id.eq.{$me},recipient_id.eq.{$me})", 'select' => self::FIELDS, 'order' => 'created_at.desc', 'limit' => 2000]);
        $by = [];
        foreach ($rows as $r) {
            $other = $r['sender_id'] === $me ? $r['recipient_id'] : $r['sender_id'];
            $by[$other] ??= ['last' => $r, 'unread' => 0];
            $by[$other]['unread'] += ($r['recipient_id'] === $me && ! $r['read_at']) ? 1 : 0;
        }
        $people = $this->data->persons(array_keys($by));
        $out = [];
        foreach ($by as $id => $t) {
            $out[] = ['partner' => $people[$id] ?? $this->data->person($id), 'last' => $this->format($t['last'], []), 'unread' => $t['unread']];
        }

        return $out;
    }

    public function unreadTotal(): int
    {
        $me = $this->me();
        if (! $me) {
            return 0;
        }
        $r = $this->data->rest()->withHeaders(['Prefer' => 'count=exact', 'Range' => '0-0'])
            ->get($this->data->baseUrl().'/rest/v1/messages', ['recipient_id' => "eq.{$me}", 'read_at' => 'is.null', 'select' => 'id']);
        $range = (string) $r->header('Content-Range');

        return (int) Str::after($range, '/');
    }

    private function markRead(string $partnerId): void
    {
        if (Str::isUuid($partnerId)) {
            $this->data->rest()->post($this->data->baseUrl().'/rest/v1/rpc/mark_messages_read', ['p_partner' => $partnerId]);
        }
    }

    public function conversation(string $partnerId): array
    {
        $this->markRead($partnerId);
        $rows = $this->get('messages', ['or' => $this->pair($partnerId), 'select' => self::FIELDS, 'order' => 'created_at.desc', 'limit' => 300]);
        $rows = array_reverse($rows);

        return ['partner' => $this->data->person($partnerId), 'messages' => $this->formatAll($rows)];
    }

    public function since(string $partnerId, ?string $afterId): array
    {
        $this->markRead($partnerId);
        $q = ['or' => $this->pair($partnerId), 'select' => self::FIELDS, 'order' => 'created_at.asc', 'limit' => 200];
        if ($afterId) {
            $q['created_at'] = 'gt.'.$afterId;
        }
        $rows = $this->get('messages', $q);
        $read = $this->get('messages', ['sender_id' => 'eq.'.$this->me(), 'recipient_id' => 'eq.'.$partnerId, 'read_at' => 'not.is.null',
            'select' => 'created_at', 'order' => 'created_at.desc', 'limit' => 1]);

        return ['messages' => $this->formatAll($rows), 'read_upto' => $read[0]['created_at'] ?? null];
    }

    public function canContact(string $partnerId): bool
    {
        $u = $this->data->user();
        if (($u['role'] ?? '') !== 'recruiter' || ! empty($u['premium']) || app(\App\Terrain\Growth\Members::class)->isPremium()) {
            return true;
        }
        $mine = array_unique(array_column($this->get('messages', ['sender_id' => 'eq.'.$this->me(), 'select' => 'recipient_id', 'limit' => 1000]), 'recipient_id'));

        return in_array($partnerId, $mine, true) || count($mine) < (int) config('terrain.free_contacts', 3);
    }

    public function sendText(string $partnerId, string $text): array
    {
        $row = $this->post('messages', ['sender_id' => $this->me(), 'recipient_id' => $partnerId, 'body' => $text, 'kind' => 'text']);

        return $this->format($row, []);
    }

    public function sendMedia(string $partnerId, string $kind, UploadedFile $file, ?int $duration = null): array
    {
        $ext = strtolower((string) $file->guessExtension());
        $allowed = $kind === 'voice' ? ['webm', 'weba', 'ogg', 'oga', 'm4a', 'mp4', 'mp3', 'aac', 'wav'] : ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
        if (! in_array($ext, $allowed, true)) {
            $ext = $kind === 'voice' ? 'webm' : 'jpg';
        }
        $path = $this->me().'/'.Str::uuid().'.'.$ext;
        $r = $this->data->rest()->withBody((string) file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream')
            ->post($this->data->baseUrl().'/storage/v1/object/'.self::BUCKET.'/'.$path);
        if (! $r->successful()) {
            throw new RuntimeException('Envoi du fichier impossible ('.$r->status().')');
        }
        $row = $this->post('messages', ['sender_id' => $this->me(), 'recipient_id' => $partnerId, 'body' => '', 'kind' => $kind, 'media_path' => $path, 'duration' => $duration]);

        return $this->formatAll([$row])[0];
    }

    public function localMedia(string $messageId): ?string
    {
        return null;
    }

    /** Liens signés (1 h) pour tous les fichiers d'une liste de messages, en une seule requête. */
    private function formatAll(array $rows): array
    {
        $paths = array_values(array_filter(array_column($rows, 'media_path')));
        $urls = [];
        if ($paths) {
            $r = $this->data->rest()->post($this->data->baseUrl().'/storage/v1/object/sign/'.self::BUCKET, ['expiresIn' => 3600, 'paths' => $paths]);
            foreach ($r->successful() ? $r->json() : [] as $s) {
                if (! empty($s['signedURL'])) {
                    $urls[$s['path']] = $this->data->baseUrl().'/storage/v1'.$s['signedURL'];
                }
            }
        }

        return array_map(fn ($r) => $this->format($r, $urls), $rows);
    }

    private function format(array $r, array $urls): array
    {
        $me = $this->me();
        $at = Carbon::parse($r['created_at'])->setTimezone(config('terrain.timezone', 'UTC'));
        $msg = [
            'id' => $r['id'], 'cursor' => $r['created_at'], 'mine' => $r['sender_id'] === $me, 'kind' => $r['kind'] ?? 'text', 'text' => (string) ($r['body'] ?? ''),
            'media_url' => ! empty($r['media_path']) ? ($urls[$r['media_path']] ?? null) : null, 'duration' => $r['duration'] ?? null,
            'at' => $at->format('H:i'), 'day' => $at->format('Y-m-d'), 'ts' => $at->toIso8601String(), 'read' => $r['sender_id'] === $me && ! empty($r['read_at']),
        ];
        if ($msg['kind'] === 'call') {
            [$kind, $status, $caller] = array_pad(explode('|', $msg['text']), 3, '');
            $msg['call'] = ['kind' => $kind, 'status' => $status, 'outgoing' => $caller === $me];
            $msg['text'] = CallText::label($kind, $status, $caller === $me, $msg['duration']);
        }

        return $msg;
    }

    // ------------------------------------------------------------------ appels

    public function canCall(string $partnerId): array
    {
        $me = $this->me();
        if (! $me || $partnerId === $me || ! Str::isUuid($partnerId)) {
            return [false, 'Connecte-toi pour appeler.'];
        }
        $people = $this->data->persons([$me, $partnerId]);
        if (($people[$me]['minor'] ?? false) || ($people[$partnerId]['minor'] ?? false)) {
            return [false, CallText::MINOR];
        }
        if (! $this->get('messages', ['sender_id' => "eq.{$partnerId}", 'recipient_id' => "eq.{$me}", 'select' => 'id', 'limit' => 1])) {
            return [false, CallText::NO_REPLY];
        }

        return [true, ''];
    }

    public function startCall(string $partnerId, string $kind): array
    {
        $row = $this->post('calls', ['caller_id' => $this->me(), 'callee_id' => $partnerId, 'kind' => $kind === 'video' ? 'video' : 'audio']);

        return $this->formatCall($row);
    }

    public function incomingCall(): ?array
    {
        $me = $this->me();
        if (! $me) {
            return null;
        }
        $rows = $this->get('calls', ['callee_id' => "eq.{$me}", 'status' => 'eq.ringing', 'created_at' => 'gt.'.now()->subSeconds(45)->toIso8601String(),
            'order' => 'created_at.desc', 'limit' => 1]);

        return $rows ? $this->formatCall($rows[0]) : null;
    }

    public function call(string $callId): ?array
    {
        if (! Str::isUuid($callId)) {
            return null;
        }
        $rows = $this->get('calls', ['id' => "eq.{$callId}", 'limit' => 1]);
        if (! $rows) {
            return null;
        }
        $c = $rows[0];
        if ($c['status'] === 'ringing' && Carbon::parse($c['created_at'])->lt(now()->subSeconds(45))) {
            return $this->setCallStatus($callId, 'missed');
        }

        return $this->formatCall($c);
    }

    public function setCallStatus(string $callId, string $status): ?array
    {
        $me = $this->me();
        $rows = $this->get('calls', ['id' => "eq.{$callId}", 'limit' => 1]);
        if (! $rows) {
            return null;
        }
        $c = $rows[0];
        if ($status === 'ended' && $c['status'] === 'ringing') {
            $status = $c['caller_id'] === $me ? 'cancelled' : 'declined';
        }
        if (in_array($c['status'], ['ringing', 'accepted'], true)) {
            $this->data->rest()->withHeaders(['Prefer' => 'return=minimal'])
                ->patch($this->data->baseUrl().'/rest/v1/calls?'.http_build_query(['id' => "eq.{$callId}", 'status' => 'in.(ringing,accepted)']), ['status' => $status]);
        }
        $rows = $this->get('calls', ['id' => "eq.{$callId}", 'limit' => 1]);

        return $rows ? $this->formatCall($rows[0]) : null;
    }

    public function sendSignal(string $callId, string $type, string $payload): void
    {
        $this->data->rest()->withHeaders(['Prefer' => 'return=minimal'])
            ->post($this->data->baseUrl().'/rest/v1/call_signals', ['call_id' => $callId, 'sender_id' => $this->me(), 'type' => $type, 'payload' => $payload]);
    }

    public function signals(string $callId, ?string $afterId): array
    {
        $q = ['call_id' => "eq.{$callId}", 'sender_id' => 'neq.'.$this->me(), 'order' => 'id.asc', 'select' => 'id,type,payload'];
        if ($afterId) {
            $q['id'] = 'gt.'.(int) $afterId;
        }

        return array_map(fn ($s) => ['id' => (string) $s['id'], 'type' => $s['type'], 'payload' => $s['payload']], $this->get('call_signals', $q));
    }

    private function formatCall(array $c): array
    {
        $outgoing = $c['caller_id'] === $this->me();

        return [
            'id' => $c['id'], 'kind' => $c['kind'], 'status' => $c['status'], 'outgoing' => $outgoing,
            'partner' => $this->data->person($outgoing ? $c['callee_id'] : $c['caller_id']),
            'created_ts' => $c['created_at'], 'answered_ts' => $c['answered_at'] ?? null, 'fictional' => false,
        ];
    }
}
