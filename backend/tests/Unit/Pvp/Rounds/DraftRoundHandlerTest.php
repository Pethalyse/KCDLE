<?php

namespace Tests\Unit\Pvp\Rounds;

use App\Services\Pvp\Rounds\DraftRoundHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class DraftRoundHandlerTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_draft_phase_order_and_hint_picks_then_guess_phase(): void
    {
        $this->pvpConfigureForSingleRound('draft');
        $secretId = $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'draft');

        $handler = app(DraftRoundHandler::class);
        $init = $handler->initialize($match);
        $match->state = array_replace_recursive((array) $match->state, $init);

        $match->state = array_replace_recursive((array) $match->state, ['chooser_user_id' => (int) $u1->id]);

        $r0 = $handler->handleAction($match, (int) $u1->id, ['type' => 'choose_draft_order', 'first_picker_user_id' => (int) $u1->id]);
        $match->state = array_replace_recursive((array) $match->state, $r0->statePatch);
        $data = (array) ($match->state['round_data']['draft'] ?? []);

        $allowed = array_values((array) ($data['allowed_keys'] ?? []));
        $this->assertGreaterThanOrEqual(4, count($allowed));

        $k1 = $allowed[0];
        $k2 = $allowed[1];
        $k3 = $allowed[2];
        $k4 = $allowed[3];

        $r1 = $handler->handleAction($match, (int) $u1->id, ['type' => 'pick_hint', 'key' => $k1]);
        $match->state = array_replace_recursive((array) $match->state, $r1->statePatch);

        $r2 = $handler->handleAction($match, (int) $u2->id, ['type' => 'pick_hint', 'key' => $k2]);
        $match->state = array_replace_recursive((array) $match->state, $r2->statePatch);

        $r3 = $handler->handleAction($match, (int) $u2->id, ['type' => 'pick_hint', 'key' => $k3]);
        $match->state = array_replace_recursive((array) $match->state, $r3->statePatch);

        $r4 = $handler->handleAction($match, (int) $u1->id, ['type' => 'pick_hint', 'key' => $k4]);
        $this->assertFalse($r4->roundEnded);
        $match->state = array_replace_recursive((array) $match->state, $r4->statePatch);

        $stateU1 = $handler->publicState($match, (int) $u1->id);
        $stateU2 = $handler->publicState($match, (int) $u2->id);

        $this->assertSame('guess', (string) ($stateU1['phase'] ?? ''));
        $this->assertSame('guess', (string) ($stateU2['phase'] ?? ''));

        $h1 = array_keys((array) ($stateU1['revealed_hints'] ?? []));
        $h2 = array_keys((array) ($stateU2['revealed_hints'] ?? []));
        sort($h1);
        sort($h2);

        $this->assertSame([$k1, $k4], $h1);
        $this->assertSame([$k2, $k3], $h2);

        $g1 = $handler->handleAction($match, (int) $u1->id, ['type' => 'guess', 'player_id' => $secretId]);
        $match->state = array_replace_recursive((array) $match->state, $g1->statePatch);
        $g2 = $handler->handleAction($match, (int) $u2->id, ['type' => 'guess', 'player_id' => $secretId]);

        $this->assertTrue($g2->roundEnded);
        $this->assertContains($g2->roundWinnerUserId, [(int) $u1->id, (int) $u2->id]);
    }
}
