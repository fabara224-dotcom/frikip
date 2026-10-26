<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Members;
use App\Terrain\Growth\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

/** Premium (recruteurs) et Boost (sportifs) : Orange Money, MTN MoMo ou carte, via CinetPay. */
class PaymentController extends Controller
{
    public function __construct(private DataSource $data, private Payments $payments, private Members $members)
    {
    }

    public function show(string $product): View
    {
        $this->allowed($product);
        $m = $this->members->current();

        return view('web.payment', ['product' => $product, 'plans' => Payments::plans($product), 'live' => Payments::live(), 'member' => $m,
            'simulation' => $this->simulationAllowed(), 'country' => \App\Terrain\Region::viewer(), 'countryOk' => \App\Terrain\Region::canPay(\App\Terrain\Region::viewer())]);
    }

    /** Message affiché quand le paiement en ligne n'est pas encore ouvert dans le pays du membre. */
    public static function countryClosedMessage(): ?string
    {
        $c = \App\Terrain\Region::viewer();

        return \App\Terrain\Region::canPay($c) ? null : __('Le paiement en ligne n’est pas encore disponible pour ce pays (:c). Il arrive bientôt : écris-nous si tu veux payer autrement.', ['c' => $c]);
    }

    /** Premium et Structure pour les recruteurs, Boost pour les sportifs ; le montage passe par sa page de commande. */
    private function allowed(string $product): void
    {
        $role = $this->data->user()['role'] ?? null;
        abort_unless(in_array($product, ['premium', 'boost', 'structure', 'sans_pub'], true), 404);
        abort_if($product === 'structure' && $role !== 'recruiter', 403, 'L’abonnement Structure est réservé aux comptes club, académie ou agent.');
    }

    /** Paiement simulé : seulement en démo ou sur l'ordinateur de développement, jamais sur le vrai site. */
    public function simulationAllowed(): bool
    {
        return ! Payments::live() && ($this->data->isDemo() || app()->environment('local'));
    }

    /** Historique des paiements de l'utilisateur (reçus). */
    public function history(): View
    {
        $rows = \Illuminate\Support\Facades\DB::table('payments')->where('user_key', $this->data->user()['id'])->orderByDesc('id')->limit(100)->get();

        return view('web.payments-history', ['rows' => $rows, 'member' => $this->members->current()]);
    }

    public function start(Request $request, string $product): RedirectResponse
    {
        $this->allowed($product);
        $data = $request->validate([
            'plan' => ['required', 'integer', 'min:0', 'max:'.(count(Payments::plans($product)) - 1)],
            'method' => ['required', 'in:om,momo,card'],
            'phone' => ['required', 'string', 'max:30', function ($attr, $value, $fail) {
                if (strlen(preg_replace('/\D/', '', (string) $value)) < 8) {
                    $fail('Entre un numéro valide (8 chiffres minimum).');
                }
            }],
        ]);
        $u = $this->data->user();
        if ($closed = self::countryClosedMessage()) {
            return back()->withInput()->withErrors(['phone' => $closed]);
        }
        if (! Payments::live() && ! $this->simulationAllowed()) {
            return back()->withInput()->withErrors(['phone' => __('Le paiement en ligne n’est pas encore ouvert sur ce site. Réessaie bientôt.')]);
        }
        $this->members->touch();
        $p = $this->payments->create($u['id'], $product, (int) $data['plan'], $data['method'], $data['phone']);
        try {
            return redirect()->away($this->checkoutUrl($p));
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['phone' => $e->getMessage()]);
        }
    }

    /** Adresse où envoyer le client : page CinetPay, ou page de simulation sur l'ordinateur de développement. */
    public function checkoutUrl(object $p): string
    {
        if (! Payments::live()) {
            return route('payments.simulation', $p->reference);
        }
        $u = $this->data->user();

        return $this->payments->checkoutUrl($p, ['name' => $u['name'] ?? 'Client', 'email' => $u['email'] ?? null]);
    }

    /** Démo / tests : confirmation par un code à 4 chiffres (aucun argent n'est débité). */
    public function simulation(string $ref): View
    {
        $p = $this->mine($ref);
        abort_unless($p->provider === 'simulation' && $this->simulationAllowed(), 404);

        return view('web.payment-simulation', ['p' => $p]);
    }

    public function confirmSimulation(Request $request, string $ref): RedirectResponse
    {
        $p = $this->mine($ref);
        abort_unless($p->provider === 'simulation' && $this->simulationAllowed(), 404);
        $request->validate(['code' => ['required', 'digits:4']], ['code.digits' => 'Le code doit contenir 4 chiffres.']);
        $this->payments->markPaid($p);
        $this->activateSession($p);

        return redirect()->route('payments.return', $ref);
    }

    /** Retour du client après la page CinetPay. */
    public function back(string $ref): View
    {
        $p = $this->mine($ref);
        if ($p->status === 'pending') {
            try {
                $p = $this->payments->verify($p);
            } catch (Throwable) {
            }
        }
        if ($p->status === 'accepted') {
            $this->activateSession($p);
        }

        return view('web.payment-result', ['p' => $p]);
    }

    /** Notification serveur de CinetPay (GET = test de disponibilité, POST = paiement mis à jour). */
    public function notify(Request $request): Response
    {
        if ($request->isMethod('post') && ($ref = (string) $request->input('cpm_trans_id'))) {
            $p = $this->payments->find($ref);
            if ($p && $p->status !== 'accepted' && (string) $request->input('cpm_site_id', config('terrain.cinetpay.site_id')) === (string) config('terrain.cinetpay.site_id')) {
                try {
                    $this->payments->verify($p);   // on ne croit jamais la notification : on redemande l'état à CinetPay
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return response('OK');
    }

    private function mine(string $ref): object
    {
        $p = $this->payments->find($ref);
        abort_unless($p && $p->user_key === ($this->data->user()['id'] ?? null), 404);

        return $p;
    }

    /** Met aussi à jour la session (mode démo) pour que l'effet soit immédiat. */
    private function activateSession(object $p): void
    {
        try {
            if ($this->data->isDemo()) {
                $this->data->activate($p->product);
            }
        } catch (Throwable) {
        }
    }
}
