<?php

namespace Tests\Unit\Pvp;

use App\Services\Pvp\Rounds\GuessActionPayloadService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class GuessActionPayloadServiceTest extends TestCase
{
    use PvpTestHelper;

    public function test_require_guess_player_id_ok(): void
    {
        $svc = app(GuessActionPayloadService::class);
        $id = $svc->requireGuessPlayerId(['type' => 'guess', 'player_id' => 12]);
        $this->assertSame(12, $id);
    }

    public function test_invalid_type_aborts_422(): void
    {
        $svc = app(GuessActionPayloadService::class);
        $this->expectException(HttpException::class);
        $svc->requireGuessPlayerId(['type' => 'pick_hint', 'player_id' => 12]);
    }

    public function test_invalid_player_id_aborts_422(): void
    {
        $svc = app(GuessActionPayloadService::class);
        $this->expectException(HttpException::class);
        $svc->requireGuessPlayerId(['type' => 'guess', 'player_id' => 0]);
    }
}
