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
 * This mode is session-based and works as follows:
 * - Start returns two players (left/right) with hidden trophy counts.
 * - Guess accepts a choice (left/right/equal) and returns reveal values and correctness.
 * - On success, the right player becomes the new left player and a new right player is drawn.
 * - On failure, the session is cleared and game_over is returned.
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
     * Start a new session.
     *
     * @return JsonResponse
     */
    public function start(): JsonResponse
    {
        return response()->json($this->service->start());
    }

    /**
     * Submit a guess for a session.
     *
     * Request body:
     * - session_id: string
     * - choice: 'left'|'right'|'equal'
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function guess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:128'],
            'choice' => ['required', 'string', Rule::in(['left', 'right', 'equal'])],
        ]);

        return response()->json($this->service->guess(
            (string) $validated['session_id'],
            (string) $validated['choice'],
        ));
    }

    /**
     * End a session explicitly and clear its state.
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
