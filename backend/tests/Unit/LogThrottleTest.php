<?php

namespace Tests\Unit;

use App\Http\Middleware\LogThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class LogThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_is_written_when_too_many_attempts(): void
    {
        RateLimiter::shouldReceive('limiter')
            ->once()
            ->with('game-guess')
            ->andReturn(function () {
                return [(object)['maxAttempts' => 5]];
            });

        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->andReturn(true);

        Log::shouldReceive('channel')
            ->once()
            ->with('guess')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->with('Throttle exceeded', Mockery::on(function ($context) {
                return isset($context['ip'], $context['route'], $context['game']);
            }));

        $middleware = new LogThrottle();

        $request = Request::create('/api/games/kcdle/guess', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $request->setRouteResolver(function () {
            return new class {
                public function parameter($key, $default = null)
                {
                    if ($key === 'game') {
                        return 'kcdle';
                    }

                    return $default;
                }
            };
        });

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        }, 'game-guess');

        $this->assertEquals(200, $response->getStatusCode());
    }
}
