<?php

namespace Baris\Generator;

use Illuminate\Support\ServiceProvider;

class BarisGeneratorProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->singleton('command.generate.models', function ($app) {
            return $app['Baris\Generator\Commands\MakeModelsCommand'];
        });

        $this->commands('command.generate.models');
    }
}