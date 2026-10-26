<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/** Tableau de bord (sportif), compte (recruteur), paramètres, paiement simulé, suppression du compte. */
class MeController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    public function show(): View
    {
        $me = $this->data->user();
        if ($me['role'] === 'recruiter') {
            $favorites = array_values(array_filter(array_map(fn ($id) => $this->data->athlete($id), $this->data->following())));
            $mine = array_values(array_filter($this->data->offers(), fn ($o) => $o['mine']));

            return view('web.account', ['favorites' => $favorites, 'offersCount' => count($mine)]);
        }

        $a = $me['athlete_id'] ? $this->data->athlete($me['athlete_id']) : null;
        $a = $a ?: null;
        $checklist = $a ? [
            'Vidéo de présentation publiée' => count($a['videos']) > 0,
            'Au moins 3 compilations' => count($a['videos']) >= 3,
            'Fiche sport complète' => count(array_filter($a['fields'], fn ($v) => $v !== '–')) >= 3,
            'Identité vérifiée' => $a['verified'],
            'Disponibilité indiquée' => (string) $a['availability'] !== '',
        ] : [];
        // Vues réelles du profil sur 7 jours (table profile_views du site)
        $views = array_slice(array_values(app(\App\Terrain\Growth\Insights::class)->summary($a)['days']), -7);

        return view('web.dashboard', ['a' => $a, 'checklist' => $checklist, 'views' => $views]);
    }

    public function settings(): View
    {
        return view('web.settings');
    }

    public function report(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'target_type' => ['nullable', 'in:app,profile,video,message'],
            'target_id' => ['nullable', 'string', 'max:64'],
        ], ['reason.required' => 'Décris le problème.']);
        try {
            $this->data->report($data['target_type'] ?? 'app', $data['target_id'] ?? '', $data['reason']);
        } catch (Throwable $e) {
            report($e);
        }
        // File d'attente de l'équipe de modération (espace admin > Signalements)
        try {
            \Illuminate\Support\Facades\DB::table('reports')->insert([
                'reporter_key' => $this->data->user()['id'] ?? null, 'target_type' => $data['target_type'] ?? 'app',
                'target_id' => $data['target_id'] ?? null, 'reason' => $data['reason'], 'status' => 'nouveau', 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
        app(\App\Terrain\Growth\ContentGuard::class)->onReport($data['target_type'] ?? 'app', $data['target_id'] ?? null);
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', "Signalement envoyé à l'équipe de modération.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', function ($attr, $value, $fail) {
            if (mb_strtoupper(trim($value)) !== 'SUPPRIMER') {
                $fail('Écris SUPPRIMER pour confirmer.');
            }
        }]], ['confirmation.required' => 'Écris SUPPRIMER pour confirmer.']);

        try {
            $this->data->deleteAccount();
        } catch (Throwable $e) {
            return back()->withErrors(['confirmation' => 'La suppression a échoué. Réessaie ou écris au support.']);
        }
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Ton compte a été supprimé.');
    }
}
