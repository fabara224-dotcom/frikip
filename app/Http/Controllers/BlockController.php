<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Blocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Bloquer / débloquer un utilisateur (compte ou profil de joueur). */
class BlockController extends Controller
{
    public function __construct(private DataSource $data, private Blocks $blocks)
    {
    }

    public function toggle(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $me = $this->data->user();
        abort_unless(preg_match('/^[A-Za-z0-9\-]{2,64}$/', $id), 404);
        $blocked = in_array($id, $this->blocks->blockedBy($me['id']), true);
        if ($blocked) {
            $this->blocks->unblock($me['id'], $id);
            $msg = __('Débloqué.');
        } else {
            $this->blocks->block($me['id'], $id);
            $msg = __('Bloqué : cette personne ne peut plus t’écrire et tu ne vois plus ses contenus.');
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'blocked' => ! $blocked, 'message' => $msg]);
        }

        return back()->with('status', $msg);
    }

    public function index(): View
    {
        $me = $this->data->user();
        $rows = $this->blocks->list($me['id']);
        $names = [];
        foreach ($rows as $r) {
            $names[$r->blocked] = DB::table('accounts')->where('id', $r->blocked)->value('name')
                ?? DB::table('local_athletes')->where('id', $r->blocked)->value('name') ?? $r->blocked;
        }

        return view('web.blocks', ['rows' => $rows, 'names' => $names]);
    }
}
