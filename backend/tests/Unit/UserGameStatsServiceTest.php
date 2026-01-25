<?php

namespace Tests\Unit;

use App\Services\UserGameStatsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class UserGameStatsServiceTest extends TestCase
{
    public function test_compute_streaks_uses_unique_days_for_max_and_current(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-24 12:00:00'));

        $service = new class extends UserGameStatsService {
            /**
             * @param Collection<int, Carbon|string> $dates
             * @return array{0:int, 1:int}
             */
            public function compute(Collection $dates): array
            {
                return $this->computeStreaks($dates);
            }
        };

        $dates = collect([
            Carbon::parse('2026-01-21'),
            Carbon::parse('2026-01-22'),
            Carbon::parse('2026-01-22'),
            Carbon::parse('2026-01-23'),
            Carbon::parse('2026-01-24'),
        ]);

        [$current, $max] = $service->compute($dates);

        $this->assertSame(4, $current);
        $this->assertSame(4, $max);

        Carbon::setTestNow();
    }
}
