<?php

namespace Hiqusol\GenerateCrud\commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class DeleteCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:crud';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete migration, model, controller, views, routes, and table for a given model';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $modelName = $this->ask('Enter the model name');
        $tableName = Str::plural(Str::snake($modelName));
        $migrationFile = $this->findMigrationFile($tableName);

        if (!$this->modelExists($modelName)) {
            $this->error("Model {$modelName} not found!");
            return;
        }

        if ($this->confirmDeletion("model", app_path("Models/{$modelName}.php"))) {
            $this->deleteModel($modelName);
        }

        if ($migrationFile && $this->confirmDeletion("migration", database_path("migrations/{$migrationFile}"))) {
            $this->deleteMigration($migrationFile);
            $this->dropTable($tableName);
        }

        if ($this->confirmDeletion("controller", app_path("Http/Controllers/{$modelName}Controller.php"))) {
            $this->deleteController($modelName);
        }

        if ($this->confirmDeletion("views", resource_path("views/{$tableName}"))) {
            $this->deleteViews($tableName);
        }

        if ($this->confirmDeletion("routes", "routes/web.php and routes/api.php")) {
            $this->deleteRoutes($modelName);
        }

        Artisan::call('optimize:clear');
        $this->info("CRUD files and table for {$modelName} deleted successfully!");
    }

    protected function modelExists($modelName)
    {
        return File::exists(app_path("Models/{$modelName}.php"));
    }

    protected function confirmDeletion($type, $path)
    {
        return $this->confirm("Do you really want to delete the {$type} at {$path}?",true);
    }

    protected function deleteModel($modelName)
    {
        File::delete(app_path("Models/{$modelName}.php"));
        $this->info("Model {$modelName} deleted.");
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
        File::delete(app_path("Http/Controllers/{$modelName}Controller.php"));
        $this->info("Controller {$modelName}Controller deleted.");
    }

    protected function deleteViews($tableName)
    {
        File::deleteDirectory(resource_path("views/{$tableName}"));
        $this->info("Views for {$tableName} deleted.");
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
            }
        }
    }

    protected function removeRouteFromFile($filePath, $modelName)
    {
        if (File::exists(base_path($filePath))) {
            $fileContent = File::get(base_path($filePath));
            $pattern = "/Route::resource\('".strtolower($modelName)."', '{$modelName}Controller'\);\n?/";
            $fileContent = preg_replace($pattern, '', $fileContent);
            File::put(base_path($filePath), $fileContent);
        }
    }

}
