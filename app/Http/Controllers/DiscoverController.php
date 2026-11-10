<?php

namespace App\Http\Controllers;

use App\Terrain\Growth\Discover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Onglet « Découvrir » : vidéos de foot YouTube, lues avec le lecteur officiel et créditées. */
class DiscoverController extends Controller
{
    public function __construct(private Discover $d)
    {
    }

    public function index(Request $request): View|Response
    {
        $topics = $this->d->topics();
        $topic = (string) $request->query('theme', '');
        $topic = isset($topics[$topic]) ? $topic : null;
        $page = max(1, (int) $request->query('page', 1));
        $list = $this->d->page($topic, $page);
        $next = count($list) === 24 ? route('discover', array_filter(['theme' => $topic, 'page' => $page + 1])) : null;
        if ($request->query('fragment')) {
            return response(view('web.partials.discover-cards', ['list' => $list])->render())->header('X-Next', (string) $next);
        }

        return view('web.discover', ['list' => $list, 'topics' => $topics, 'topic' => $topic, 'next' => $next, 'total' => Discover::count()]);
    }

    /** Une lecture (pour classer les vidéos les plus regardées). */
    public function play(int $id): JsonResponse
    {
        DB::table('discover_videos')->where('id', $id)->increment('plays');

        return response()->json(['ok' => true]);
    }
}
