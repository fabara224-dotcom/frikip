<?php

namespace App\Terrain\Messaging;

use Illuminate\Http\UploadedFile;

/**
 * Messagerie (textes, vocaux, photos) et appels audio/vidéo.
 * DemoMessenger = base locale du site (mode démo), SupabaseMessenger = tables Supabase (partagées avec l'app).
 *
 * message : id, mine, kind (text|voice|image|call), text, media_url, duration, at (HH:MM), day (AAAA-MM-JJ), ts, read
 * call    : id, kind (audio|video), status (ringing|accepted|declined|missed|ended|cancelled), outgoing, partner[], created_ts
 * signal  : id, type (offer|answer|ice), payload (texte JSON), mine
 */
interface Messenger
{
    /** Identifiant de contact de l'utilisateur connecté (null = pas connecté). */
    public function me(): ?string;

    /** @return list<array{partner: array, last: ?array, unread: int}> conversations, la plus récente d'abord */
    public function threads(): array;

    public function unreadTotal(): int;

    /** Messages d'une conversation (les messages reçus passent en « lus »). */
    public function conversation(string $partnerId): array;

    /** Nouveaux messages depuis $afterId + dernier de mes messages lu par l'autre. @return array{messages: list<array>, read_upto: ?string} */
    public function since(string $partnerId, ?string $afterId): array;

    /** false si un recruteur gratuit a déjà utilisé ses contacts */
    public function canContact(string $partnerId): bool;

    public function sendText(string $partnerId, string $text): array;

    /** @param string $kind voice|image */
    public function sendMedia(string $partnerId, string $kind, UploadedFile $file, ?int $duration = null): array;

    /** Chemin local d'un fichier joint (mode démo seulement), null si l'utilisateur n'y a pas accès. */
    public function localMedia(string $messageId): ?string;

    // ---- Appels
    /** @return array{0: bool, 1: string} [autorisé, raison si refusé] */
    public function canCall(string $partnerId): array;

    public function startCall(string $partnerId, string $kind): array;

    /** Appel qui sonne pour moi en ce moment. */
    public function incomingCall(): ?array;

    public function call(string $callId): ?array;

    /** @param string $status accepted|declined|ended|cancelled|missed */
    public function setCallStatus(string $callId, string $status): ?array;

    public function sendSignal(string $callId, string $type, string $payload): void;

    /** @return list<array> signaux de l'autre personne après $afterId */
    public function signals(string $callId, ?string $afterId): array;
}
