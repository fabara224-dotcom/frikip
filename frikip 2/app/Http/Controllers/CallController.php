<?php

namespace App\Http\Controllers;

use App\Terrain\Messaging\Messenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Appels audio et vidéo (WebRTC, d'appareil à appareil). Le site ne transporte ni le son ni l'image :
 * il échange seulement les messages de connexion (« signalisation »). Voir config/terrain.php (ice_servers).
 */
class CallController extends Controller
{
    public function __construct(private Messenger $messenger)
    {
    }

    public function start(Request $request, string $partner): JsonResponse|RedirectResponse
    {
        $kind = $request->input('kind') === 'video' ? 'video' : 'audio';
        [$ok, $reason] = $this->messenger->canCall($partner);
        if (! $ok) {
            return $request->expectsJson() ? response()->json(['error' => $reason], 403) : back()->with('status', $reason);
        }
        try {
            $call = $this->messenger->startCall($partner, $kind);
        } catch (Throwable $e) {
            $msg = "L'appel n'a pas pu démarrer.";

            return $request->expectsJson() ? response()->json(['error' => $msg], 500) : back()->with('status', $msg);
        }
        $url = route('calls.show', $call['id']);
        app(\App\Terrain\Growth\Notifier::class)->send($partner, 'call', ($kind === 'video' ? 'Appel vidéo' : 'Appel').' de '.(app(\App\Terrain\DataSource::class)->user()['name'] ?? 'un membre'),
            null, route('calls.show', [$call['id'], 'repondre' => 1]));

        return $request->expectsJson() ? response()->json(['call' => $call, 'url' => $url]) : redirect($url);
    }

    public function show(Request $request, string $call): View
    {
        $c = $this->messenger->call($call);
        abort_unless($c, 404);

        return view('web.call', [
            'call' => $c, 'answer' => $request->boolean('repondre'),
            'iceServers' => config('terrain.ice_servers'),
        ]);
    }

    /** État de l'appel + signaux reçus (appelé chaque seconde pendant la connexion). */
    public function state(Request $request, string $call): JsonResponse
    {
        $c = $this->messenger->call($call);
        abort_unless($c, 404);

        return response()->json(['call' => $c, 'signals' => $this->messenger->signals($call, $request->query('after') ?: null)]);
    }

    public function signal(Request $request, string $call): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'in:offer,answer,ice'], 'payload' => ['required', 'string', 'max:20000']]);
        abort_unless($this->messenger->call($call), 404);
        $this->messenger->sendSignal($call, $data['type'], $data['payload']);

        return response()->json(['ok' => true]);
    }

    public function status(Request $request, string $call): JsonResponse
    {
        $status = $request->validate(['status' => ['required', 'in:accepted,declined,ended,cancelled']])['status'];
        $c = $this->messenger->setCallStatus($call, $status);
        abort_unless($c, 404);
        if ($c['outgoing'] && in_array($c['status'], ['cancelled', 'missed'], true)) {
            app(\App\Terrain\Growth\Notifier::class)->send($c['partner']['id'], 'call_missed', ($c['kind'] === 'video' ? 'Appel vidéo' : 'Appel').' manqué de '.(app(\App\Terrain\DataSource::class)->user()['name'] ?? 'un membre'),
                null, route('messages.show', $this->messenger->me() ?? ''));
        }

        return response()->json(['call' => $c]);
    }

    /** Appel entrant (vérifié toutes les quelques secondes sur toutes les pages) + nombre de messages non lus. */
    public function incoming(Request $request): JsonResponse
    {
        try {
            $c = $this->messenger->incomingCall();
            $key = app(\App\Terrain\DataSource::class)->user()['id'] ?? null;
            // Dernière notification arrivée depuis le dernier passage (message, réponse, offre…) : alerte dans la page
            $after = (int) $request->query('apres', -1);   // -1 = premier passage : on note seulement où on en est
            $latest = $key ? \Illuminate\Support\Facades\DB::table('notifications_feed')->where('user_key', $key)->whereNull('read_at')
                ->where('id', '>', $after)->orderByDesc('id')->first(['id', 'type', 'title', 'body', 'url']) : null;
            $max = $key ? (int) \Illuminate\Support\Facades\DB::table('notifications_feed')->where('user_key', $key)->max('id') : 0;

            return response()->json(['call' => $c, 'url' => $c ? route('calls.show', [$c['id'], 'repondre' => 1]) : null, 'unread' => $this->messenger->unreadTotal(),
                'bell' => app(\App\Terrain\Growth\Notifier::class)->unread($key), 'notif' => $after >= 0 ? $latest : null, 'notif_max' => $max]);
        } catch (Throwable) {
            return response()->json(['call' => null, 'url' => null, 'unread' => 0]);
        }
    }
}
