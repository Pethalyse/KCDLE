<?php

namespace Tests\Unit\Pvp\Rounds;

use App\Services\Pvp\Rounds\RevealRaceRoundHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class RevealRaceRoundHandlerTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_wrong_lock_blocks_player_then_correct_lock_ends_round(): void
    {
        $this->pvpConfigureForSingleRound('reveal_race');

        $idA = $this->pvpSeedMinimalKcdlePlayer('a', 'A');
        $idB = $this->pvpSeedMinimalKcdlePlayer('b', 'B');

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'reveal_race');

        $handler = app(RevealRaceRoundHandler::class);

        $init = $handler->initialize($match);
        $match->state = array_replace_recursive((array) $match->state, $init);

        $data = (array) ($match->state['round_data']['reveal_race'] ?? []);
        $secretId = (int) ($data['secret_player_id'] ?? 0);
        $this->assertTrue(in_array($secretId, [$idA, $idB], true));

        $wrongId = ($secretId === $idA) ? $idB : $idA;

        $bad = $handler->handleAction($match, (int) $u1->id, ['type' => 'lock', 'player_id' => $wrongId]);
        $this->assertFalse($bad->roundEnded);

        $match->state = array_replace_recursive((array) $match->state, $bad->statePatch);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('You are temporarily blocked.');
        $handler->handleAction($match, (int) $u1->id, ['type' => 'lock', 'player_id' => $secretId]);
    }

    public function test_correct_lock_wins_immediately(): void
    {
        $this->pvpConfigureForSingleRound('reveal_race');

        $this->pvpSeedMinimalKcdlePlayer('a', 'A');
        $this->pvpSeedMinimalKcdlePlayer('b', 'B');

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'reveal_race');

        $handler = app(RevealRaceRoundHandler::class);

        $init = $handler->initialize($match);
        $match->state = array_replace_recursive((array) $match->state, $init);

        $data = (array) ($match->state['round_data']['reveal_race'] ?? []);
        $secretId = (int) ($data['secret_player_id'] ?? 0);
        $this->assertGreaterThan(0, $secretId);

        $win = $handler->handleAction($match, (int) $u2->id, ['type' => 'lock', 'player_id' => $secretId]);

        $this->assertTrue($win->roundEnded);
        $this->assertSame((int) $u2->id, (int) $win->roundWinnerUserId);
    }
}
