<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Abonnement d'un appareil (téléphone, ordinateur) aux notifications du site. */
class PushController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    public function subscribe(Request $request): JsonResponse
    {
        $d = $request->validate([
            'endpoint' => ['required', 'url', 'starts_with:https://', 'max:600'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        abort_unless(WebPush::configured(), 503);
        DB::table('push_subscriptions')->updateOrInsert(['endpoint' => $d['endpoint']], [
            'user_key' => $this->data->user()['id'], 'p256dh' => $d['keys']['p256dh'], 'auth' => $d['keys']['auth'],
            'user_agent' => Str::limit((string) $request->userAgent(), 190, ''), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        DB::table('push_subscriptions')->where('endpoint', $endpoint)->where('user_key', $this->data->user()['id'])->delete();

        return response()->json(['ok' => true]);
    }

    /** Notification d'essai sur les appareils de l'utilisateur. */
    public function test(WebPush $push): JsonResponse
    {
        $n = $push->sendTo($this->data->user()['id'], [
            'title' => __('Les notifications fonctionnent ✅'), 'body' => __('Tu seras prévenu ici des messages et des appels.'),
            'url' => route('settings'), 'type' => 'test', 'tag' => 'test',
        ]);

        return response()->json(['sent' => $n]);
    }
}
