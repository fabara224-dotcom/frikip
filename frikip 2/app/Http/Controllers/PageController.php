<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Challenges;
use App\Terrain\Growth\DemoSeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class PageController extends Controller
{
    public function home(DataSource $data, DemoSeed $seed, Challenges $challenges): View
    {
        $seed->ensure();
        $counters = ['players' => 0, 'clubs' => 0, 'trials' => 0];
        $winner = null;
        $events = collect();
        try {
            $athletes = $data->athletes([]);
            $offers = $data->offers();
            $counters = [
                'players' => count($athletes) + DB::table('members')->where('role', 'athlete')->whereNotIn('athlete_id', array_column($athletes, 'id'))->count(),
                'clubs' => DB::table('organizations')->count() + DB::table('members')->where('role', 'recruiter')->count(),
                'trials' => array_sum(array_map(fn ($o) => count($o['candidates']), $offers)) + DB::table('event_registrations')->count(),
            ];
            $challenges->current();
            $winner = $challenges->winners(1)[0] ?? null;
            $events = DB::table('events')->where('date', '>=', today()->toDateString())->orderBy('date')->limit(3)->get();
        } catch (Throwable) {
        }

        return view('pages.home', ['counters' => $counters, 'winner' => $winner, 'events' => $events]);
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function childSafety(): View
    {
        return view('pages.child-safety');
    }

    public function support(): View
    {
        return view('pages.support');
    }

    /** Aide audio en langues nationales (enregistrements à déposer dans public/aide/<langue>/<page>.mp3). */
    public function audioHelp(): View
    {
        return view('web.audio-help', ['langs' => self::AUDIO_LANGS, 'pages' => self::AUDIO_PAGES]);
    }

    public const AUDIO_LANGS = ['soussou' => 'Soussou', 'pular' => 'Pular', 'malinke' => 'Malinké', 'francais' => 'Français'];

    public const AUDIO_PAGES = [
        'inscription' => 'Créer mon compte', 'publier' => 'Publier une compilation', 'profil' => 'Bien remplir mon profil',
        'securite' => 'Se protéger des arnaques', 'detections' => 'S’inscrire à une détection', 'messages' => 'Messages et appels',
    ];

    /** Mode économie de données (3G) : affiches seulement, vidéos lancées à la demande. */
    public function lite(string $on): RedirectResponse
    {
        return redirect()->to(url()->previous() ?: route('feed'))->withCookie(cookie('terrain_lite', $on === '1' ? '1' : '0', 60 * 24 * 365));
    }

    /** Pays affiché (fil, classements, prix) pour un visiteur ; un membre change le sien dans son profil. */
    public function country(string $country): RedirectResponse
    {
        abort_unless(isset(\App\Terrain\Region::COUNTRIES[$country]), 404);
        app(\App\Terrain\Growth\Members::class)->setCountry($country);

        return redirect()->to(url()->previous() ?: route('feed'))->withCookie(cookie('terrain_pays', $country, 60 * 24 * 365));
    }

    public function locale(string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('terrain.locales', [])), 404);

        return redirect()->to(url()->previous() ?: route('home'))->withCookie(cookie('terrain_lang', $locale, 60 * 24 * 365));
    }
}
