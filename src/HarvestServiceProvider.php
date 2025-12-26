<?php

namespace Dennenboom\Harvest;

use Dennenboom\Harvest\Commands\DeployCommand;
use Illuminate\Support\ServiceProvider;

class HarvestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/harvest.php',
            'harvest'
        );
    }

    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../config/harvest.php' => config_path('harvest.php'),
            ],
            'harvest-config'
        );

        if ($this->app->runningInConsole()) {
            $this->commands(
                [
                    DeployCommand::class,
                ]
            );
        }
    }
}
