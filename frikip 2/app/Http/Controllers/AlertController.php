<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Members;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Alertes recruteur : une recherche enregistrée (« milieux U19 à Kindia ») ; chaque jour, la commande
 * « php artisan terrain:alertes » prévient le recruteur des nouveaux profils qui y correspondent.
 */
class AlertController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    public function index(): View
    {
        return view('web.alerts', ['rows' => DB::table('saved_searches')->where('user_key', $this->data->user()['id'])->orderByDesc('id')->get()]);
    }

    public function store(Request $request, Members $members): RedirectResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'sport' => ['nullable', 'string', 'max:30'], 'country' => ['nullable', 'string', 'max:60'],
            'age' => ['nullable', 'in:Tous,-18,18-21,22+'], 'verified' => ['nullable', 'boolean'],
        ]);
        $f = array_filter($f, fn ($v) => $v !== null && $v !== '' && $v !== 'Tous');
        $label = implode(' · ', array_filter([$f['sport'] ?? 'Tous sports', $f['q'] ?? null, $f['country'] ?? null,
            isset($f['age']) ? ['-18' => 'moins de 18 ans', '18-21' => '18-21 ans', '22+' => '22 ans et +'][$f['age']] : null, ! empty($f['verified']) ? 'vérifiés' : null]));
        $members->touch();
        // Les profils actuels sont « déjà vus » : on ne préviendra que des nouveaux
        $seen = array_column($this->data->athletes($f), 'id');
        DB::table('saved_searches')->insert(['user_key' => $this->data->user()['id'], 'label' => $label, 'filters' => json_encode($f),
            'seen_ids' => json_encode($seen), 'last_sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('alerts')->with('status', 'Alerte créée : tu seras prévenu des nouveaux profils « '.$label.' ».');
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::table('saved_searches')->where('id', $id)->where('user_key', $this->data->user()['id'])->delete();

        return back()->with('status', 'Alerte supprimée.');
    }
}
