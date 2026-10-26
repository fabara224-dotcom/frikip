<?php

namespace App\Http\Controllers;

use App\Terrain\Growth\AdminAuth;
use App\Terrain\Growth\Members;
use App\Terrain\Growth\Notifier;
use App\Terrain\Growth\Payments;
use App\Terrain\LocalData;
use App\Terrain\SportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Espace admin : comptes admin, et tout ce qu'un admin ajoute, modifie ou supprime
 * (membres, vidéos, commentaires, offres, détections, clubs, paiements).
 */
class AdminManageController extends Controller
{
    // ================================================================== comptes admin

    public function admins(Request $request): View
    {
        return view('admin.admins', [
            'rows' => DB::table('admins')->orderByDesc('active')->orderBy('name')->get(),
            'edit' => $request->filled('modifier') ? DB::table('admins')->find((int) $request->query('modifier')) : null,
            'roles' => AdminAuth::ROLES,
        ]);
    }

    public function saveAdmin(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('admins', 'email')->ignore($id ?: null)],
            'role' => ['required', Rule::in(array_keys(AdminAuth::ROLES))],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:10'],
        ], ['password.min' => 'Au moins 10 caractères.', 'email.unique' => 'Cet e-mail a déjà un compte admin.']);
        if ($id) {
            $me = app(AdminAuth::class)->current();
            if ($id === (int) $me->id && $data['role'] !== 'admin') {
                return back()->withErrors(['role' => 'Tu ne peux pas retirer ton propre rôle d’administrateur.']);
            }
            $row = ['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'role' => $data['role'], 'updated_at' => now()];
            if (! empty($data['password'])) {
                $row['password'] = Hash::make($data['password']);
            }
            DB::table('admins')->where('id', $id)->update($row);
            $this->keepOneAdmin();

            return redirect()->route('admin.admins')->with('saved', 'Compte admin modifié.');
        }
        app(AdminAuth::class)->create($data['name'], $data['email'], $data['password'], $data['role']);

        return redirect()->route('admin.admins')->with('saved', 'Compte admin créé : '.$data['email'].'. Donne-lui son mot de passe en main propre.');
    }

    public function adminAction(Request $request, int $id): RedirectResponse
    {
        $a = DB::table('admins')->find($id);
        abort_unless($a, 404);
        $me = app(AdminAuth::class)->current();
        abort_if($a->id === $me->id, 422, 'Action impossible sur ton propre compte.');
        if ($request->input('action') === 'supprimer') {
            DB::table('admins')->where('id', $id)->delete();
            $msg = 'Compte admin supprimé.';
        } else {
            DB::table('admins')->where('id', $id)->update(['active' => ! $a->active, 'updated_at' => now()]);
            $msg = $a->active ? 'Compte désactivé : il ne peut plus se connecter.' : 'Compte réactivé.';
        }
        $this->keepOneAdmin();

        return back()->with('saved', $msg);
    }

    /** Il reste toujours au moins un administrateur actif. */
    private function keepOneAdmin(): void
    {
        if (! DB::table('admins')->where('role', 'admin')->where('active', true)->exists()) {
            DB::table('admins')->where('id', app(AdminAuth::class)->current()->id)->update(['role' => 'admin', 'active' => true]);
        }
    }

    public function account(): View
    {
        return view('admin.account', ['a' => app(AdminAuth::class)->current()]);
    }

    public function saveAccount(Request $request): RedirectResponse
    {
        $me = app(AdminAuth::class)->current();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'current_password' => ['required', 'string'],
            'password' => ['nullable', 'string', 'min:10', 'confirmed'],
        ], ['password.confirmed' => 'Les deux nouveaux mots de passe ne sont pas identiques.', 'password.min' => 'Au moins 10 caractères.']);
        if (! Hash::check($data['current_password'], DB::table('admins')->where('id', $me->id)->value('password'))) {
            return back()->withErrors(['current_password' => 'Mot de passe actuel incorrect.']);
        }
        $row = ['name' => $data['name'], 'updated_at' => now()];
        if (! empty($data['password'])) {
            $row['password'] = Hash::make($data['password']);
        }
        DB::table('admins')->where('id', $me->id)->update($row);

        return back()->with('saved', ! empty($data['password']) ? 'Mot de passe changé.' : 'Compte enregistré.');
    }

    // ================================================================== membres

    /** Modifier, cadeaux, mot de passe, suppression d'un membre. */
    public function memberManage(Request $request, string $key): RedirectResponse
    {
        $m = DB::table('members')->where('user_key', $key)->first();
        $acc = DB::table('accounts')->where('id', $key)->first();
        abort_unless($m || $acc, 404);
        $action = (string) $request->input('do');
        $athleteId = $m->athlete_id ?? ($acc->athlete_id ?? null);
        switch ($action) {
            case 'edit':
                $data = $request->validate([
                    'name' => ['required', 'string', 'max:120'], 'email' => ['nullable', 'email', 'max:200'], 'phone' => ['nullable', 'string', 'max:30'],
                    'sport' => ['nullable', Rule::in(SportFields::sports())], 'city' => ['nullable', 'string', 'max:120'],
                ]);
                if ($acc) {
                    if (! empty($data['email']) && DB::table('accounts')->where('email', $data['email'])->where('id', '!=', $key)->exists()) {
                        return back()->withErrors(['email' => 'Cet e-mail est déjà utilisé par un autre compte.']);
                    }
                    DB::table('accounts')->where('id', $key)->update(['name' => $data['name'], 'email' => $data['email'] ?: $acc->email, 'phone' => $data['phone'] ?: $acc->phone, 'updated_at' => now()]);
                }
                DB::table('members')->where('user_key', $key)->update(array_filter(['name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null]) + ['updated_at' => now()]);
                if ($athleteId) {
                    DB::table('local_athletes')->where('id', $athleteId)->update(array_filter(['name' => $data['name'], 'sport' => $data['sport'] ?? null, 'city' => $data['city'] ?? null]) + ['updated_at' => now()]);
                }
                $msg = 'Profil modifié.';
                break;
            case 'password':
                $p = (string) $request->validate(['new_password' => ['required', 'string', 'min:8']])['new_password'];
                abort_unless($acc, 422);
                DB::table('accounts')->where('id', $key)->update(['password' => Hash::make($p), 'updated_at' => now()]);
                $msg = 'Nouveau mot de passe enregistré : donne-le au membre.';
                break;
            case 'gift':
                $d = $request->validate(['product' => ['required', Rule::in(['premium', 'boost', 'structure', 'sans_pub'])], 'days' => ['required', 'integer', 'min:1', 'max:365']]);
                app(Members::class)->extend($key, $d['product'], (int) $d['days']);
                app(Notifier::class)->send($key, 'payment', Payments::label($d['product']).' offert ('.$d['days'].' jours)', 'Cadeau de l’équipe '.config('terrain.app_name').'.', route('me'));
                $msg = Payments::label($d['product']).' offert pour '.$d['days'].' jours.';
                break;
            case 'delete':
                abort_unless($request->input('confirm') === 'SUPPRIMER', 422, 'Écris SUPPRIMER pour confirmer.');
                LocalData::purgeAccount($key, $athleteId);
                DB::table('members')->where('user_key', $key)->delete();

                return redirect()->route('admin.members')->with('saved', 'Compte supprimé avec ses vidéos, messages et commentaires.');
            default:
                abort(422);
        }

        return back()->with('saved', $msg);
    }

    /** Supprimer définitivement une vidéo (fichier compris). */
    public function deleteVideo(string $id): RedirectResponse
    {
        $v = DB::table('local_videos')->where('id', $id)->first();
        abort_unless($v, 404);
        foreach ([$v->url, $v->poster] as $f) {
            if ($f && ! str_starts_with($f, 'http')) {
                @unlink(public_path($f));
            }
        }
        DB::table('local_videos')->where('id', $id)->delete();
        foreach (['video_meta', 'video_subtitles', 'video_likes_local', 'video_comments_local', 'video_shares', 'video_views'] as $t) {
            try {
                DB::table($t)->where('video_id', $id)->delete();
            } catch (\Throwable) {
            }
        }
        DB::table('challenge_entries')->where('video_id', $id)->delete();

        return back()->with('saved', 'Vidéo supprimée.');
    }

    // ================================================================== contenus

    public function content(Request $request): View
    {
        $tab = in_array($request->query('onglet'), ['offres', 'detections', 'commentaires', 'videos'], true) ? $request->query('onglet') : 'videos';
        $q = trim((string) $request->query('q'));
        $like = fn ($query, array $cols) => $q === '' ? $query : $query->where(function ($w) use ($cols, $q) {
            foreach ($cols as $c) {
                $w->orWhere($c, 'like', '%'.$q.'%');
            }
        });
        $rows = match ($tab) {
            'offres' => $like(DB::table('local_offers'), ['title', 'organization', 'place'])->orderByDesc('created_at')->limit(200)->get(),
            'detections' => $like(DB::table('events'), ['title', 'city', 'place'])->orderByDesc('date')->limit(200)->get(),
            'commentaires' => $like(DB::table('video_comments_local'), ['body', 'author_name'])->orderByDesc('id')->limit(200)->get(),
            default => $like(DB::table('local_videos')->leftJoin('local_athletes', 'local_athletes.id', '=', 'local_videos.athlete_id')
                ->select('local_videos.*', 'local_athletes.name as athlete_name', 'local_athletes.account_id'), ['local_videos.title', 'local_athletes.name'])
                ->orderByDesc('local_videos.created_at')->limit(200)->get(),
        };

        return view('admin.content', ['tab' => $tab, 'rows' => $rows, 'q' => $q,
            'counts' => ['videos' => DB::table('local_videos')->count(), 'offres' => DB::table('local_offers')->count(),
                'detections' => DB::table('events')->count(), 'commentaires' => DB::table('video_comments_local')->count()]]);
    }

    public function deleteContent(Request $request, string $type, string $id): RedirectResponse
    {
        switch ($type) {
            case 'offre':
                DB::table('local_applications')->where('offer_id', $id)->delete();
                DB::table('local_offers')->where('id', $id)->delete();
                break;
            case 'detection':
                DB::table('event_registrations')->where('event_id', (int) $id)->delete();
                DB::table('events')->where('id', (int) $id)->delete();
                break;
            case 'commentaire':
                DB::table('video_comments_local')->where('id', (int) $id)->orWhere('parent_id', (int) $id)->delete();
                break;
            case 'club':
                DB::table('events')->where('organization_id', (int) $id)->delete();
                DB::table('organizations')->where('id', (int) $id)->delete();
                break;
            default:
                abort(404);
        }

        return back()->with('saved', 'Supprimé.');
    }

    // ================================================================== paiements

    /** Paiement reçu autrement (espèces, virement) : l'offre est activée à la main ; ou paiement annulé. */
    public function paymentAction(Request $request, Payments $payments, int $id): RedirectResponse
    {
        $p = DB::table('payments')->find($id);
        abort_unless($p, 404);
        if ($request->input('action') === 'valider' && $p->status !== 'accepted') {
            $payments->markPaid($p, 'manuel');
            DB::table('payments')->where('id', $id)->update(['provider_status' => 'VALIDE_PAR_ADMIN']);

            return back()->with('saved', 'Paiement validé : l’offre est activée et le client prévenu.');
        }
        if ($request->input('action') === 'annuler' && $p->status !== 'accepted') {
            DB::table('payments')->where('id', $id)->update(['status' => 'refused', 'provider_status' => 'ANNULE_PAR_ADMIN', 'updated_at' => now()]);

            return back()->with('saved', 'Paiement annulé.');
        }

        return back();
    }
}
