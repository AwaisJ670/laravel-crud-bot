<?php

namespace Hiqusol\GenerateCrud\commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class DeleteCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:crud {--dir=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete migration, model, controller, views, routes, and table for a given model';

    // Private directory variable
    private $directory;
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->directory = $this->option('dir') ?: Config::get('crud_generator.directory');
        $modelName = $this->ask('Enter the model name');
        $tableName = Str::plural(Str::snake($modelName));
        $migrationFile = $this->findMigrationFile($tableName);


        if (!$this->modelExists($modelName)) {
            $this->error("Model {$modelName} not found!");
            return;
        }


        if ($migrationFile && $this->confirmDeletion("migration", database_path("migrations/{$migrationFile}"))) {
            $this->deleteMigration($migrationFile);
            $this->dropTable($tableName);
        }

        if ($this->confirmDeletion("controller", app_path("Http" . DIRECTORY_SEPARATOR . "Controllers" . DIRECTORY_SEPARATOR . "{$this->directory}" . DIRECTORY_SEPARATOR . "{$modelName}Controller.php"))) {
            $this->deleteController($modelName);
        }

        if ($this->confirmDeletion("requests", app_path("Http" . DIRECTORY_SEPARATOR . "Requests" . DIRECTORY_SEPARATOR . "{$this->directory}" . DIRECTORY_SEPARATOR . "{$modelName}Request.php"))) {
            $this->deleteRequest($modelName);
        }

        if ($this->confirmDeletion("model", app_path("Models" . DIRECTORY_SEPARATOR . "{$this->directory}" . DIRECTORY_SEPARATOR . "{$modelName}.php"))) {
            $this->deleteModel($modelName);
        }
        Artisan::call('optimize:clear');
        $this->info("CRUD files and table for {$modelName} deleted successfully!");
    }

    protected function modelExists($modelName)
    {
        return File::exists(app_path("Models" . DIRECTORY_SEPARATOR . $this->directory . DIRECTORY_SEPARATOR . "{$modelName}.php"));
    }

    protected function confirmDeletion($type, $path)
    {
        return $this->confirm("Do you really want to delete the {$type} at {$path}?", true);
    }

    protected function deleteModel($modelName)
    {
        $modelPath = app_path("Models" . DIRECTORY_SEPARATOR . $this->directory . DIRECTORY_SEPARATOR . "{$modelName}.php");
        if (File::exists($modelPath)) {
            File::delete($modelPath);
            $this->info("Model {$modelName} deleted.");
        } else {
            $this->error("Model {$modelName} not found.");
        }
    }

    protected function findMigrationFile($tableName)
    {
        $migrations = File::files(database_path('migrations'));

        foreach ($migrations as $migration) {
            if (Str::contains($migration->getFilename(), $tableName)) {
                return $migration->getFilename();
            }
        }

        return null;
    }

    protected function deleteMigration($migrationFile)
    {
        File::delete(database_path("migrations/{$migrationFile}"));
        DB::table('migrations')->where('migration', Str::before($migrationFile, '.php'))->delete();
        $this->info("Migration file {$migrationFile} deleted.");
    }

    protected function dropTable($tableName)
    {
        if (Schema::hasTable($tableName)) {
            Schema::dropIfExists($tableName);
            $this->info("Table {$tableName} dropped.");
        }
    }

    protected function deleteController($modelName)
    {
        $controllerPath = app_path("Http" . DIRECTORY_SEPARATOR . "Controllers" . DIRECTORY_SEPARATOR . "{$this->directory}" . DIRECTORY_SEPARATOR . "{$modelName}Controller.php");
        $tableName = Str::plural(Str::snake($modelName));

        if (File::exists($controllerPath)) {
            File::delete($controllerPath);
            $this->info("{$modelName}Controller deleted.");

            if ($this->confirmDeletion("views", resource_path("views" . DIRECTORY_SEPARATOR . "{$this->directory}" . DIRECTORY_SEPARATOR . "{$tableName}"))) {
                $this->deleteViews($tableName);
            }

            if ($this->confirmDeletion("routes", "routes/web.php and routes/api.php")) {
                $this->deleteRoutes($modelName);
            }
        } else {
            $this->error("{$modelName}Controller does not exist.");
        }
    }
    protected function deleteRequest($modelName)
    {
        $requestPath = app_path("Http" . DIRECTORY_SEPARATOR . "Requests" . DIRECTORY_SEPARATOR . $this->directory . DIRECTORY_SEPARATOR . "{$modelName}Request.php");

        if (File::exists($requestPath)) {
            File::delete($requestPath);
            $this->info("{$modelName}Request deleted.");
        } else {
            $this->error("{$modelName}Request does not exist.");
        }
    }


    protected function deleteViews($tableName)
    {
        $resourcePath = resource_path("views" . DIRECTORY_SEPARATOR . "{$this->directory}" . DIRECTORY_SEPARATOR . "{$tableName}");
        if ($resourcePath) {
            File::deleteDirectory($resourcePath);
            $this->info("Views for {$tableName} deleted.");
        } else {
            $this->error("Views for {$tableName} does not exists");
        }
    }

    protected function deleteRoutes($modelName)
    {
        // Ask user which route files they want to delete routes from
        $files = ['web.php', 'api.php'];
        $choices = $this->choice(
            'Select the route files to delete the routes from (use comma to separate choices)',
            $files,
            null,
            null,
            true
        );

        foreach ($choices as $file) {
            if ($this->confirmDeletion("routes", "routes/{$file}")) {
                $this->removeRouteFromFile("routes/{$file}", $modelName);
                $this->info("Routes for {$modelName} deleted from {$file}.");
                Artisan::call('optimize:clear');
            }
        }
    }

    protected function removeRouteFromFile($filePath, $modelName)
    {
        if (File::exists(base_path($filePath))) {
            $fileContent = File::get(base_path($filePath));
            $controllerName = $modelName . 'Controller';

            // Simple pattern to match any route definitions using the controller
            $pattern = "/Route::(?:resource|apiResource|get|post|put|patch|delete|options|match|any)\(\s*['\"][^'\"]+['\"]\s*,\s*(?:'{$controllerName}@\w*'|{$controllerName}::class)\s*\);?\n?/";

            // Replace the matched routes
            $fileContent = preg_replace($pattern, '', $fileContent);


            // Pattern to match the import statement for the controller
            $importPattern = "/use\s+App\\\Http\\\Controllers\\\{$this->directory}\\\{$controllerName};\n?/";

            // Remove the import statement for the controller
            $fileContent = preg_replace($importPattern, '', $fileContent);
            // Write the modified content back to the file
            File::put(base_path($filePath), $fileContent);

            $this->info("Routes related to {$controllerName} removed from {$filePath}.");
        } else {
            $this->error("Routes file not found at {$filePath}");
        }
    }
}
