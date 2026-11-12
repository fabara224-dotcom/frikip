<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use Illuminate\View\View;

/**
 * Page unique du design FootPro (/app) : Accueil, Reels, Explorer, Matchs et lives, Messages, Profil, Menu.
 * L'interface (public/frikip-app) charge tout par l'API JSON /api/v1 (ApiController).
 */
class AppShellController extends Controller
{
    public function show(DataSource $data): View
    {
        $u = $data->user();
        $role = $u['role'] ?? null;

        return view('web.app-v2', [
            'config' => [
                'mode' => 'api',
                'apiBase' => url('/api/v1'),
                'loginUrl' => route('login'),
                'registerUrl' => route('register'),
                'chunkUrl' => route('publish.chunk'),
                'publishUrl' => route('publish'),
                'shareBase' => url('/fil?v='),
                'appName' => config('terrain.app_name', 'FootPro'),
                'pollScoresMs' => 30000,
                'pollMessagesMs' => 4000,
                // Pages du site ouvertes depuis l'interface (même design grâce à css/frikip-v5.css)
                'links' => [
                    'liveGo' => route('live.go'), 'liveBroadcast' => route('live.create'), 'liveAll' => route('live'),
                    'events' => route('events'), 'offers' => route('offers'), 'challenges' => route('challenges'), 'coaching' => route('coaching'),
                    'clubs' => route('clubs'), 'rankings' => route('rankings'), 'stories' => route('stories'), 'posts' => route('posts'), 'storyStore' => route('fkstories.store'), 'storyBase' => url('/stories'), 'actors' => route('actors'),
                    'profileEdit' => route('profile.edit'), 'settings' => route('settings'), 'stats' => route('stats'), 'verification' => route('verification'),
                    'payments' => route('payments.history'), 'premium' => route('premium', 'premium'), 'noAds' => route('premium', 'sans_pub'),
                    'boost' => route('premium', 'boost'), 'structure' => route('structure.offer'), 'videoServices' => route('video.services'),
                    'referral' => route('referral'), 'blocks' => route('blocks'), 'shortlists' => route('shortlists'), 'alerts' => route('alerts'),
                    'contacts' => route('contacts'), 'clubEdit' => route('clubs.edit'), 'coachingFollow' => route('coaching.follow'),
                    'support' => route('support'), 'partners' => route('partners'), 'logout' => route('logout'), 'deletion' => route('deletion'),
                    'proche' => route('register', ['type' => 'proche']), 'search' => route('search'), 'videoOrders' => route('video.orders'),
                    'block' => url('/bloquer'), 'report' => route('report'), 'call' => url('/appeler'), 'room' => url('/salon'),
                    'discoverPlay' => url('/decouvrir'), 'view' => url('/videos'),
                ],
                'role' => $role,
            ],
        ]);
    }
}
