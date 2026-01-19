<?php

namespace App\Http\Controllers\Api\Kcdle;

use App\Http\Controllers\Controller;
use App\Services\Kcdle\TrophiesHigherLowerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller responsible for the KCDLE trophies Higher/Lower solo game mode.
 *
 * This mode is a session-based mini-game:
 * - The client starts a session.
 * - The API returns two players (left/right) without trophies revealed.
 * - The client submits guesses by selecting which player has more trophies.
 * - The API returns the reveal values and whether the guess is correct.
 * - On success, the right player becomes the next left player and a new right player is drawn.
 */
class TrophiesHigherLowerController extends Controller
{
    /**
     * @param TrophiesHigherLowerService $service
     */
    public function __construct(private readonly TrophiesHigherLowerService $service)
    {
    }

    /**
     * Start a new trophies Higher/Lower session.
     *
     * Response JSON:
     * - session_id: string
     * - score: int
     * - round: int
     * - left: array{id:int,name:string,image_url:?string,trophies_count:?int}
     * - right: array{id:int,name:string,image_url:?string,trophies_count:?int}
     *
     * @return JsonResponse
     */
    public function start(): JsonResponse
    {
        return response()->json($this->service->start());
    }

    /**
     * Submit a guess for a given session.
     *
     * Request body:
     * - session_id: string
     * - choice: 'left'|'right'
     *
     * Response JSON:
     * - session_id: string
     * - correct: bool
     * - clicked: 'left'|'right'
     * - reveal: array{left:int,right:int}
     * - score: int
     * - round: int
     * - game_over: bool
     * - next: null|array{session_id:string,score:int,round:int,left:array,right:array}
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function guess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:128'],
            'choice' => ['required', 'string', Rule::in(['left', 'right'])],
        ]);

        return response()->json($this->service->guess(
            (string) $validated['session_id'],
            (string) $validated['choice'],
        ));
    }

    /**
     * End a session explicitly and clear its state.
     *
     * Request body:
     * - session_id: string
     *
     * Response JSON:
     * - ended: bool
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function end(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:128'],
        ]);

        $this->service->end((string) $validated['session_id']);

        return response()->json(['ended' => true]);
    }
}
