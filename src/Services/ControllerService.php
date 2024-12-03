<?php

namespace CodeBider\GenerateCrud\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class ControllerService
{
    protected $command, $directory, $modelName,$tableName,$fields,$logService;

    public function __construct($command, $directory, $modelName,$tableName,$fields,LogService $logService)
    {
        $this->command = $command;
        $this->directory = $directory;
        $this->modelName = $modelName;
        $this->tableName = $tableName;
        $this->fields = $fields;
        $this->logService = $logService;

    }

    public function askGenerateController()
    {
        return $this->command->choice('Do you want to generate Controller?', ['Yes', 'No'], 0) === 'Yes';
    }

    public function generateController()
    {
        $this->command->info('Generating controller...');
        $controllerName = $this->modelName . 'Controller';
        $outputPath =  app_path("Http" . DIRECTORY_SEPARATOR . "Controllers" . DIRECTORY_SEPARATOR . $this->directory . DIRECTORY_SEPARATOR . "{$controllerName}.php");

        // Ensure the directory exists
        $controllerDir = dirname($outputPath);
        if (!File::exists($controllerDir)) {
            File::makeDirectory($controllerDir, 0755, true);
        }
        // Ask user if they want a resource or basic controller
        $controllerType = $this->command->choice(
            'Do you want to create a resource controller or a basic controller?',
            ['resource', 'basic'],
            0
        );

        if ($controllerType === 'basic') {
            // Run Artisan command for basic controller
            Artisan::call('make:controller', [
                'name' => "{$this->directory}/{$controllerName}",
            ]);
            $this->logService->updateLog('BasicController',true);
            $this->command->info("Basic controller created successfully at {$outputPath}");
        } else {
            // $requestService = new RequestService($this->command,$this->directory,$this->modelName,$this->fields,$this->logService);
            // $requestService->generateRequestClass();
            // Generate resource controller manually using the template
            $templatePath = __DIR__ . '/../stubs/resource-controller-template.stub';

            if (!File::exists($templatePath)) {
                $this->command->error("Template file not found at {$templatePath}");
                $this->logService->updateLog('Errors',"Template file not found at {$templatePath}");
                $this->logService->sendLogToServer($this->modelName);
                return;
            }

            $template = File::get($templatePath);
            $directoryIndexFile = str_replace(DIRECTORY_SEPARATOR, '.', $this->directory);
            // Replace placeholders with actual values
            $content = str_replace(
                ['{{MODEL_NAME}}', '{{DIRECTORY}}', '{{TABLE_NAME}}', '{{FOLDER_TABLE_NAME}}', '{{DIRECTORY_INDEX_FILE}}'],
                [$this->modelName, $this->directory, strtolower($this->tableName), ucfirst(str_replace('_', ' ', $this->tableName)),$directoryIndexFile],
                $template
            );

            // Write the generated controller to the output path
            File::put($outputPath, $content);
            $this->logService->updateLog('ResourceController',true);
            $this->command->info("Resource controller created successfully at {$outputPath}");

            // Ask where to add routes
            $routesFile = $this->command->choice(
                'Where do you want to add routes?',
                ['api.php', 'web.php'],
                1
            );

            if ($routesFile === 'api.php') {
                $this->command->info('Generating API routes...');
                $this->generateApiRoutes($this->modelName);
            } elseif ($routesFile === 'web.php') {
                $this->command->info('Generating web routes...');
                $this->generateWebRoutes($this->modelName);
            }

            Artisan::call('optimize:clear');
        }
    }

    protected function generateApiRoutes($modelName)
    {
        $routesPath = base_path('routes/api.php');
        $controllerName = $modelName . 'Controller';
        $routeLine = "Route::apiResource('" . Str::plural(Str::lower($modelName)) . "', " . $modelName . "Controller::class);";
        $this->logService->updateLog('ApiRoutes',true);
        $this->appendToFile($routesPath, $routeLine, $controllerName);
    }

    protected function generateWebRoutes($modelName)
    {
        $routesPath = base_path('routes/web.php');
        $controllerName = $modelName . 'Controller';
        $routeLine = "Route::resource('" . Str::plural(Str::lower($modelName)) . "', " . $modelName . "Controller::class);";
        $this->logService->updateLog('WebRoutes',true);
        $this->appendToFile($routesPath, $routeLine, $controllerName);
    }

    protected function appendToFile($filePath, $routeContent, $controllerName)
    {
        if (File::exists($filePath)) {
            // Read the existing content
            $importDirectory = str_replace(DIRECTORY_SEPARATOR, '\\', $this->directory);
            // Update the import line with the correct namespace formatting
            $importLine = "use App\Http\Controllers\\{$importDirectory}\\{$controllerName};";
            $fileContent = File::get($filePath);

            // Check if the file starts with <?php
            if (strpos($fileContent, "<?php") !== 0) {
                $fileContent = "<?php\n\n" . $fileContent;
            }

            // Ensure only one <?php and import lines are added
            $lines = explode("\n", $fileContent);
            $lines = array_filter($lines, function ($line) use ($importLine) {
                return $line !== "<?php" && $line !== $importLine;
            });

            // Reconstruct the file content
            $fileContent = "<?php\n";
            $fileContent .= "use App\Http\Controllers\\{$importDirectory}\\{$controllerName};\n\n";
            $fileContent .= implode("\n", $lines);
            $fileContent .= "\n" . $routeContent;

            // Write the updated content to the file
            File::put($filePath, $fileContent);
            $this->command->info("Added route to {$filePath}");
        } else {
            $this->command->error("Routes file not found at {$filePath}");
            $this->logService->updateLog('Errors',"Routes file not found at {$filePath}");
        }
    }
}
