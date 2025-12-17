<?php

namespace App\Providers;

use App\Services\Pvp\Rounds\ClassicRoundHandler;
use App\Services\Pvp\Rounds\DraftRoundHandler;
use App\Services\Pvp\Rounds\LockedInfosRoundHandler;
use App\Services\Pvp\Rounds\PvpRoundHandlerFactory;
use App\Services\Pvp\Rounds\WhoisRoundHandler;
use Illuminate\Support\ServiceProvider;

/**
 * Registers PvP services and round handlers.
 *
 * This provider centralizes handler registration for the round handler factory.
 */
class PvpServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(PvpRoundHandlerFactory::class, function () {
            return new PvpRoundHandlerFactory([
                app(ClassicRoundHandler::class),
                app(WhoisRoundHandler::class),
                app(DraftRoundHandler::class),
                app(LockedInfosRoundHandler::class),

            ]);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
    }
}
