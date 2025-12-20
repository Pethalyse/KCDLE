<?php

namespace Tests\Unit\Pvp\Rounds;

use App\Services\Pvp\Rounds\ClassicRoundHandler;
use App\Services\Pvp\Rounds\PvpRoundResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class ClassicRoundHandlerTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_initialize_creates_state_for_two_players(): void
    {
        $this->pvpConfigureForSingleRound('classic');
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $handler = app(ClassicRoundHandler::class);
        $init = $handler->initialize($match);

        $this->assertArrayHasKey('round_data', $init);
        $data = (array) ($init['round_data']['classic'] ?? []);
        $this->assertGreaterThan(0, (int) ($data['secret_player_id'] ?? 0));
        $players = (array) ($data['players'] ?? []);
        $this->assertArrayHasKey((int) $u1->id, $players);
        $this->assertArrayHasKey((int) $u2->id, $players);
    }

    public function test_guess_solves_and_round_ends_when_both_solved(): void
    {
        $this->pvpConfigureForSingleRound('classic');
        $secretId = $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $handler = app(ClassicRoundHandler::class);
        $init = $handler->initialize($match);

        $match->state = array_replace_recursive((array) $match->state, $init);

        $r1 = $handler->handleAction($match, (int) $u1->id, ['type' => 'guess', 'player_id' => $secretId]);
        $this->assertInstanceOf(PvpRoundResult::class, $r1);
        $this->assertFalse($r1->roundEnded);

        $match->state = array_replace_recursive((array) $match->state, $r1->statePatch);

        $r2 = $handler->handleAction($match, (int) $u2->id, ['type' => 'guess', 'player_id' => $secretId]);
        $this->assertTrue($r2->roundEnded);
        $this->assertContains($r2->roundWinnerUserId, [(int) $u1->id, (int) $u2->id]);
    }
}
