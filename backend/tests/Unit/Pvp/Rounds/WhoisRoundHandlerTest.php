<?php

namespace Tests\Unit\Pvp\Rounds;

use App\Services\Pvp\Rounds\WhoisRoundHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class WhoisRoundHandlerTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_whois_turn_based_question_then_correct_guess_ends_round(): void
    {
        $this->pvpConfigureForSingleRound('whois');
        $secretId = $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'whois');

        $handler = app(WhoisRoundHandler::class);
        $init = $handler->initialize($match);
        $match->state = array_replace_recursive((array) $match->state, $init);

        $match->state = array_replace_recursive((array) $match->state, ['chooser_user_id' => (int) $u1->id]);

        $choose = $handler->handleAction($match, (int) $u1->id, ['type' => 'choose_turn', 'first_player_user_id' => (int) $u1->id]);
        $match->state = array_replace_recursive((array) $match->state, $choose->statePatch);

        $ask = $handler->handleAction($match, (int) $u1->id, [
            'type' => 'ask',
            'question' => ['key' => 'trophies_count', 'op' => 'eq', 'value' => 0],
        ]);

        $this->assertFalse($ask->roundEnded);
        $match->state = array_replace_recursive((array) $match->state, $ask->statePatch);

        $turn = (int) ($match->state['turn_user_id'] ?? 0);
        $this->assertSame((int) $u2->id, $turn);

        $guess = $handler->handleAction($match, (int) $u2->id, ['type' => 'guess', 'player_id' => $secretId]);
        $this->assertTrue($guess->roundEnded);
        $this->assertSame((int) $u2->id, (int) $guess->roundWinnerUserId);
    }
}
