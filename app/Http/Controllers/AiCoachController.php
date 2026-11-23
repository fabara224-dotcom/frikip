<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\AiCoach;
use App\Terrain\Growth\ContentGuard;
use App\Terrain\Growth\Members;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Coach IA : la page de discussion avec le coach personnel. */
class AiCoachController extends Controller
{
    public function __construct(private DataSource $data, private AiCoach $coach)
    {
    }

    public function index(): View
    {
        $me = $this->data->user();

        return view('web.coach-ia', [
            'me' => $me,
            'ready' => AiCoach::ready(),
            'msgs' => $me ? $this->coach->history($me['id']) : collect(),
            'premium' => $me ? $this->coach->premium($me['id']) : false,
            'remaining' => $me ? $this->coach->remaining($me['id']) : 0,
            'free' => (int) config('terrain.ai_coach.free_per_day', 3),
            'suggestions' => [
                __('Fais-moi un programme pour la semaine'),
                __('Comment devenir plus rapide ?'),
                __('Exercices pour mon pied faible'),
                __('Que manger avant un match ?'),
                __('Comment filmer ma vidéo de recrutement ?'),
            ],
        ]);
    }

    public function send(Request $request): RedirectResponse|JsonResponse
    {
        $me = $this->data->user();
        $data = $request->validate(['message' => ['required', 'string', 'max:1500']], ['message.required' => __('Écris ta question au coach.')]);
        $message = trim($data['message']);
        if (! AiCoach::ready()) {
            return $this->fail($request, __('Le coach IA arrive très bientôt.'));
        }
        if ($this->coach->remaining($me['id']) <= 0) {
            return $this->fail($request, $this->coach->premium($me['id'])
                ? __('Tu as beaucoup échangé avec le coach aujourd’hui. Reviens demain !')
                : __('Tu as utilisé tes :n messages gratuits du jour. Reviens demain, ou passe en Premium pour discuter sans limite.', ['n' => (int) config('terrain.ai_coach.free_per_day', 3)]), 'limit');
        }
        app(ContentGuard::class)->enforce(['message' => $message], 'coach_ia', $me['id']);
        app(Members::class)->touch();
        try {
            $reply = $this->coach->ask($me, $message);
        } catch (RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }
        if ($request->expectsJson()) {
            return response()->json(['reply' => $reply, 'html' => AiCoach::html($reply), 'remaining' => $this->coach->remaining($me['id'])]);
        }

        return redirect()->to(route('coach.ia').'#fin');
    }

    public function reset(): RedirectResponse
    {
        $this->coach->reset($this->data->user()['id']);

        return redirect()->route('coach.ia')->with('status', __('Nouvelle conversation : le coach repart de zéro.'));
    }

    private function fail(Request $request, string $message, string $code = 'error'): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'code' => $code], $code === 'limit' ? 429 : 422)
            : back()->withInput()->withErrors(['message' => $message]);
    }
}
