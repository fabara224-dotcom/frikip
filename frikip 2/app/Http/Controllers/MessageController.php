<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Messaging\DemoMessenger;
use App\Terrain\Messaging\LocalMessenger;
use App\Terrain\Messaging\Messenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Messagerie entre recruteurs et sportifs : textes, vocaux, photos, accusés de lecture et alerte anti-arnaque. */
class MessageController extends Controller
{
    public const MONEY_WORDS = 'frais|argent|virement|payer|visa|passeport';

    private const NOT_ALLOWED = 'Message non envoyé. Seuls les recruteurs vérifiés peuvent écrire en premier à un sportif.';

    public function __construct(private DataSource $data, private Messenger $messenger)
    {
    }

    public function index(): View
    {
        $invite = null;

        return view('web.messages', ['threads' => $this->messenger->threads(), 'invite' => $invite]);
    }

    public function show(string $partner): View|RedirectResponse
    {
        if ($partner === $this->messenger->me()) {
            return redirect()->route('messages');
        }
        if (! $this->messenger->canContact($partner)) {
            return redirect()->route('premium', 'premium')->with('status', 'Tu as utilisé tes contacts gratuits. Passe Premium pour écrire à d’autres sportifs.');
        }
        // Vrais comptes : on ne peut pas écrire à un profil d'exemple, ni écrire en premier à un mineur sans être un recruteur vérifié
        $blocked = null;
        if ($this->messenger instanceof LocalMessenger) {
            [$ok, $why] = $this->messenger->reachable($partner);
            if (! $ok) {
                $blocked = $why;
            }
        }
        $conv = $this->messenger->conversation($partner);
        if ($blocked && ! $conv['messages']) {
            $to = $this->data->athlete($partner) ? route('athletes.show', $partner) : route('messages');

            return redirect($to)->with('status', __($blocked));
        }
        [$canCall, $callReason] = $this->messenger->canCall($partner);

        return view('web.thread', $conv + [
            'partnerId' => $partner, 'moneyWords' => self::MONEY_WORDS, 'canCall' => $canCall, 'callReason' => $callReason, 'blocked' => $blocked,
        ]);
    }

    public function store(Request $request, string $partner): RedirectResponse|JsonResponse
    {
        $text = trim($request->validate(['text' => ['required', 'string', 'max:2000']])['text']);
        if ($why = app(\App\Terrain\Growth\ContentGuard::class)->refusal(['text' => $text], 'message', $this->data->user()['id'] ?? null)) {
            return $request->expectsJson() ? response()->json(['error' => $why], 422) : back()->withErrors(['text' => $why]);
        }
        if (! $this->messenger->canContact($partner)) {
            return $request->expectsJson() ? response()->json(['error' => 'premium', 'url' => route('premium', 'premium')], 402) : redirect()->route('premium', 'premium');
        }
        try {
            $msg = $this->messenger->sendText($partner, $text);
            $this->notifyPartner($partner, \Illuminate\Support\Str::limit($text, 90));
        } catch (Throwable $e) {
            $why = $e instanceof \RuntimeException ? __($e->getMessage()) : self::NOT_ALLOWED;

            return $request->expectsJson() ? response()->json(['error' => $why], 403) : back()->withErrors(['text' => $why]);
        }

        return $request->expectsJson() ? response()->json(['message' => $msg]) : redirect()->route('messages.show', $partner);
    }

    /** Message vocal (enregistré dans le navigateur) ou photo. */
    public function upload(Request $request, string $partner): JsonResponse
    {
        $kind = $request->input('kind') === 'image' ? 'image' : 'voice';
        $rules = $kind === 'image'
            ? ['file' => ['required', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif']]
            : ['file' => ['required', 'file', 'max:6144', 'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/mpeg,audio/aac,audio/x-m4a,audio/wav,audio/x-wav,video/webm,video/mp4,video/ogg'],
                'duration' => ['nullable', 'integer', 'min:1', 'max:300']];
        $data = $request->validate($rules, [
            'file.max' => $kind === 'image' ? 'Photo trop lourde (8 Mo maximum).' : 'Vocal trop long.',
            'file.mimetypes' => $kind === 'image' ? 'Format de photo non pris en charge.' : "Format audio non pris en charge par ce navigateur.",
        ]);
        if (! $this->messenger->canContact($partner)) {
            return response()->json(['error' => 'premium', 'url' => route('premium', 'premium')], 402);
        }
        try {
            $msg = $this->messenger->sendMedia($partner, $kind, $data['file'], isset($data['duration']) ? (int) $data['duration'] : null);
            $this->notifyPartner($partner, $kind === 'image' ? '📷 Photo' : '🎤 Message vocal');
        } catch (Throwable $e) {
            return response()->json(['error' => $e instanceof \RuntimeException ? __($e->getMessage()) : self::NOT_ALLOWED], 403);
        }

        return response()->json(['message' => $msg]);
    }

    /** Nouveaux messages (toutes les 2-3 s quand la conversation est ouverte). */
    public function poll(Request $request, string $partner): JsonResponse
    {
        $data = $this->messenger->since($partner, $request->query('after') ?: null);

        return response()->json($data + ['unread' => $this->messenger->unreadTotal()]);
    }

    /** Fichier joint en mode démo (avec Supabase, les fichiers sont servis par des liens signés). */
    public function media(string $message): BinaryFileResponse
    {
        $path = $this->messenger->localMedia($message);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Démo : rejoindre la salle d'un autre appareil pour tester messages et appels à deux. */
    public function join(string $room, string $role): RedirectResponse
    {
        abort_unless($this->messenger instanceof DemoMessenger && ! $this->messenger instanceof LocalMessenger && in_array($role, ['athlete', 'recruiter'], true) && preg_match('/^[a-z0-9]{6,40}$/', $room), 404);
        $this->messenger->join($room);
        $this->data->demoLogin($role);

        return redirect()->route('messages.show', $role === 'athlete' ? 'r1' : 'a1')
            ->with('status', 'Tu as rejoint la démo comme '.($role === 'athlete' ? 'sportif' : 'recruteur').'. Les messages et appels arrivent maintenant sur l’autre appareil.');
    }

    /** Notification au destinataire (cloche + SMS/WhatsApp selon ses préférences, au plus 1 toutes les 15 min). */
    private function notifyPartner(string $partner, string $preview): void
    {
        $me = $this->data->user();
        app(\App\Terrain\Growth\Notifier::class)->send($partner, 'message', 'Nouveau message de '.($me['name'] ?? 'un membre'), $preview,
            route('messages.show', $this->messenger->me() ?? ''));
    }
}
