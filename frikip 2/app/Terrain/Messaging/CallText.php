<?php

namespace App\Terrain\Messaging;

/** Textes communs de la messagerie et des appels. */
class CallText
{
    public const MINOR = 'Les appels ne sont pas possibles avec un sportif mineur : écris-lui par message (son tuteur reçoit une copie).';

    public const NO_REPLY = "Tu pourras appeler cette personne quand elle t'aura répondu par message.";

    public static function label(string $kind, string $status, bool $outgoing, ?int $seconds): string
    {
        $type = $kind === 'video' ? 'Appel vidéo' : 'Appel vocal';

        return match ($status) {
            'ended', 'accepted' => $type.($seconds ? ' · '.self::duration($seconds) : ''),
            'missed' => $outgoing ? $type.' · pas de réponse' : $type.' manqué',
            'declined' => $outgoing ? $type.' · refusé' : $type.' refusé',
            'cancelled' => $outgoing ? $type.' · annulé' : $type.' manqué',
            default => $type,
        };
    }

    public static function duration(int $s): string
    {
        return $s < 60 ? "{$s} s" : intdiv($s, 60).' min'.($s % 60 ? ' '.($s % 60).' s' : '');
    }
}
