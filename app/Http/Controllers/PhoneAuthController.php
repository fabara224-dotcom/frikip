<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\DemoData;
use App\Terrain\Growth\Members;
use App\Terrain\Growth\Notifier;
use App\Terrain\SportFields;
use App\Terrain\SupabaseData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Connexion et inscription en 30 secondes avec un numéro de téléphone et un code reçu par SMS (sans e-mail).
 * Avec Supabase : Supabase envoie et vérifie le code (Authentication > Providers > Phone).
 * Sans Supabase : code envoyé par Twilio si configuré, sinon affiché à l'écran (démo).
 */
class PhoneAuthController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    public function show(Request $request): View
    {
        if ($request->boolean('changer')) {
            session()->forget(['phone_auth.phone', 'phone_auth.demo_code']);
        }

        return view('web.phone-login', ['phone' => session('phone_auth.phone'), 'demoCode' => session('phone_auth.demo_code'), 'withPassword' => $this->usesPassword()]);
    }

    /** Sans service SMS (ni Twilio, ni Supabase) sur le vrai site : connexion par numéro + mot de passe. */
    private function usesPassword(): bool
    {
        return $this->data instanceof \App\Terrain\LocalData && ! Notifier::smsConfigured() && ! app()->environment('local');
    }

    /** Connexion ou création de compte avec numéro + mot de passe. */
    public function password(Request $request): RedirectResponse
    {
        if (! $this->usesPassword()) {
            return redirect()->route('phone.login');
        }
        $request->validate([
            'phone' => ['required', 'string', 'max:30', function ($a, $v, $fail) {
                if (strlen(preg_replace('/\D/', '', (string) $v)) < 8) {
                    $fail('Numéro trop court.');
                }
            }],
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ], ['phone.required' => 'Entre ton numéro de téléphone.', 'password.required' => 'Entre ton mot de passe.', 'password.min' => 'Le mot de passe doit faire au moins 6 caractères.']);
        $country = isset(\App\Terrain\Region::COUNTRIES[$request->input('country')]) ? $request->input('country') : \App\Terrain\Region::viewer();
        $phone = Notifier::e164($request->input('phone'), $country);
        try {
            $user = $this->data->signInByPhonePassword($phone, (string) $request->input('password'));
        } catch (Throwable $e) {
            return back()->withInput($request->except('password'))->withErrors(['password' => $e->getMessage()]);
        }
        if ($user) {
            session()->forget(['phone_auth.phone', 'phone_auth.verified', 'phone_auth.password_hash']);

            return redirect()->intended(route('feed'));
        }
        // Numéro inconnu : on crée le compte (dernière étape : profil et confirmation du mot de passe)
        session(['phone_auth.country' => $country, 'phone_auth.phone' => $phone, 'phone_auth.verified' => $phone,
            'phone_auth.password_hash' => Hash::make((string) $request->input('password'))]);

        return redirect()->route('phone.profile');
    }

    public function send(Request $request, Notifier $notifier): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:30', function ($a, $v, $fail) {
            if (strlen(preg_replace('/\D/', '', (string) $v)) < 8) {
                $fail('Numéro trop court.');
            }
        }]], ['phone.required' => 'Entre ton numéro de téléphone.']);
        $country = isset(\App\Terrain\Region::COUNTRIES[$request->input('country')]) ? $request->input('country') : \App\Terrain\Region::viewer();
        $phone = Notifier::e164($request->input('phone'), $country);
        session(['phone_auth.country' => $country]);
        session(['phone_auth.phone' => $phone]);
        session()->forget('phone_auth.demo_code');

        if ($this->data instanceof SupabaseData) {
            try {
                $this->data->phoneOtp($phone);
            } catch (Throwable $e) {
                return back()->withErrors(['phone' => "SMS non envoyé : active la connexion par téléphone dans Supabase (Authentication > Providers > Phone)."]);
            }

            return redirect()->route('phone.login')->with('status', 'Code envoyé par SMS au '.$phone.'.');
        }

        $code = (string) random_int(100000, 999999);
        DB::table('phone_codes')->where('phone', $phone)->delete();
        DB::table('phone_codes')->insert(['phone' => $phone, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);
        if (Notifier::smsConfigured() && $notifier->sms($phone, "Ton code ".config('terrain.app_name')." : {$code} (valable 10 min). Ne le donne à personne.")) {
            return redirect()->route('phone.login')->with('status', 'Code envoyé par SMS au '.$phone.'.');
        }
        // Pas de SMS configuré : le code n'est affiché à l'écran qu'en démo ou sur l'ordinateur de développement
        if ($this->data->isDemo() || app()->environment('local')) {
            session(['phone_auth.demo_code' => $code]);

            return redirect()->route('phone.login');
        }
        DB::table('phone_codes')->where('phone', $phone)->delete();
        session()->forget('phone_auth.phone');

        return back()->withErrors(['phone' => 'La connexion par SMS n’est pas encore disponible. Inscris-toi avec ton e-mail.']);
    }

    public function verify(Request $request): RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'Le code contient 6 chiffres.'])['code'];
        $phone = session('phone_auth.phone');
        if (! $phone) {
            return redirect()->route('phone.login');
        }

        if ($this->data instanceof SupabaseData) {
            try {
                $user = $this->data->phoneVerify($phone, $code);
            } catch (Throwable) {
                return back()->withErrors(['code' => 'Code incorrect ou expiré.']);
            }
            if (! empty($user['incomplete'])) {
                return redirect()->route('phone.profile');
            }
            app(Members::class)->updatePrefs(['phone' => $phone]);

            return redirect()->intended(route('feed'));
        }

        $row = DB::table('phone_codes')->where('phone', $phone)->orderByDesc('id')->first();
        if (! $row || now()->gt($row->expires_at) || $row->attempts >= 5) {
            return back()->withErrors(['code' => 'Code expiré. Demande un nouveau code.']);
        }
        if (! Hash::check($code, $row->code_hash)) {
            DB::table('phone_codes')->where('id', $row->id)->increment('attempts');

            return back()->withErrors(['code' => 'Code incorrect.']);
        }
        DB::table('phone_codes')->where('phone', $phone)->delete();
        session()->forget('phone_auth.demo_code');
        // Vrais comptes : ce numéro a déjà un compte → connexion directe
        if ($this->data instanceof \App\Terrain\LocalData && $this->data->signInByPhone($phone)) {
            session()->forget(['phone_auth.phone', 'phone_auth.verified']);

            return redirect()->intended(route('feed'));
        }
        session(['phone_auth.verified' => $phone]);

        return redirect()->route('phone.profile');
    }

    public function profile(): View|RedirectResponse
    {
        if (! session('phone_auth.verified') && ! session('terrain.pending_phone_user')) {
            return redirect()->route('phone.login');
        }

        return view('web.phone-profile', ['sports' => SportFields::sports(), 'countries' => SportFields::COUNTRIES, 'phone' => session('phone_auth.phone')]);
    }

    public function complete(Request $request): RedirectResponse
    {
        if (! session('phone_auth.verified') && ! session('terrain.pending_phone_user')) {
            return redirect()->route('phone.login');
        }
        $minAge = (int) config('terrain.min_age', 13);
        $athlete = $request->input('role') === 'athlete';
        $data = $request->validate([
            'role' => ['required', Rule::in(['athlete', 'recruiter'])],
            'name' => ['required', 'string', 'max:120'],
            'sport' => [$athlete ? 'required' : 'nullable', Rule::in(SportFields::sports())],
            'age' => [$athlete ? 'required' : 'nullable', 'integer', "min:{$minAge}", 'max:60'],
            'country' => [$athlete ? 'required' : 'nullable', Rule::in(SportFields::COUNTRIES)],
            'city' => ['nullable', 'string', 'max:120'],
            'guardian_phone' => [$athlete && (int) $request->input('age') < 18 ? 'required' : 'nullable', 'string', 'max:30'],
            'terms' => ['accepted'],
            'password_confirmation' => [session('phone_auth.password_hash') ? 'required' : 'nullable', 'string', function ($a, $v, $fail) {
                if (session('phone_auth.password_hash') && ! Hash::check((string) $v, session('phone_auth.password_hash'))) {
                    $fail('Les deux mots de passe ne sont pas identiques.');
                }
            }],
        ], ['password_confirmation.required' => 'Retape ton mot de passe.', 'age.min' => "Il faut avoir au moins {$minAge} ans.", 'guardian_phone.required' => 'Moins de 18 ans : le téléphone d’un parent ou tuteur est obligatoire.', 'terms.accepted' => 'Accepte les conditions pour continuer.']);
        app(\App\Terrain\Growth\ContentGuard::class)->enforce(['name' => $data['name'], 'city' => $data['city'] ?? null], 'profile');
        $phone = session('phone_auth.phone');
        $country = $data['country'] ?? session('phone_auth.country') ?? \App\Terrain\Region::viewer();
        $profile = ['name' => $data['name'], 'sport' => $data['sport'] ?? null, 'age' => $data['age'] ?? null, 'country' => $country,
            'city' => $data['city'] ?? null, 'guardian_phone' => $data['guardian_phone'] ?? null, 'fields' => [], 'phone' => $phone,
            'password_hash' => session('phone_auth.password_hash')];
        try {
            if ($this->data instanceof SupabaseData) {
                $this->data->phoneComplete($data['role'], $profile);
            } elseif ($this->data instanceof DemoData) {
                $this->data->signUp(preg_replace('/\D/', '', (string) $phone).'@telephone.local', bin2hex(random_bytes(8)), $data['role'], $profile);
            }
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['name' => 'Inscription impossible : '.$e->getMessage()]);
        }
        session()->forget(['phone_auth.verified', 'phone_auth.password_hash']);
        app(Members::class)->touch();
        app(Members::class)->updatePrefs(['phone' => $phone, 'notify_sms' => true]);
        app(Members::class)->setCountry($country);

        return redirect()->route($data['role'] === 'athlete' ? 'publish' : 'search')->with('status', 'Bienvenue ! Ton compte est créé.');
    }
}
