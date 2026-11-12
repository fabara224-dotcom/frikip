<?php

namespace App\Http\Controllers;

use App\Terrain\ConfirmEmailException;
use App\Terrain\DataSource;
use App\Terrain\Growth\ContentGuard;
use App\Terrain\Growth\Members;
use App\Terrain\LocalData;
use App\Terrain\Region;
use App\Terrain\Signup;
use App\Terrain\SocialLogin;
use App\Terrain\SportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Création de compte en 3 étapes :
 *  1. Qui es-tu ? (joueur, proche, supporter, club, recruteur, agent, autre acteur du foot)
 *  2. Comment ? (Google, Facebook, TikTok, numéro de téléphone, e-mail)
 *  3. Ton profil (questions propres à ton type de compte)
 */
class SignupController extends Controller
{
    /** Anciennes valeurs « role » du formulaire (et de l'app) → nouveaux types. */
    private const LEGACY = ['athlete' => 'joueur', 'recruiter' => 'recruteur', 'fan' => 'supporter', 'proche' => 'proche'];

    public function __construct(private DataSource $data)
    {
    }

    private function viewData(?string $type): array
    {
        return [
            'types' => Signup::TYPES, 'type' => Signup::valid($type) ? $type : null,
            'professions' => Signup::PROFESSIONS, 'clubTypes' => Signup::CLUB_TYPES, 'countries' => SportFields::COUNTRIES,
            'football' => SportFields::FIELDS['Football'], 'providers' => SocialLogin::providers(),
            'smsReady' => \App\Terrain\Growth\Notifier::smsConfigured() || $this->data->isDemo() || app()->environment('local'),
        ];
    }

    private function typeFrom(Request $request): ?string
    {
        $t = (string) $request->input('type', '');
        if (! Signup::valid($t) && $request->filled('role')) {
            $t = self::LEGACY[(string) $request->input('role')] ?? '';
        }

        return Signup::valid($t) ? $t : null;
    }

    // ------------------------------------------------------------------ inscription par e-mail

    public function show(Request $request): View
    {
        $type = old('type', $this->typeFrom($request) ?? session('signup.type'));

        return view('web.register', $this->viewData($type));
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->typeFrom($request);
        if (! $type) {
            return back()->withInput($request->except('password'))->withErrors(['type' => 'Choisis qui tu es (étape 1).']);
        }
        $request->merge(['type' => $type]);
        $d = $request->validate(Signup::rules($type, true, (int) $request->input('age')), Signup::messages());
        $this->guard($d);
        try {
            $this->data->signUp($d['email'], $d['password'], Signup::role($type), Signup::profile($type, $d));
        } catch (ConfirmEmailException $e) {
            return redirect()->route('login')->with('status', $e->getMessage());
        } catch (Throwable $e) {
            return back()->withInput($request->except('password'))->withErrors(['email' => 'Inscription impossible : '.$e->getMessage()]);
        }
        $this->afterSignup($d);

        return redirect()->route(Signup::landing($type))->with('status', Signup::welcome($type));
    }

    /** Étape 1 → téléphone : on garde le type choisi pendant la vérification du code. */
    public function viaPhone(Request $request): RedirectResponse
    {
        if ($type = $this->typeFrom($request)) {
            session(['signup.type' => $type]);
        }

        return redirect()->route('phone.login');
    }

    // ------------------------------------------------------------------ Google, Facebook, TikTok

    public function social(Request $request, string $provider): RedirectResponse
    {
        if (! SocialLogin::configured($provider)) {
            return redirect()->route('register')->withErrors(['type' => __('La connexion avec :p n’est pas encore activée. Utilise ton numéro de téléphone ou ton e-mail.', ['p' => SocialLogin::NAMES[$provider] ?? $provider])]);
        }
        if ($type = $this->typeFrom($request)) {
            session(['signup.type' => $type]);
        }
        $state = Str::random(40);
        session(['signup.state' => $state, 'signup.provider' => $provider]);

        return redirect()->away(app(SocialLogin::class)->authUrl($provider, $state));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $name = SocialLogin::NAMES[$provider] ?? $provider;
        if (! SocialLogin::configured($provider)) {
            return redirect()->route('register');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('register')->withErrors(['type' => __('Connexion avec :p annulée. Tu peux réessayer ou choisir une autre façon.', ['p' => $name])]);
        }
        if (! hash_equals((string) session('signup.state'), (string) $request->query('state')) || session('signup.provider') !== $provider) {
            return redirect()->route('register')->withErrors(['type' => __('La connexion a expiré. Recommence, s’il te plaît.')]);
        }
        session()->forget(['signup.state', 'signup.provider']);
        try {
            $u = app(SocialLogin::class)->user($provider, (string) $request->query('code'));
        } catch (Throwable $e) {
            Log::warning("Connexion {$provider} impossible", ['error' => $e->getMessage()]);

            return redirect()->route('register')->withErrors(['type' => __(':p ne répond pas pour le moment. Réessaie ou utilise ton numéro de téléphone.', ['p' => $name])]);
        }
        if ($u['id'] === '' || ! $this->data instanceof LocalData) {
            return redirect()->route('register')->withErrors(['type' => __('Connexion avec :p impossible.', ['p' => $name])]);
        }

        // Déjà relié à un compte FootPro : connexion directe
        $linked = DB::table('social_accounts')->where('provider', $provider)->where('provider_id', $u['id'])->value('account_id');
        if ($linked && $this->data->signInById($linked)) {
            return redirect()->intended(route('feed'));
        }
        // Même e-mail (vérifié par Google ou Facebook) qu'un compte existant : on relie et on connecte
        if ($u['email'] && $u['verified']) {
            $acc = DB::table('accounts')->where('email', Str::lower($u['email']))->value('id');
            if ($acc) {
                $this->link($provider, $u, $acc);
                $this->data->signInById($acc);

                return redirect()->intended(route('feed'))->with('status', __('Ton compte est maintenant relié à :p.', ['p' => $name]));
            }
        }
        // Nouveau membre : dernière étape (son profil)
        session(['signup.social' => ['provider' => $provider] + $u]);

        return redirect()->route('signup.finish');
    }

