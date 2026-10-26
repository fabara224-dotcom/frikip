<?php

namespace App\Http\Controllers;

use App\Terrain\Growth\Ads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Côté visiteur : vidéo de la pub, pub vue, clic vers l'annonceur. */
class AdController extends Controller
{
    public function seen(int $id, Ads $ads): JsonResponse
    {
        $k = "ads.seen.$id";
        $n = (int) session($k, 0);
        if ($n < 20) {   // limite par session contre les compteurs gonflés
            session([$k => $n + 1]);
            $ads->impression($id);
        }

        return response()->json(['ok' => true]);
    }

    public function click(int $id, Ads $ads): RedirectResponse
    {
        $ad = DB::table('ads')->find($id);
        abort_unless($ad && $ad->link, 404);
        if (! session()->has("ads.click.$id")) {
            session(["ads.click.$id" => true]);
            $ads->click($id);
        }

        return redirect()->away($ad->link);
    }

    public function video(int $id): BinaryFileResponse
    {
        return $this->file($id, 'video_path');
    }

    public function poster(int $id): BinaryFileResponse
    {
        return $this->file($id, 'poster_path');
    }

    private function file(int $id, string $col): BinaryFileResponse
    {
        $path = DB::table('ads')->where('id', $id)->value($col);
        $full = $path ? Ads::dir().'/'.basename($path) : null;
        abort_unless($full && is_file($full), 404);

        return response()->file($full, ['Cache-Control' => 'public, max-age=86400']);
    }
}
