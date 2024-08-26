<?php

namespace Hiqusol\GenerateCrud;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        // Register bindings and commands
        $this->commands([
            commands\ScaffoldCommand::class,
            commands\DeleteCrudCommand::class,
        ]);
    }

    public function boot()
    {
        // $this->publishes([
        //     __DIR__.'/config/generate-crud.php' => config_path('generate-crud.php'),
        // ], 'config');
    }
}
