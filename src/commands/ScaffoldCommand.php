<?php

namespace Hiqusol\GenerateCrud\commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ScaffoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:crud';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration, model, and controller with specified fields';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $modelName = $this->ask('Enter the model name');
        $tableName = Str::plural(Str::snake($modelName));
        $fields = $this->askFields();

        // Create migration
        Artisan::call('make:migration', [
            'name' => "create_{$tableName}_table",
            '--create' => $tableName,
        ]);

        // Modify migration file
        $this->info('Generating migration...');
        $this->modifyMigration($fields, $tableName);


        // Ask user to review the migration file
        if ($this->confirm('Do you want to review the migration file before proceeding?', true)) {
            $migrationFile = $this->getLatestMigrationFile();
            $migrationPath = database_path('migrations/' . $migrationFile);
            $this->info("Opening migration file: {$migrationPath}");
            // Open migration file in default editor (works on Unix-like systems)
            // system("nano {$migrationPath}"); // You can replace 'nano' with your preferred editor
            // For Windows, use 'notepad' or 'start'
            system("notepad {$migrationPath}");

            if ($this->confirm('Is the migration file correct?', true)) {
                $this->info('Migration file is correct.');
                Artisan::call('migrate');
                $this->info('Migrated Successfully.');
            } else {
                // Delete migration file if incorrect
                if (File::exists($migrationPath)) {
                    File::delete($migrationPath);
                    $this->info('Migration file deleted.');
                }
                $this->info('Crud Operation Skipped.');
                return; // Exit the command
            }
        }
        // Create model
        Artisan::call('make:model', ['name' => $modelName]);

        // Modify model file
        $this->info('Generating model...');
        $this->modifyModel($modelName, $tableName, $fields);

         // Generate Controller
         $this->info('Generating controller...');
         $this->generateController($modelName, $tableName);

          // Ask if views should be created
          if ($this->askGenerateViews()) {
            $this->info('Generating views...');
            $this->generateViews($tableName);
        }
            // Ask where to add routes
        $routesFile = $this->choice(
            'Where do you want to add routes?',
            ['api.php', 'web.php'],
            1
        );

        if ($routesFile === 'api.php') {
            $this->info('Generating API routes...');
            $this->generateApiRoutes($modelName);
        } elseif ($routesFile === 'web.php') {
            $this->info('Generating web routes...');
            $this->generateWebRoutes($modelName);
        }

        Artisan::call('optimize:clear');
        $this->info("Migration and cache cleared successfully!");

        $this->info("CRUD files for {$modelName} generated successfully!");

    }

    protected function askFields()
    {
        $fieldTypes = [
            'string', 'text','longText', 'integer', 'bigInteger','unsignedBigInteger','json','jsonb','enum', 'decimal', 'float','ipAddress', 'boolean', 'date', 'datetime', 'timestamp'
        ];
        $fields = [];
        while (true) {
            $fieldName = $this->ask('Enter field name (or press enter to stop adding fields)');
            if (empty($fieldName)) {
                break;
            }

            $fieldType =  $this->choice('Select field type', $fieldTypes,0);

            // Handle special cases
            $additionalOptions = [];
            $enumValues = null;
            if ($fieldType === 'enum') {
                $enumValues = $this->ask('Enter enum values separated by comma');
            }
            else if ($fieldType === 'decimal') {
                $precision = $this->ask('Enter precision (total digits)');
                $scale = $this->ask('Enter scale (digits after decimal)');
                $additionalOptions['precision'] = $precision;
                $additionalOptions['scale'] = $scale;
            } elseif ($fieldType === 'boolean') {
                $default = $this->ask('Enter default value (1 for true, 0 for false,default is 0)', '0');
                $additionalOptions['default'] = $default;
            }

            $nullable = $this->choice('Nullable ', ['Yes','No'],0) ;

            $fields[] = [
                'name' => $fieldName,
                'type' => $fieldType,
                'nullable' => $nullable,
                'options' => $additionalOptions,
                'enum' => $enumValues,
            ];
        }
        return $fields;
    }

    protected function modifyMigration($fields, $tableName)
    {
        $migrationFile = $this->getLatestMigrationFile();
        $migrationPath = database_path('migrations/' . $migrationFile);

        $fieldLines = '';
        foreach ($fields as $field) {
            $nullable = $field['nullable'] === 'Yes' ? '->nullable()' : '';

            // Handle special cases
            if ($field['type'] === 'decimal') {
                $precision = $field['options']['precision'];
                $scale = $field['options']['scale'];
                $fieldLines .= "\$table->decimal('{$field['name']}', $precision, $scale){$nullable};\n            ";
            } elseif ($field['type'] === 'boolean') {
                $default = $field['options']['default'];
                $fieldLines .= "\$table->boolean('{$field['name']}')->default($default){$nullable};\n            ";
            } elseif ($field['type'] === 'enum') {
                 $enumArray = implode("', '", explode(',', $field['enum']));
                $fieldLines .= "\$table->enum('{$field['name']}', ['{$enumArray}']);\n                   ";
            }
            else {
                $fieldLines .= "\$table->{$field['type']}('{$field['name']}'){$nullable};\n            ";
            }
        }

        $migrationContent = file_get_contents($migrationPath);
        $migrationContent = str_replace(
            '$table->id();',
            '$table->id();' . "\n            " . $fieldLines,
            $migrationContent
        );
        file_put_contents($migrationPath, $migrationContent);


        $this->info("fields are : " . implode(', ', array_column($fields, 'name')));
    }


    protected function getLatestMigrationFile()
    {
        $files = scandir(database_path('migrations'), SCANDIR_SORT_DESCENDING);
        return $files[0];
    }

    protected function modifyModel($modelName, $tableName, $fields)
    {
        $modelPath = app_path("Models/{$modelName}.php");

        if (!file_exists($modelPath)) {
            $this->error("Model file not found: {$modelPath}");
            return;
        }

        $fillableFields = implode("', '", array_column($fields, 'name'));
        $modelContent = file_get_contents($modelPath);

        $tableProperty = "\n    protected \$table = '{$tableName}';";
        $fillableProperty = "\n    protected \$fillable = ['{$fillableFields}'];";

        // Insert table name and fillable properties
        $modelContent = str_replace(
            "use HasFactory;",
            "use HasFactory;{$tableProperty}{$fillableProperty}",
            $modelContent
        );

        file_put_contents($modelPath, $modelContent);

        $this->info("Model file updated with table name '{$tableName}' and fillable fields: {$fillableFields}");
    }

    protected function generateController($modelName, $tableName)
    {
        $controllerName = $modelName . 'Controller';
        $templatePath = __DIR__ . '/../stubs/controller-template.stub';;
        $outputPath = app_path("Http/Controllers/{$controllerName}.php");

        if (!File::exists($templatePath)) {
            $this->error("Template file not found at {$templatePath}");
            return;
        }

        $template = File::get($templatePath);

        $content = str_replace(
            ['{{MODEL_NAME}}', '{{TABLE_NAME}}', '{{FOLDER_TABLE_NAME}}'],
            [$modelName, strtolower($tableName), ucfirst(str_replace('_', ' ', $tableName))],
            $template
        );

        File::put($outputPath, $content);

        $this->info("Controller created successfully at {$outputPath}");
    }

    protected function askGenerateViews()
    {
        return $this->choice('Do you want to generate views?', ['Yes', 'No'], 0) === 'Yes';
    }
    protected function generateViews($tableName)
    {
        $viewsPath = resource_path("views/{$tableName}");

        if (!File::exists($viewsPath)) {
            File::makeDirectory($viewsPath, 0755, true);
        }

        $viewPath = "{$viewsPath}/index.blade.php";
        if (!File::exists($viewPath)) {
            File::put($viewPath, "<!-- View for index -->\n");
            $this->info("Created view file: {$viewPath}");
        }
    }

    protected function generateApiRoutes($modelName)
    {
        $routesPath = base_path('routes/api.php');
        $controllerName = $modelName . 'Controller';
        $routeLine = "Route::apiResource('" . strtolower($modelName) . "', " . $modelName . "Controller::class);";

        $this->appendToFile($routesPath, $routeLine,$controllerName);
    }

    protected function generateWebRoutes($modelName)
    {
        $routesPath = base_path('routes/web.php');
        $controllerName = $modelName . 'Controller';
        $routeLine = "Route::resource('" . strtolower($modelName) . "', " . $modelName . "Controller::class);";

        $this->appendToFile($routesPath, $routeLine,$controllerName);
    }

    protected function appendToFile($filePath, $routeContent, $controllerName)
    {
        if (File::exists($filePath)) {
            // Read the existing content
            $fileContent = File::get($filePath);
            $importLine = "use App\Http\Controllers\\{$controllerName};";

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
            $fileContent .= "use App\Http\Controllers\\{$controllerName};\n\n";
            $fileContent .= implode("\n", $lines);
            $fileContent .= "\n" . $routeContent;

            // Write the updated content to the file
            File::put($filePath, $fileContent);
    $this->info("Added route to {$filePath}");
        } else {
            $this->error("Routes file not found at {$filePath}");
        }
    }


}
