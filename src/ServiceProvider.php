<?php

namespace CodeBider\GenerateCrud;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/crud_generator.php',
            'crud_generator'
        );
        // Register bindings and commands
        $this->commands([
            commands\ScaffoldCommand::class,
            // commands\DeleteCrudCommand::class,
        ]);
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/crud_generator.php' => config_path('crud_generator.php'),
        ], 'crud-generator-config');
    }
}
