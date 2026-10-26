<?php

namespace App\Http\Middleware;

use App\Terrain\Growth\AdminAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Espace admin : compte admin connecté, droits selon le rôle, et journal de chaque action. */
class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AdminAuth::hasAny()) {
            return redirect()->route('admin.setup');
        }
        $auth = app(AdminAuth::class);
        $admin = $auth->current();
        if (! $admin) {
            session()->put('url.intended', $request->fullUrl());

            return redirect()->route('admin.login');
        }
        if (! AdminAuth::allows($admin, $request->route()?->getName())) {
            abort(403, 'Cette page est réservée aux administrateurs.');
        }
        view()->share('admin', $admin);

        $response = $next($request);
        // Journal : chaque action (formulaire envoyé) est gardée avec son auteur, sans possibilité de la modifier
        if ($request->isMethod('POST') && $response->getStatusCode() < 400 && ! $request->routeIs('admin.logout')) {
            try {
                $input = collect($request->except(['_token', 'password', 'password_confirmation', 'current_password']))
                    ->map(fn ($v) => is_string($v) ? \Illuminate\Support\Str::limit($v, 300) : $v)->all();
                \Illuminate\Support\Facades\DB::table('admin_log')->insert([
                    'action' => (string) ($request->route()?->getName() ?? $request->path()),
                    'target' => \Illuminate\Support\Str::limit(implode(' ', array_map('strval', array_filter($request->route()?->parameters() ?? [], 'is_scalar'))), 120, ''),
                    'details' => json_encode($input + ($request->attributes->get('admin_log') ?? []), JSON_UNESCAPED_UNICODE),
                    'ip' => $request->ip(), 'admin_id' => $admin->id, 'admin_name' => $admin->name, 'created_at' => now(),
                ]);
            } catch (\Throwable) {
            }
        }
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
