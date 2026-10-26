<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\LocalData;
use App\Terrain\SportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Vrais comptes du site : modifier son profil, changer ou retrouver son mot de passe. */
class AccountController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    private function local(): LocalData
    {
        abort_unless($this->data instanceof LocalData, 404);

        return $this->data;
    }

    /** E-mail réellement envoyé ? (sinon, en développement, le lien est affiché à l'écran) */
    public static function mailConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null, ''], true);
    }

    // ------------------------------------------------------------------ profil

    public function edit(): View
    {
        $this->local();
        $u = $this->data->user();
        $a = $u['athlete_id'] ? $this->data->athlete($u['athlete_id']) : null;

        return view('web.profile-edit', ['u' => $u, 'a' => $a, 'sports' => SportFields::FIELDS, 'countries' => SportFields::COUNTRIES,
            'orgTypes' => ['Club', 'Académie', 'Agent', 'Université', 'Fédération', 'Autre']]);
    }

    public function update(Request $request): RedirectResponse
    {
        $local = $this->local();
        $u = $this->data->user();
        $minAge = (int) config('terrain.min_age', 13);
        $rules = ['name' => ['required', 'string', 'max:120']];
        if ($u['athlete_id']) {
            $rules += [
                'age' => ['required', 'integer', "min:{$minAge}", 'max:60'], 'country' => ['required', Rule::in(SportFields::COUNTRIES)],
                'city' => ['nullable', 'string', 'max:120'], 'height' => ['nullable', 'string', 'max:20'], 'weight' => ['nullable', 'string', 'max:20'],
                'level' => ['nullable', 'string', 'max:40'], 'club' => ['nullable', 'string', 'max:120'], 'availability' => ['nullable', 'string', 'max:60'],
                'specialty' => ['nullable', 'string', 'max:80'], 'fields' => ['array'],
                'guardian_phone' => [Rule::requiredIf(fn () => (int) $request->input('age') < 18), 'nullable', 'string', 'max:30'],
            ];
        } else {
            $rules += ['org_type' => ['nullable', 'string', 'max:40'], 'country' => ['nullable', Rule::in(SportFields::COUNTRIES)]];
        }
        $data = $request->validate($rules, ['guardian_phone.required' => 'Moins de 18 ans : le téléphone du parent ou tuteur est obligatoire.']);
        app(\App\Terrain\Growth\ContentGuard::class)->enforce(array_merge(array_intersect_key($data, array_flip(['name', 'city', 'club', 'availability', 'specialty', 'org_type'])),
            array_map('strval', (array) $request->input('fields', []))), 'profile', $u['id']);
        if ($u['athlete_id']) {
            $a = $this->data->athlete($u['athlete_id']);
            $fields = [];
            foreach (SportFields::FIELDS[$a['sport']] ?? [] as $f) {
                $v = trim((string) ($request->input('fields')[$f['label']] ?? ''));
                $fields[$f['label']] = $v !== '' ? $v : '–';
            }
            $data['fields'] = $fields;
        }
        $local->updateProfile($data);
        app(\App\Terrain\Growth\Members::class)->setCountry($data['country'] ?? null);

        return redirect()->route('profile.edit')->with('status', __('Profil enregistré.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $local = $this->local();
        $u = $this->data->user();
        $acc = DB::table('accounts')->where('id', $u['id'])->first();
        $request->validate([
            'current' => [$acc && $acc->password ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], ['password.min' => 'Le mot de passe doit faire au moins 8 caractères.', 'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.']);
        if ($acc && $acc->password && ! Hash::check((string) $request->input('current'), $acc->password)) {
            return back()->withErrors(['current' => __('Mot de passe actuel incorrect.')]);
        }
        $local->changePassword($u['id'], (string) $request->input('password'));

        return redirect()->route('profile.edit')->with('status', __('Mot de passe modifié.'));
    }

    // ------------------------------------------------------------------ mot de passe oublié

    public function forgot(): View
    {
        $this->local();

        return view('web.password-forgot');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $this->local();
        $email = Str::lower(trim((string) $request->validate(['email' => ['required', 'email']])['email']));
        $acc = DB::table('accounts')->where('email', $email)->first();
        $done = redirect()->route('password.forgot')->with('status', __('Si un compte existe avec cet e-mail, un lien pour choisir un nouveau mot de passe vient d’être envoyé (valable 1 heure).'));
        if (! $acc) {
            return $done;
        }
        $token = Str::random(48);
        DB::table('password_resets_local')->where('account_id', $acc->id)->delete();
        DB::table('password_resets_local')->insert(['account_id' => $acc->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        $link = route('password.reset', $token);
        try {
            Mail::raw(__('Bonjour :name,', ['name' => $acc->name])."\n\n".__('Pour choisir un nouveau mot de passe, ouvre ce lien (valable 1 heure) :')."\n{$link}\n\n".__('Si tu n’as rien demandé, ignore ce message.')."\n\n— ".config('terrain.app_name'),
                fn ($m) => $m->to($email)->subject(__('Nouveau mot de passe').' – '.config('terrain.app_name')));
        } catch (Throwable $e) {
            Log::warning('E-mail de mot de passe non envoyé : '.$e->getMessage());
        }
        // Sur ton ordinateur, sans serveur d'e-mail configuré : le lien est affiché pour pouvoir tester
        if (! self::mailConfigured() && app()->environment('local')) {
            return $done->with('dev_link', $link);
        }

        return $done;
    }

    private function resetRow(string $token): ?object
    {
        return DB::table('password_resets_local')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
    }

    public function resetForm(string $token): View|RedirectResponse
    {
        $this->local();
        if (! $this->resetRow($token)) {
            return redirect()->route('password.forgot')->withErrors(['email' => __('Lien expiré ou déjà utilisé. Demande un nouveau lien.')]);
        }

        return view('web.password-reset', ['token' => $token]);
    }

    public function reset(Request $request, string $token): RedirectResponse
    {
        $local = $this->local();
        $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']],
            ['password.min' => 'Le mot de passe doit faire au moins 8 caractères.', 'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.']);
        $row = $this->resetRow($token);
        if (! $row) {
            return redirect()->route('password.forgot')->withErrors(['email' => __('Lien expiré ou déjà utilisé. Demande un nouveau lien.')]);
        }
        $local->changePassword($row->account_id, (string) $request->input('password'));
        DB::table('password_resets_local')->where('account_id', $row->account_id)->delete();
        $email = DB::table('accounts')->where('id', $row->account_id)->value('email');
        $local->signIn($email, (string) $request->input('password'));

        return redirect()->route('feed')->with('status', __('Mot de passe modifié. Tu es connecté.'));
    }
}
