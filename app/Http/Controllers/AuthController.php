<?php

namespace App\Http\Controllers;

use App\Terrain\ConfirmEmailException;
use App\Terrain\DataSource;
use App\Terrain\DemoData;
use App\Terrain\SportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    public function showLogin(): View
    {
        return view('web.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], ['email.required' => 'Indique ton e-mail.', 'email.email' => "Cet e-mail n'est pas valide.", 'password.required' => 'Indique ton mot de passe.']);

        try {
            $this->data->signIn($data['email'], $data['password']);
        } catch (Throwable $e) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'E-mail ou mot de passe incorrect.']);
        }

        return redirect()->intended(route('feed'));
    }

    /** Mode démo : entrer comme sportif ou comme recruteur en un clic. */
    public function demo(string $role): RedirectResponse
    {
        abort_unless($this->data instanceof DemoData && $this->data->isDemo() && in_array($role, ['athlete', 'recruiter'], true), 404);
        $this->data->demoLogin($role);

        return redirect()->intended(route('feed'))->with('status', $role === 'recruiter' ? 'Tu es connecté comme recruteur (démo).' : 'Tu es connecté comme sportif (démo).');
    }

    public function showRegister(): View
    {
        return view('web.register', ['sports' => SportFields::FIELDS, 'countries' => SportFields::COUNTRIES]);
    }

    public function register(Request $request): RedirectResponse
    {
        $minAge = (int) config('terrain.min_age', 13);
        $isAthlete = $request->input('role') === 'athlete';
        $sport = (string) $request->input('sport');

        $rules = [
            'country' => ['required', Rule::in(SportFields::COUNTRIES)],
            'role' => ['required', Rule::in(['athlete', 'recruiter'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:200'],
            'password' => ['required', 'string', 'min:8'],
            'terms' => ['accepted'],
        ];
        if ($isAthlete) {
            $rules += [
                'age' => ['required', 'integer', "min:{$minAge}", 'max:60'],
                'city' => ['nullable', 'string', 'max:120'],
                'sport' => ['required', Rule::in(SportFields::sports())],
                'fields' => ['array'],
                'guardian_phone' => [Rule::requiredIf(fn () => (int) $request->input('age') < 18), 'nullable', 'string', 'max:30'],
            ];
        }
        $data = $request->validate($rules, [
            'name.required' => 'Indique ton nom.',
            'password.min' => 'Le mot de passe doit faire au moins 8 caractères.',
            'terms.accepted' => "Accepte les conditions d'utilisation et la politique de confidentialité.",
            'age.min' => "Il faut avoir au moins {$minAge} ans.",
            'guardian_phone.required' => 'Moins de 18 ans : le téléphone du parent ou tuteur est obligatoire.',
            'sport.required' => 'Choisis ton sport.',
        ]);

        app(\App\Terrain\Growth\ContentGuard::class)->enforce(['name' => $data['name'], 'city' => $data['city'] ?? null], 'profile');
        $profile = ['name' => $data['name']];
        if ($isAthlete) {
            $fields = [];
            foreach (SportFields::FIELDS[$sport] as $f) {
                $v = trim((string) ($request->input('fields')[$f['label']] ?? ''));
                $fields[$f['label']] = $v !== '' ? $v : ($f['type'] === 'choice' ? $f['options'][0] : '–');
            }
            $profile += [
                'age' => $data['age'], 'country' => $data['country'], 'city' => $data['city'] ?? '',
                'sport' => $sport, 'fields' => $fields, 'guardian_phone' => $data['guardian_phone'] ?? null,
            ];
        }

        $profile['country'] ??= $data['country'] ?? \App\Terrain\Region::viewer();
        try {
            $this->data->signUp($data['email'], $data['password'], $data['role'], $profile);
            app(\App\Terrain\Growth\Members::class)->touch();
            app(\App\Terrain\Growth\Members::class)->setCountry($profile['country']);
        } catch (ConfirmEmailException $e) {
            return redirect()->route('login')->with('status', $e->getMessage());
        } catch (Throwable $e) {
            return back()->withInput($request->except('password'))->withErrors(['email' => 'Inscription impossible : '.$e->getMessage()]);
        }

        return redirect()->route($isAthlete ? 'me' : 'feed')->with('status', 'Bienvenue sur '.config('terrain.app_name').' !');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->data->signOut();
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
