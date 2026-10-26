<?php

namespace App\Terrain\Messaging;

use App\Terrain\Growth\Notifier;
use App\Terrain\LocalData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Messagerie et appels RÉELS entre les comptes du site (sans Supabase).
 * Même stockage que la démo (tables demo_messages / demo_calls) dans une salle unique « reel »,
 * mais sans réponses automatiques : chaque message arrive à la vraie personne, qui est prévenue
 * (cloche, alerte dans la page, notification sur son téléphone, e-mail / SMS selon ses réglages).
 *
 * Règles :
 *  - on ne peut écrire qu'aux vrais comptes (les profils d'exemple ne reçoivent rien) ;
 *  - un sportif mineur ne peut être contacté en premier que par un recruteur vérifié (copie au tuteur par SMS si configuré) ;
 *  - un sportif peut écrire aux recruteurs, et aux autres sportifs majeurs s'il est majeur ;
 *  - on peut toujours répondre à quelqu'un qui nous a écrit.
 */
class LocalMessenger extends DemoMessenger
{
    public const ROOM = 'reel';

    public function __construct(protected \App\Terrain\DemoData $data)
    {
    }

    private function local(): LocalData
    {
        /** @var LocalData $d */
        $d = $this->data;

        return $d;
    }

    public function room(): string
    {
        return self::ROOM;
    }

    public function join(string $room): void
    {
    }

    public function joined(): bool
    {
        return true;
    }

    protected function seed(string $room): void
    {
    }

    protected function isLive(string $id): bool
    {
        return $this->local()->isRealContact($id);
    }

    protected function fakeReply(string $partnerId, string $kind): void
    {
    }

    /** Peut-on écrire à cette personne ? @return array{0: bool, 1: string} */
    public function reachable(string $partnerId): array
    {
        $me = $this->me();
        if (! $me) {
            return [false, 'Connecte-toi pour écrire.'];
        }
        if (! $this->isLive($partnerId)) {
            return [false, 'Ce profil est un exemple fictif : il ne reçoit pas de messages. Écris à un vrai membre.'];
        }
        // On peut toujours répondre à quelqu'un qui nous a écrit
        if (DB::table('demo_messages')->where('room', self::ROOM)->where('sender', $partnerId)->where('recipient', $me)->exists()) {
            return [true, ''];
        }
        $u = $this->data->user();
        $partner = $this->data->person($partnerId);
        $myAthlete = ! empty($u['athlete_id']) ? $this->data->athlete($u['athlete_id']) : null;
        $partnerIsAthlete = (bool) $this->data->athlete($partnerId);
        if ($partner['minor'] && ! ($u['role'] === 'recruiter' && ! empty($u['verified']))) {
            return [false, 'Ce sportif a moins de 18 ans : seuls les recruteurs vérifiés peuvent lui écrire en premier.'];
        }
        if ($u['role'] === 'athlete' && $partnerIsAthlete && (($myAthlete['age'] ?? 0) < 18)) {
            return [false, 'Les sportifs de moins de 18 ans peuvent écrire aux recruteurs, ou répondre aux messages reçus.'];
        }

        return [true, ''];
    }

    private function guard(string $partnerId): void
    {
        [$ok, $why] = $this->reachable($partnerId);
        if (! $ok) {
            throw new RuntimeException($why);
        }
    }

    public function sendText(string $partnerId, string $text): array
    {
        $this->guard($partnerId);
        $first = ! DB::table('demo_messages')->where('room', self::ROOM)->where('sender', $this->me())->where('recipient', $partnerId)->exists();
        $msg = parent::sendText($partnerId, $text);
        $this->copyToGuardian($partnerId, $first);

        return $msg;
    }

    public function sendMedia(string $partnerId, string $kind, UploadedFile $file, ?int $duration = null): array
    {
        $this->guard($partnerId);

        return parent::sendMedia($partnerId, $kind, $file, $duration);
    }

    public function canCall(string $partnerId): array
    {
        if (! $this->isLive($partnerId)) {
            return [false, 'Ce profil est un exemple fictif : il ne peut pas être appelé.'];
        }

        return parent::canCall($partnerId);
    }

    public function startCall(string $partnerId, string $kind): array
    {
        [$ok, $why] = $this->canCall($partnerId);
        if (! $ok) {
            throw new RuntimeException($why);
        }

        return parent::startCall($partnerId, $kind);
    }

    /** Premier message d'un recruteur à un mineur : le parent ou tuteur reçoit un SMS (si les SMS sont configurés). */
    private function copyToGuardian(string $partnerId, bool $first): void
    {
        if (! $first) {
            return;
        }
        $a = $this->data->athlete($partnerId);
        if (! $a || $a['age'] >= 18 || empty($a['guardian_phone'])) {
            return;
        }
        $u = $this->data->user();
        try {
            app(Notifier::class)->sms($a['guardian_phone'], config('terrain.app_name').' : '.$u['name'].' (recruteur vérifié) a écrit à '.$a['name'].'. Restez attentifs : un essai ne doit jamais être payant.');
        } catch (\Throwable) {
        }
    }
}
