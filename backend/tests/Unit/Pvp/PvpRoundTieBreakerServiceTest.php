<?php

namespace Tests\Unit\Pvp;

use App\Services\Pvp\PvpRoundTieBreakerService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Tests\TestCase;

class PvpRoundTieBreakerServiceTest extends TestCase
{
    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
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

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
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

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
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

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
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
