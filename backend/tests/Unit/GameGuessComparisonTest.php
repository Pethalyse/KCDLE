<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\GameGuessController;
use App\Services\AchievementService;
use Illuminate\Contracts\Container\BindingResolutionException;
use ReflectionException;
use ReflectionMethod;
use Tests\TestCase;

class GameGuessComparisonTest extends TestCase
{
    protected GameGuessController $controller;

    /**
     * @throws BindingResolutionException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new GameGuessController($this->app->make(AchievementService::class));
    }

    /**
     * @throws ReflectionException
     */
    public function test_eq_returns_1_when_values_are_identical(): void
    {
        $ref = new ReflectionMethod(GameGuessController::class, 'eq');
        $ref->setAccessible(true);

        $this->assertSame(1, $ref->invoke($this->controller, 10, 10));
        $this->assertSame(0, $ref->invoke($this->controller, 10, 11));
        $this->assertSame(0, $ref->invoke($this->controller, null, 0));
    }

    /**
     * @throws ReflectionException
     */
    public function test_cmp_number(): void
    {
        $ref = new ReflectionMethod(GameGuessController::class, 'cmpNumber');
        $ref->setAccessible(true);

        $this->assertSame(1,  $ref->invoke($this->controller, 5, 5));
        $this->assertSame(-1, $ref->invoke($this->controller, 3, 5));
        $this->assertSame(0,  $ref->invoke($this->controller, 7, 3));
        $this->assertNull($ref->invoke($this->controller, null, 5));
        $this->assertNull($ref->invoke($this->controller, 5, null));
    }

    /**
     * @throws ReflectionException
     */
    public function test_cmp_date_with_strings_and_carbon(): void
    {
        $ref = new ReflectionMethod(GameGuessController::class, 'cmpDate');
        $ref->setAccessible(true);

        $this->assertSame(1, $ref->invoke($this->controller, '2000-01-01', '2000-01-01'));

        $this->assertSame(0, $ref->invoke($this->controller, '1999-01-01', '2000-01-01'));
        $this->assertSame(-1,  $ref->invoke($this->controller, '2001-01-01', '2000-01-01'));

        $this->assertNull($ref->invoke($this->controller, null, '2000-01-01'));
        $this->assertNull($ref->invoke($this->controller, '2000-01-01', null));
    }
}
