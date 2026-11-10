<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Rooms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Salon du match / du live : lecture pour tous, écriture pour les membres connectés. */
class RoomController extends Controller
{
    public function __construct(private DataSource $data, private Rooms $rooms)
    {
    }

    public function index(Request $request, string $room): JsonResponse
    {
        abort_unless(Rooms::valid($room), 404);

        return response()->json(['messages' => $this->rooms->since($room, (int) $request->query('apres', 0), $this->data->user()['id'] ?? null), 'active' => $this->rooms->active($room)]);
    }

    public function store(Request $request, string $room): JsonResponse
    {
        abort_unless(Rooms::valid($room), 404);
        $u = $this->data->user();
        try {
            $kind = $request->filled('react') ? 'react' : 'text';
            $id = $this->rooms->post($room, $u, (string) ($kind === 'react' ? $request->input('react') : $request->input('body')), $kind);
        } catch (ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function report(int $id): JsonResponse
    {
        $this->rooms->report($id);

        return response()->json(['ok' => true]);
    }
}
