<?php

namespace CodeBider\GenerateCrud\commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use CodeBider\GenerateCrud\Services\ModelService;
use CodeBider\GenerateCrud\Services\BladeFileService;
use CodeBider\GenerateCrud\Services\MigrationService;
use CodeBider\GenerateCrud\Services\ControllerService;
use CodeBider\GenerateCrud\Services\LogService;

class ScaffoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:crud {--dir=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration, model, and controller with specified fields';

    private $directory, $logService;

    public function __construct()
    {
        parent::__construct();
        $this->logService = new LogService($this);
    }
    /**
     * Execute the console command.
     */
     public function handle()
    {
        try {
            // Get the directory name from config
            $this->directory = $this->option('dir') ?: Config::get('crud_generator.directory');
            $this->directory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->directory);
            $progressBar = $this->getOutput()->createProgressBar(7);
            $progressBar->start();
            $modelInput = $this->ask('Enter the model name');
            $modelName = Str::studly(Str::singular(Str::lower($modelInput)));
            $tableName = Str::plural(Str::snake($modelName));
            $progressBar->advance();
            $this->newLine();

            if ($this->modelExists($modelName, $this->directory)) {
                $this->logService->updateLog('ModelExists', true);
                $this->error("Model {$modelName} already exists!");
                $this->logService->updateLog('Errors', "Model {$modelName} already exists!");
                $this->logService->sendLogToServer($modelName);
                $progressBar->finish();
                return;
            }

            //Migration Service
            $migrationService = new MigrationService($this, $this->directory, $tableName, $this->logService, $modelName);

            $fields = $migrationService->askFields();
            $migrationService->createMigration();
            $fields = $migrationService->modifyMigration($fields);
            // $this->info(json_encode($fields));
            //end of Migration Service

            $progressBar->advance();
            $this->newLine();
            //Model Service
            $modelService = new ModelService($this, $this->directory, $modelName, $fields, $tableName, $this->logService);
            $modelService->createModel();
            $progressBar->advance();
            $this->newLine();

            // Generate Controller
            $controllerService = new ControllerService($this, $this->directory, $modelName, $tableName, $fields, $this->logService);
            $progressBar->advance();
            $this->newLine();
            $bladeFileService = new BladeFileService($this, $this->directory, $tableName,$modelName, $this->logService);

            if ($controllerService->askGenerateController()) {
                $controllerService->generateController();
                $progressBar->advance();
                $this->newLine();

                // Ask if views should be created
                if ($bladeFileService->askGenerateViews()) {
                    $bladeFileService->generateViews();
                    $progressBar->advance();
                    $this->newLine();
                }
            }

            $this->info("Migration and cache cleared successfully!");

            $this->info("CRUD files for {$modelName} generated successfully!");
            $this->logService->sendLogToServer($modelName);
            $progressBar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->logService->updateLog('Errors', "{$e->getMessage()}");
            $this->logService->sendLogToServer($modelName);
        }
    }

    protected function modelExists($modelName, $directory)
    {
        return File::exists(app_path("Models" . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . "{$modelName}.php"));
    }
}
