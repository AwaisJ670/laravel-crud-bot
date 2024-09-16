<?php

namespace Hiqusol\GenerateCrud\commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

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
        // Get the directory name from config
        $this->directory = $this->option('dir') ?: Config::get('crud_generator.directory');
        $modelInput = $this->ask('Enter the model name');
        // Format the model name to StudlyCase
        $modelName = Str::studly(Str::singular(Str::lower($modelInput)));
        // Generate the table name by converting the model name to snake case and pluralizing it
        $tableName = Str::plural(Str::snake($modelName));


        if ($this->modelExists($modelName, $this->directory)) {
            $this->error("Model {$modelName} already exists!");
            return;
        }

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
        Artisan::call('make:model', [
            'name' => "{$this->directory}/{$modelName}"
        ]);

        // Modify model file
        $this->info('Generating model...');
        $this->modifyModel($modelName, $tableName, $fields, $this->directory);

        // Generate Controller
        $this->info('Generating controller...');
        if ($this->askGenerateController()) {
            $this->generateController($modelName, $tableName, $this->directory);
            // Ask if views should be created
            if ($this->askGenerateViews()) {
                $this->info('Generating views...');
                $this->generateViews($tableName, $this->directory);
            }
        }

        $this->info("Migration and cache cleared successfully!");

        $this->info("CRUD files for {$modelName} generated successfully!");
    }

    protected function modelExists($modelName, $directory)
    {
        return File::exists(app_path("Models" . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . "{$modelName}.php"));
    }

    protected function askFields()
    {
        $fieldTypes = [
            'string',
            'text',
            'longText',
            'integer',
            'unsignedInteger',
            'bigInteger',
            'unsignedBigInteger',
            'json',
            'jsonb',
            'enum',
            'decimal',
            'float',
            'ipAddress',
            'boolean',
            'date',
            'datetime',
            'timestamp',
        ];
        $fields = [];
        while (true) {
            $fieldName = $this->ask('Enter field name (or press enter to stop adding fields)');
            if (empty($fieldName)) {
                break;
            }

            $fieldType = $this->choice('Select field type', $fieldTypes, 0);

            // Handle special cases
            $additionalOptions = [];
            $enumValues = null;
            if ($fieldType === 'enum') {
                $enumValues = $this->ask('Enter enum values separated by comma');
                $nullable = $this->choice('Nullable ', ['Yes', 'No'], 0);
            } else if ($fieldType === 'decimal') {
                $precision = $this->ask('Enter precision (total digits)');
                $scale = $this->ask('Enter scale (digits after decimal)');
                $additionalOptions['precision'] = $precision;
                $additionalOptions['scale'] = $scale;
                $default = $this->ask('Enter default value ', '0.00');
                $additionalOptions['default'] = $default;
            } elseif ($fieldType === 'boolean') {
                $default = $this->ask('Enter default value (1 for true, 0 for false,default is 0)', '0');
                $additionalOptions['default'] = $default;
            } else {
                $nullable = $this->choice('Nullable ', ['Yes', 'No'], 0);
            }

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
                $default = $field['options']['default'];
                $fieldLines .= "\$table->decimal('{$field['name']}', $precision, $scale)->default('$default');\n            ";
            } elseif ($field['type'] === 'boolean') {
                $default = $field['options']['default'];
                $fieldLines .= "\$table->boolean('{$field['name']}')->default($default){$nullable};\n            ";
            } elseif ($field['type'] === 'enum') {
                $enumArray = implode("', '", explode(',', $field['enum']));
                $fieldLines .= "\$table->enum('{$field['name']}', ['{$enumArray}']);\n                   ";
            } else {
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

    protected function modifyModel($modelName, $tableName, $fields, $directory)
    {
        $modelPath = app_path("Models" . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . "{$modelName}.php");

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
        // Add relationships if needed
        // Ask the user whether to add relationships with a choice
        $addRelationship = $this->choice('Do you want to add relationships to this model?', ['Yes', 'No'], 1);

        if ($addRelationship === 'Yes') {
            $relationships = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'];
            $relationshipType = $this->choice('Select the type of relationship to add:', $relationships);

            // Check if the related model exists
            $relatedModelExists = false;
            while (!$relatedModelExists) {
                $relatedModel = $this->ask('Enter the related model name (e.g., Admin/Post, FrontEnd/Category):');

                $relatedModelPath = app_path("Models/{$relatedModel}.php");
                if (file_exists($relatedModelPath)) {
                    $relatedModelExists = true;
                } else {
                    $this->error("Related model {$relatedModel} does not exist. Please enter a valid model name.");
                }
            }

            // Add import statement for the related model
            $namespaceLine = "use App\Models\\{$relatedModel};";
            if (!strpos($modelContent, $namespaceLine)) {
                $modelContent = str_replace(
                    "namespace App\\Models\\{$directory};",
                    "namespace App\\Models\\{$directory};\n\n{$namespaceLine}",
                    $modelContent
                );
            }

            // Define relationship method
            $relationshipMethod = strtolower($relatedModel);
            $relationshipContent = "\n    public function {$relationshipMethod}()\n    {\n        return \$this->{$relationshipType}({$relatedModel}::class);\n    }\n}";

            // Find the position to insert the new methods
            $classEndPos = strrpos($modelContent, '}');
            if ($classEndPos !== false) {
                $modelContent = substr_replace($modelContent, $relationshipContent, $classEndPos, 1);
            } else {
                $this->error("Failed to locate the end of the class definition in {$modelPath}");
                return;
            }

            file_put_contents($modelPath, $modelContent);
            $this->info("Model file updated with table name '{$tableName}', fillable fields: '{$fillableFields}', and added {$relationshipType} relationship with {$relatedModel}.");
        }

        $this->info("Model file updated with table name '{$tableName}', fillable fields: {$fillableFields}.");
        $this->generateRequestClass($modelName, $fields, $directory);
    }

    protected function generateRequestClass($modelName, $fields, $directory)
    {
        // Run artisan command to generate the request class
        Artisan::call('make:request', [
            'name' => "{$directory}/{$modelName}Request"
        ]);

        $requestClassName = "{$modelName}Request";
        $requestPath = app_path("Http" . DIRECTORY_SEPARATOR . "Requests" . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . "{$requestClassName}.php");
        $this->info($requestPath);

        // Check if the request class exists
        if (file_exists($requestPath)) {
            $stubPath = __DIR__ . '/../stubs/request-template.stub';
            if (!file_exists($stubPath)) {
                $this->error("Stub file not found at {$stubPath}");
                return;
            }

            $stub = File::get($stubPath);
            $namespace = "App\Http\Requests\\{$directory}";

            // Replace placeholders in the stub
            $stub = str_replace(
                ['{{REQUEST_CLASS_NAME}}', '{{NAMESPACE}}'],
                [$requestClassName, $namespace],
                $stub
            );

            // Write the modified stub content to the request file
            File::put($requestPath, $stub);
            $this->info("Request class created: {$requestClassName}");
            // Read the newly generated request class file content
            $requestContent = File::get($requestPath);

            // Add validation rules to the request class
            $rules = [];
            foreach ($fields as $field) {
                $fieldName = $field['name'];
                // Add 'required' for non-nullable fields, otherwise add the field's type
                $rules[$fieldName] = $field['nullable'] === 'Yes' ? $field['type'] : 'required';
            }

            // Generate validation rules string
            $rulesArray = implode("\n\t\t\t", array_map(function ($field, $rule) {
                return "'{$field}' => '{$rule}',";
            }, array_keys($rules), $rules));

            // Replace the placeholder for validation rules in the request class
            if (strpos($requestContent, '// Add validation rules here') !== false) {
                $requestContent = str_replace(
                    '// Add validation rules here',
                    $rulesArray,
                    $requestContent
                );
            } else {
                // If the placeholder doesn't exist, append the rules
                $requestContent = str_replace(
                    "public function rules()\n    {\n        return [",
                    "public function rules()\n    {\n        return [\n\t\t\t{$rulesArray}",
                    $requestContent
                );
            }

            // Write the updated content back to the request file
            File::put($requestPath, $requestContent);

            $this->info("Validation rules added to {$requestClassName}");
        }
        else{
            $this->error("No Request Found");
        }
    }


    protected function generateController($modelName, $tableName, $directory)
    {
        $controllerName = $modelName . 'Controller';
        $outputPath =  app_path("Http" . DIRECTORY_SEPARATOR . "Controllers" . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . "{$controllerName}.php");

        // Ensure the directory exists
        $controllerDir = dirname($outputPath);
        if (!File::exists($controllerDir)) {
            File::makeDirectory($controllerDir, 0755, true);
        }
        // Ask user if they want a resource or basic controller
        $controllerType = $this->choice(
            'Do you want to create a resource controller or a basic controller?',
            ['resource', 'basic'],
            0
        );

        if ($controllerType === 'basic') {
            // Run Artisan command for basic controller
            Artisan::call('make:controller', [
                'name' => "{$directory}/{$controllerName}",
            ]);

            $this->info("Basic controller created successfully at {$outputPath}");
        } else {
            // Generate resource controller manually using the template
            $templatePath = __DIR__ . '/../stubs/resource-controller-template.stub';

            if (!File::exists($templatePath)) {
                $this->error("Template file not found at {$templatePath}");
                return;
            }

            $template = File::get($templatePath);

            // Replace placeholders with actual values
            $content = str_replace(
                ['{{MODEL_NAME}}', '{{DIRECTORY}}', '{{TABLE_NAME}}', '{{FOLDER_TABLE_NAME}}', '{{FileRequest}}'],
                [$modelName, $directory, strtolower($tableName), ucfirst(str_replace('_', ' ', $tableName)), "{$modelName}Request"],
                $template
            );

            // Write the generated controller to the output path
            File::put($outputPath, $content);

            $this->info("Resource controller created successfully at {$outputPath}");

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
        }
    }

    protected function askGenerateController()
    {
        return $this->choice('Do you want to generate Controller?', ['Yes', 'No'], 0) === 'Yes';
    }
    protected function askGenerateViews()
    {
        return $this->choice('Do you want to generate views?', ['Yes', 'No'], 0) === 'Yes';
    }
    protected function generateViews($tableName, $directory)
    {
        $viewsPath = resource_path("views".DIRECTORY_SEPARATOR."{$directory}".DIRECTORY_SEPARATOR."{$tableName}");

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

        $this->appendToFile($routesPath, $routeLine, $controllerName);
    }

    protected function generateWebRoutes($modelName)
    {
        $routesPath = base_path('routes/web.php');
        $controllerName = $modelName . 'Controller';
        $routeLine = "Route::resource('" . strtolower($modelName) . "', " . $modelName . "Controller::class);";

        $this->appendToFile($routesPath, $routeLine, $controllerName);
    }

    protected function appendToFile($filePath, $routeContent, $controllerName)
    {
        if (File::exists($filePath)) {
            $this->directory = Config::get('crud_generator.directory');
            // Read the existing content
            $fileContent = File::get($filePath);
            $importLine = "use App\Http\Controllers\\{$this->directory}\\{$controllerName};";

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
            $fileContent .= "use App\Http\Controllers\\{$this->directory}\\{$controllerName};\n\n";
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
