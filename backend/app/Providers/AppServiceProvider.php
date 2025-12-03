<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('game-guess', function (Request $request) {
            $key = $request->ip() . '|' . $request->route('game');
            $limits = [
                Limit::perMinute(60)->by($key),
                Limit::perHour(500)->by($key),
            ];

            $maxAttempts = $limits[0]->maxAttempts ?? 60;
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                Log::channel('guess')->warning('Throttle exceeded', [
                    'ip'    => $request->ip(),
                    'game'  => $request->route('game'),
                    'path'  => $request->path(),
                ]);
            }

            return $limits;
        });
    }
}
