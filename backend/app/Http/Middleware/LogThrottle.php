<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class LogThrottle
{
    public function handle(Request $request, Closure $next, string $limiter)
    {
        $key = $request->ip() . '|' . $request->route('game');

        if (RateLimiter::tooManyAttempts($key, RateLimiter::limiter($limiter)($request)[0]->maxAttempts ?? 60)) {
            Log::channel('guess')->warning('Throttle exceeded', [
                'ip'      => $request->ip(),
                'route'   => $request->path(),
                'game'    => $request->route('game'),
            ]);
        }

        return $next($request);
    }
}
