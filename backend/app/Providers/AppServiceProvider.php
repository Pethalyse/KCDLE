<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        if (!app()->runningInConsole() && request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }

        RateLimiter::for('game-guess', function (Request $request) {
            $key = $request->ip() . '|' . $request->route('game');
            $limits = [
                Limit::perMinute(60)->by($key),
                Limit::perHour(500)->by($key),
            ];

            $maxAttempts = $limits[0]->maxAttempts ?? 60;
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                Log::channel('guess')->warning('Throttle exceeded', [
                    'ip' => $request->ip(),
                    'game' => $request->route('game'),
                    'path' => $request->path(),
                ]);
            }

            return $limits;
        });

        RateLimiter::for('discord-bot-guess', function (Request $request) {
            $discordId = (string) $request->input('discord_id', '');
            $game = (string) $request->route('game');

            $key = ($discordId !== '' ? $discordId : (string) $request->ip()) . '|' . $game;

            return [
                Limit::perMinute(120)->by($key),
                Limit::perHour(1500)->by($key),
            ];
        });
    }
}
