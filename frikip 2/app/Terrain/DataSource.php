<?php

namespace App\Terrain;

/**
 * Accès aux données de Terrain, partagé avec l'app Flutter.
 * DemoData = données d'exemple (aucune configuration), SupabaseData = vraies données.
 *
 * Formats (tableaux PHP) :
 *  athlete : id, name, sport, age, country, city, height, weight, level, club, availability, verified, score, fields[], videos[]
 *  video   : id, athlete_id, title, caption, duration, url, views, likes, liked, comments
 *  match   : id, sport, competition, home, away, hs, as, status (live|finished|upcoming), minute, date_label, result,
 *            events[], stats[], ratings[], candidates[], votes[], my_vote
 *  offer   : id, organization, org_type, sport, title, place, deadline, criteria, verified, mine, candidates[], applied
 *  user    : id, role (athlete|recruiter), email, name, athlete_id, premium, verified
 */
interface DataSource
{
    public function isDemo(): bool;

    // ---- Comptes
    /** @return array<string, mixed> utilisateur connecté (sans jeton) */
    public function signIn(string $email, string $password): array;

    /** @param array<string, mixed> $profile */
    public function signUp(string $email, string $password, string $role, array $profile): array;

    public function signOut(): void;

    public function user(): ?array;

    public function deleteAccount(): void;

    // ---- Sportifs et vidéos
    /** @param array<string, mixed> $filters q, sport, country, age, verified, sort */
    public function athletes(array $filters = []): array;

    public function athlete(string $id): ?array;

    /** @return list<array{athlete: array, video: array}> */
    public function feed(string $sport = 'Tous'): array;

    public function toggleLike(string $videoId): array;

    /**
     * Commentaires bruts d'une vidéo (mis en forme ensuite par Growth\Engagement::present).
     *
     * @return list<array{id: string, parent_id: ?string, user_key: string, author: string, role: ?string, athlete_id: ?string, text: string, created_at: string}>
     */
    public function comments(string $videoId): array;

    /** Ajoute un commentaire (ou une réponse si $parentId) et le renvoie au même format que comments(). */
    public function addComment(string $videoId, string $text, ?string $parentId = null): array;

    /** Un commentaire avec sa vidéo : ['id', 'video_id', 'user_key', 'parent_id'] */
    public function comment(string $commentId): ?array;

    /** Supprime son propre commentaire. */
    public function deleteComment(string $commentId): bool;

    /** Identifiant du sportif qui a publié la vidéo. */
    public function videoOwner(string $videoId): ?string;

    public function toggleFollow(string $athleteId): bool;

    public function following(): array;

    /** Publie une vidéo et renvoie son identifiant. */
    public function publishVideo(array $meta, ?\Illuminate\Http\UploadedFile $file): ?string;

    // ---- Matchs
    public function matches(): array;

    public function match(string $id): ?array;

    public function vote(string $matchId, int $index): void;

    public function standings(): array;

    public function organizerScore(string $matchId, string $side, int $points): void;

    public function organizerStatus(string $matchId, string $status): void;

    // ---- Offres
    public function offers(): array;

    public function createOffer(array $data): void;

    public function apply(string $offerId, ?string $videoId, string $message): void;

    // ---- Messages
    /**
     * Fiche courte d'un interlocuteur (messagerie, appels).
     * @return array{id: string, name: string, sport: string, verified: bool, minor: bool}
     */
    public function person(string $contactId): array;

    // ---- Compte
    public function activate(string $product): void;

    public function report(string $targetType, string $targetId, string $reason): void;
}
