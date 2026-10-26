<?php

namespace App\Http\Middleware;

use App\Terrain\DataSource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pages réservées aux utilisateurs connectés. Option : rôle exigé (athlete ou recruiter). */
class TerrainAuth
{
    public function __construct(private DataSource $data)
    {
    }

    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $this->data->user();
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Connecte-toi pour continuer.', 'login' => route('login')], 401);
            }

            return redirect()->guest(route('login'))->with('status', 'Connecte-toi pour continuer.');
        }
        // Compte bloqué par la modération : déconnexion immédiate
        $sanction = app(\App\Terrain\Growth\Moderation::class)->sanctionOf($user['id'], $user['athlete_id'] ?? null);
        if ($sanction) {
            $this->data->signOut();
            $request->session()->forget('terrain.user');
            $rule = __(\App\Terrain\Growth\Algorithm::RULES[$sanction->rule ?? ''] ?? ($sanction->reason ?: 'Manquement aux règles'));
            $msg = $sanction->type === 'suspend_account'
                ? __('Ton compte est suspendu jusqu’au :d. Règle enfreinte : :rule. Si c’est une erreur, contacte le support.', ['d' => \Illuminate\Support\Carbon::parse($sanction->until)->format('d/m/Y'), 'rule' => $rule])
                : __('Ton compte est bloqué. Règle enfreinte : :rule. Si c’est une erreur, contacte le support.', ['rule' => $rule]);
            if ($request->expectsJson()) {
                return response()->json(['error' => $msg], 403);
            }

            return redirect()->route('support')->with('status', $msg);
        }
        app(\App\Terrain\Growth\Members::class)->touch();
        if ($role && $user['role'] !== $role) {
            abort(403, $role === 'recruiter' ? 'Réservé aux recruteurs.' : 'Réservé aux sportifs.');
        }

        return $next($request);
    }
}
