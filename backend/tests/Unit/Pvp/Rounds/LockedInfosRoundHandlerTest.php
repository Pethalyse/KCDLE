<?php

namespace Tests\Unit\Pvp\Rounds;

use App\Services\Pvp\Rounds\LockedInfosRoundHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class LockedInfosRoundHandlerTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_locked_infos_guess_flow_ends_when_both_solved(): void
    {
        $this->pvpConfigureForSingleRound('locked_infos');
        $secretId = $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'locked_infos');

        $handler = app(LockedInfosRoundHandler::class);
        $init = $handler->initialize($match);
        $match->state = array_replace_recursive((array) $match->state, $init);

        $r1 = $handler->handleAction($match, (int) $u1->id, ['type' => 'guess', 'player_id' => $secretId]);
        $match->state = array_replace_recursive((array) $match->state, $r1->statePatch);
        $r2 = $handler->handleAction($match, (int) $u2->id, ['type' => 'guess', 'player_id' => $secretId]);

        $this->assertTrue($r2->roundEnded);
        $this->assertContains($r2->roundWinnerUserId, [(int) $u1->id, (int) $u2->id]);
    }
}
