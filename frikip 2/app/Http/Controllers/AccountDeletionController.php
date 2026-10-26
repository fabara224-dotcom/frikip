<?php

namespace App\Http\Controllers;

use App\Models\DeletionRequest;
use App\Services\SupabaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Page « Supprimer mon compte » exigée par Google Play :
 *  - option 2 : connexion avec les identifiants de l'app, puis suppression immédiate ;
 *  - option 3 : demande sans connexion, enregistrée et traitée dans l'espace admin.
 */
class AccountDeletionController extends Controller
{
    public function __construct(private SupabaseService $supabase)
    {
    }

    public function show(Request $request): View
    {
        return view('pages.delete-account', [
            'webDeletion' => $this->supabase->enabled(),
            'connectedEmail' => $request->session()->get('deletion.email'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        abort_unless($this->supabase->enabled(), 404);

        $data = $request->validateWithBag('login', [
            'email' => ['required', 'email', 'max:200'],
            'password' => ['required', 'string', 'max:200'],
        ], [
            'email.required' => "Indiquez l'e-mail de votre compte.",
            'email.email' => "Cette adresse e-mail n'est pas valide.",
            'password.required' => 'Indiquez votre mot de passe.',
        ]);

        try {
            $session = $this->supabase->signIn($data['email'], $data['password']);
        } catch (Throwable $e) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Connexion impossible : vérifiez l'e-mail et le mot de passe, ou utilisez l'option 3."], 'login');
        }

        $request->session()->regenerate();
        $request->session()->put('deletion.token', $session['token']);
        $request->session()->put('deletion.email', $session['email']);

        return redirect()->to(route('deletion').'#web');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $token = $request->session()->get('deletion.token');
        abort_unless($token && $this->supabase->enabled(), 403);

        $request->validateWithBag(
            'confirm',
            ['confirmation' => ['required', 'string', function ($attribute, $value, $fail) {
                if (mb_strtoupper(trim($value)) !== 'SUPPRIMER') {
                    $fail('Écrivez SUPPRIMER pour confirmer.');
                }
            }]],
            ['confirmation.required' => 'Écrivez SUPPRIMER pour confirmer.'],
        );

        try {
            $this->supabase->deleteAccount($token);
        } catch (Throwable $e) {
            Log::error('Suppression de compte web échouée', ['error' => $e->getMessage()]);

            return redirect()->to(route('deletion').'#web')
                ->withErrors(['confirmation' => "La suppression a échoué. Réessayez dans quelques minutes ou utilisez l'option 3."], 'confirm');
        }

        $request->session()->forget(['deletion.token', 'deletion.email']);
        $request->session()->regenerate();

        return redirect()->to(route('deletion').'#web')
            ->with('deleted', 'Votre compte et vos données ont été supprimés. Les sauvegardes seront effacées sous 30 jours.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['deletion.token', 'deletion.email']);

        return redirect()->to(route('deletion').'#web');
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        // Champ piège invisible : les robots le remplissent, pas les humains.
        if ($request->filled('website')) {
            return redirect()->to(route('deletion').'#demande')->with('requested', true);
        }

        $data = $request->validate([
            'contact' => ['required', 'string', 'min:5', 'max:200'],
            'profile_name' => ['nullable', 'string', 'max:200'],
            'scope' => ['required', 'in:account,activity,videos'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'contact.required' => "Indiquez l'e-mail ou le numéro de téléphone du compte.",
            'contact.min' => "Indiquez l'e-mail ou le numéro de téléphone complet.",
        ]);

        $deletion = DeletionRequest::create($data + ['status' => 'nouvelle']);

        if ($to = config('terrain.notify_email')) {
            try {
                Mail::raw(
                    "Nouvelle demande de suppression n°{$deletion->id}\nContact : {$deletion->contact}\nPortée : {$deletion->targetLabel()}\n\nÀ traiter sous 7 jours dans l'espace admin : ".route('admin.deletions'),
                    fn ($m) => $m->to($to)->subject('['.config('terrain.app_name').'] Demande de suppression de compte'),
                );
            } catch (Throwable $e) {
                Log::warning('Alerte e-mail non envoyée', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->to(route('deletion').'#demande')->with('requested', true);
    }
}
