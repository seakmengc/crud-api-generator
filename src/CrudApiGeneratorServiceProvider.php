<?php

namespace Schheang\CrudApiGenerator;

use Illuminate\Support\ServiceProvider;
use Schheang\CrudApiGenerator\Console\Commands\MakeCrudApi;

class CrudApiGeneratorServiceProvider extends ServiceProvider
{

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeCrudApi::class
            ]);
        }
        $this->publishes([
            __DIR__ . '/config/crud-api.php' => config_path('crud-api.php')
        ], 'config');
    }

    public function register()
    {
    }
}
