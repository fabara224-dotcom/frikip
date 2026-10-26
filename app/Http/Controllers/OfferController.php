<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\SportFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Sportif : offres ouvertes et candidature. Recruteur : ses offres, ses candidats, nouvelle offre. */
class OfferController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    public function index(): View
    {
        $me = $this->data->user();
        $offers = $this->data->offers();
        $myAthlete = $me['athlete_id'] ? $this->data->athlete($me['athlete_id']) : null;
        $people = [];
        if ($me['role'] === 'recruiter') {
            foreach ($offers as $o) {
                foreach ($o['mine'] ? $o['candidates'] : [] as $id) {
                    $people[$id] ??= $this->data->athlete($id);
                }
            }
        }

        $insights = app(\App\Terrain\Growth\Insights::class);
        $recommended = [];
        if ($me['role'] === 'recruiter') {
            foreach ($offers as $o) {
                if ($o['mine']) {
                    $recommended[$o['id']] = $insights->playersFor($o, 5);
                }
            }
        } else {
            $offers = $insights->offersFor($myAthlete, $offers);
        }

        return view('web.offers', [
            'offers' => $offers, 'myAthlete' => $myAthlete, 'people' => array_filter($people), 'sports' => SportFields::sports(),
            'recommended' => $recommended,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'sport' => ['required', Rule::in(SportFields::sports())],
            'place' => ['nullable', 'string', 'max:120'],
            'deadline' => ['nullable', 'string', 'max:60'],
            'criteria' => ['nullable', 'string', 'max:300'],
        ], ['title.required' => "Donne un titre à l'offre."]);
        app(\App\Terrain\Growth\ContentGuard::class)->enforce(['title' => $data['title'] ?? null, 'criteria' => $data['criteria'] ?? null, 'place' => $data['place'] ?? null], 'offer', $this->data->user()['id'] ?? null);
        try {
            $this->data->createOffer($data);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['title' => 'Publication refusée : ton compte recruteur doit être vérifié.']);
        }

        // Prévenir les sportifs de ce sport inscrits sur le site
        try {
            $targets = \Illuminate\Support\Facades\DB::table('members')->where('role', 'athlete')->where('sport', $data['sport'])->pluck('user_key');
            app(\App\Terrain\Growth\Notifier::class)->sendMany($targets, 'offer', 'Nouvelle offre : '.$data['title'], ($data['place'] ?? '').' · '.($this->data->user()['name'] ?? ''), route('offers'));
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('offers')->with('status', 'Offre publiée. Les sportifs de ce sport sont prévenus.');
    }

    public function apply(Request $request, string $offer): RedirectResponse
    {
        $data = $request->validate([
            'video_id' => ['nullable', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        app(\App\Terrain\Growth\ContentGuard::class)->enforce(['message' => $data['message'] ?? null], 'offer', $this->data->user()['id'] ?? null);
        try {
            $this->data->apply($offer, $data['video_id'] ?? null, (string) ($data['message'] ?? ''));
        } catch (Throwable $e) {
            return back()->withErrors(['apply' => 'La candidature a échoué. Réessaie.']);
        }

        return redirect()->route('offers')->with('status', 'Candidature envoyée.');
    }
}
