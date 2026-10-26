<?php

namespace App\Http\Controllers;

use App\Models\DeletionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace admin minimal : liste et suivi des demandes de suppression.
 * Protégé par le mot de passe TERRAIN_ADMIN_PASSWORD du fichier .env.
 */
class AdminController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (! \App\Terrain\Growth\AdminAuth::hasAny()) {
            return redirect()->route('admin.setup');
        }
        if (app(\App\Terrain\Growth\AdminAuth::class)->current()) {
            return redirect()->route('admin.index');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']],
            ['email.required' => 'Entre ton identifiant (e-mail).', 'password.required' => 'Entre ton mot de passe.']);
        $request->session()->regenerate();
        if (! app(\App\Terrain\Growth\AdminAuth::class)->attempt($data['email'], $data['password'], (string) $request->ip())) {
            return back()->withInput(['email' => $data['email']])->withErrors(['password' => 'Identifiant ou mot de passe incorrect.']);
        }

        return redirect()->intended(route('admin.index'));
    }

    /** Premier lancement : création du compte administrateur principal. */
    public function setupForm(): View|RedirectResponse
    {
        if (\App\Terrain\Growth\AdminAuth::hasAny()) {
            return redirect()->route('admin.login');
        }

        return view('admin.setup', ['needsKey' => (string) config('terrain.admin_password') !== '']);
    }

    public function setup(Request $request): RedirectResponse
    {
        abort_if(\App\Terrain\Growth\AdminAuth::hasAny(), 403);
        $key = (string) config('terrain.admin_password');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:10', 'confirmed'], 'setup_key' => [$key !== '' ? 'required' : 'nullable', 'string'],
        ], ['password.min' => 'Choisis un mot de passe d’au moins 10 caractères.', 'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.']);
        if ($key !== '' && ! hash_equals($key, (string) $data['setup_key'])) {
            return back()->withInput($request->except(['password', 'password_confirmation', 'setup_key']))->withErrors(['setup_key' => 'Code d’installation incorrect (TERRAIN_ADMIN_PASSWORD du fichier .env).']);
        }
        $auth = app(\App\Terrain\Growth\AdminAuth::class);
        $auth->create($data['name'], $data['email'], $data['password'], 'admin');
        $request->session()->regenerate();
        $auth->attempt($data['email'], $data['password'], (string) $request->ip());

        return redirect()->route('admin.index')->with('saved', 'Compte administrateur créé. Bienvenue !');
    }

    public function index(Request $request): View
    {
        $status = $request->query('statut');
        $requests = DeletionRequest::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'nouvelle' then 0 when 'verification' then 1 else 2 end")
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => DeletionRequest::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function update(Request $request, DeletionRequest $deletionRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:nouvelle,verification,terminee,refusee'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $deletionRequest->status = $data['status'];
        $deletionRequest->admin_note = $data['admin_note'] ?? $deletionRequest->admin_note;
        $deletionRequest->done_at = in_array($data['status'], ['terminee', 'refusee'], true) ? now() : null;
        $deletionRequest->save();

        return back()->with('saved', "Demande n°{$deletionRequest->id} mise à jour.");
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['admin.id', 'terrain_admin']);
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }
}
