<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Region;
use App\Terrain\Signup;
use App\Terrain\SportFields;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Onglet « Explorer » : joueurs, acteurs du foot, clubs, détections, matchs, défis, coaching, vidéos à découvrir. */
class ExploreController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $poste = (string) $request->query('poste', '');
        $postes = SportFields::FIELDS['Football'][0]['options'];
        $players = [];
        if ($q !== '' || in_array($poste, $postes, true)) {
            $players = $this->data->athletes(['q' => $q, 'country' => 'Tous']);
            if (in_array($poste, $postes, true)) {
                $players = array_values(array_filter($players, fn ($a) => ($a['fields']['Poste'] ?? '') === $poste));
            }
            $players = array_slice($players, 0, 40);
        }
        $actors = array_slice($this->data->athletes(['kind' => 'pro']), 0, 8);

        return view('web.explore', [
            'q' => $q, 'poste' => $poste, 'postes' => $postes, 'players' => $players, 'searched' => $q !== '' || $poste !== '',
            'actors' => $actors, 'discover' => \App\Terrain\Growth\Discover::count(),
        ]);
    }

    /** Entraîneurs, arbitres, médecins, kinés, préparateurs… */
    public function actors(Request $request): View
    {
        $p = (string) $request->query('metier', '');
        $p = in_array($p, Signup::PROFESSIONS, true) ? $p : '';
        $list = $this->data->athletes(['kind' => 'pro', 'profession' => $p ?: null, 'country' => (string) $request->query('pays', 'Tous')]);

        return view('web.actors', ['list' => $list, 'metier' => $p, 'professions' => Signup::PROFESSIONS, 'country' => (string) $request->query('pays', 'Tous'), 'countries' => SportFields::COUNTRIES]);
    }

    /** Bouton « Publier » de la barre pour ceux qui ne publient pas de vidéo de joueur : live, détection, offre… */
    public function create(): View
    {
        return view('web.create', ['me' => $this->data->user(), 'cfReady' => \App\Terrain\Growth\UserLive::configured()]);
    }
}
