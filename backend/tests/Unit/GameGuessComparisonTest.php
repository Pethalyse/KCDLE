<?php

namespace Tests\Unit;

use App\Services\Dle\PlayerComparisonService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Tests\TestCase;

class GameGuessComparisonTest extends TestCase
{
    protected PlayerComparisonService $comparison;

    /**
     * @throws BindingResolutionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->comparison = $this->app->make(PlayerComparisonService::class);
    }

    public function test_eq_returns_1_when_values_are_identical(): void
    {
        $this->assertSame(1, $this->comparison->eq(10, 10));
        $this->assertSame(0, $this->comparison->eq(10, 11));
        $this->assertSame(0, $this->comparison->eq(null, 0));
    }

    public function test_cmp_number(): void
    {
        $this->assertSame(1, $this->comparison->cmpNumber(5, 5));
        $this->assertSame(-1, $this->comparison->cmpNumber(3, 5));
        $this->assertSame(0, $this->comparison->cmpNumber(7, 3));
        $this->assertNull($this->comparison->cmpNumber(null, 5));
        $this->assertNull($this->comparison->cmpNumber(5, null));
    }

    public function test_cmp_date_with_strings_and_carbon(): void
    {
        $this->assertSame(1, $this->comparison->cmpDate('2000-01-01', '2000-01-01'));

        $this->assertSame(0, $this->comparison->cmpDate('1999-01-01', '2000-01-01'));
        $this->assertSame(-1, $this->comparison->cmpDate('2001-01-01', '2000-01-01'));

        $this->assertNull($this->comparison->cmpDate(null, '2000-01-01'));
        $this->assertNull($this->comparison->cmpDate('2000-01-01', null));
    }
}