    // ------------------------------------------------------------------ dernière étape (réseau social ou téléphone)

    private function pending(): ?array
    {
        if ($s = session('signup.social')) {
            return ['via' => $s['provider'], 'name' => $s['name'], 'email' => $s['verified'] ? $s['email'] : null, 'avatar' => $s['avatar'] ?? null];
        }
        if ($phone = session('phone_auth.verified')) {
            return ['via' => 'phone', 'name' => '', 'email' => null, 'phone' => $phone, 'avatar' => null];
        }

        return null;
    }

    public function finish(Request $request): View|RedirectResponse
    {
        $p = $this->pending();
        if (! $p) {
            return redirect()->route('register');
        }

        return view('web.signup-finish', $this->viewData(old('type', session('signup.type'))) + ['pending' => $p]);
    }

    public function finishStore(Request $request): RedirectResponse
    {
        $p = $this->pending();
        if (! $p) {
            return redirect()->route('register');
        }
        $type = $this->typeFrom($request);
        if (! $type) {
            return back()->withInput()->withErrors(['type' => 'Choisis qui tu es.']);
        }
        $request->merge(['type' => $type]);
        $d = $request->validate(Signup::rules($type, false, (int) $request->input('age')), Signup::messages());
        $this->guard($d);
        $profile = Signup::profile($type, $d) + ['avatar_url' => $p['avatar']];
        if ($p['via'] === 'phone') {
            $profile['phone'] = $p['phone'];
            $email = preg_replace('/\D/', '', (string) $p['phone']).'@telephone.local';
        } else {
            $s = session('signup.social');
            $free = $p['email'] && ! DB::table('accounts')->where('email', Str::lower($p['email']))->exists();
            $email = $free ? $p['email'] : $s['provider'].'-'.substr(sha1($s['id']), 0, 16).'@social.local';
        }
        try {
            $user = $this->data->signUp($email, Str::random(32), Signup::role($type), $profile);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['name' => 'Inscription impossible : '.$e->getMessage()]);
        }
        if ($p['via'] !== 'phone') {
            $this->link(session('signup.social.provider'), session('signup.social'), $user['id']);
        } else {
            app(Members::class)->updatePrefs(['phone' => $p['phone'], 'notify_sms' => true]);
            // inscription par numéro + mot de passe (sans service SMS) : on garde le mot de passe choisi
            if ($hash = session('phone_auth.password_hash')) {
                DB::table('accounts')->where('id', $user['id'])->update(['password' => $hash, 'phone' => $p['phone']]);
            }
        }
        session()->forget(['signup.social', 'signup.type', 'phone_auth.verified', 'phone_auth.phone', 'phone_auth.password_hash']);
        $this->afterSignup($d);

        return redirect()->route(Signup::landing($type))->with('status', Signup::welcome($type));
    }

    // ------------------------------------------------------------------ outils

    private function guard(array $d): void
    {
        app(ContentGuard::class)->enforce(array_filter([
            'name' => $d['name'] ?? null, 'city' => $d['city'] ?? null, 'manager_name' => $d['manager_name'] ?? null,
            'club' => $d['club'] ?? null, 'organization' => $d['organization'] ?? null, 'favorite_club' => $d['favorite_club'] ?? null,
        ]), 'profile');
    }

    private function afterSignup(array $d): void
    {
        app(Members::class)->touch();
        app(Members::class)->setCountry($d['country'] ?? Region::viewer());
    }

    private function link(string $provider, array $u, string $accountId): void
    {
        try {
            DB::table('social_accounts')->updateOrInsert(['provider' => $provider, 'provider_id' => (string) $u['id']],
                ['account_id' => $accountId, 'email' => $u['email'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        } catch (Throwable) {
        }
    }
}
