<?php

namespace Tests\Unit\Pvp;

use App\Services\Pvp\PvpRoundTieBreakerService;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpRoundTieBreakerServiceTest extends TestCase
{
    use PvpTestHelper;

    public function test_fewer_guesses_wins(): void
    {
        $svc = app(PvpRoundTieBreakerService::class);

        $winner = $svc->resolve(
            10,
            ['guess_count' => 3, 'started_at' => '2026-01-01T10:00:00Z', 'solved_at' => '2026-01-01T10:00:10Z'],
            20,
            ['guess_count' => 2, 'started_at' => '2026-01-01T10:00:00Z', 'solved_at' => '2026-01-01T10:00:20Z'],
        );

        $this->assertSame(20, $winner);
    }

    public function test_equal_guesses_faster_solve_wins(): void
    {
        $svc = app(PvpRoundTieBreakerService::class);

        $winner = $svc->resolve(
            10,
            ['guess_count' => 2, 'started_at' => '2026-01-01T10:00:00Z', 'solved_at' => '2026-01-01T10:00:30Z'],
            20,
            ['guess_count' => 2, 'started_at' => '2026-01-01T10:00:00Z', 'solved_at' => '2026-01-01T10:00:10Z'],
        );

        $this->assertSame(20, $winner);
    }

    public function test_equal_guesses_and_time_lowest_user_id_wins(): void
    {
        $svc = app(PvpRoundTieBreakerService::class);

        $winner = $svc->resolve(
            10,
            ['guess_count' => 2, 'started_at' => '2026-01-01T10:00:00Z', 'solved_at' => '2026-01-01T10:00:10Z'],
            20,
            ['guess_count' => 2, 'started_at' => '2026-01-01T10:00:00Z', 'solved_at' => '2026-01-01T10:00:10Z'],
        );

        $this->assertSame(10, $winner);
    }

    public function test_invalid_timestamps_fall_back_to_lowest_user_id(): void
    {
        $svc = app(PvpRoundTieBreakerService::class);

        $winner = $svc->resolve(
            10,
            ['guess_count' => 2, 'started_at' => 'bad', 'solved_at' => 'bad'],
            20,
            ['guess_count' => 2, 'started_at' => 'bad', 'solved_at' => 'bad'],
        );

        $this->assertSame(10, $winner);
    }
}
