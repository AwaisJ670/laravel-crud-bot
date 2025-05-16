<?php

namespace CodeBider\GenerateCrud\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ModelService
{
    protected $command, $directory, $modelName, $fields, $tableName, $logService;

    public function __construct($command, $directory, $modelName, $fields, $tableName, LogService $logService)
    {
        $this->command = $command;
        $this->directory = $directory;
        $this->modelName = $modelName;
        $this->fields = $fields;
        $this->tableName = $tableName;
        $this->logService = $logService;
    }

    public function createModel()
    {
        $this->command->info('Creating Model');
        Artisan::call('make:model', [
            'name' => "{$this->directory}/{$this->modelName}",
        ]);

        $this->modifyModel();
    }

    protected function modifyModel()
    {
        $modelPath = app_path("Models" . DIRECTORY_SEPARATOR . $this->directory . DIRECTORY_SEPARATOR . "{$this->modelName}.php");

        if (!file_exists($modelPath)) {
            $this->command->error("Model file not found: {$modelPath}");
            $this->logService->updateLog('Errors', "Model file not found: {$modelPath}");
            $this->logService->sendLogToServer($this->modelName);
            return;
        }

        // Read existing model content
        $modelContent = file_get_contents($modelPath);

        // Prepare fillable fields
        $fillableFields = array_map(function ($field) {
            return "'$field'";
        }, $this->fields);

        $fillableFields = implode(', ', $fillableFields);

        // Read the template
        $stubFileName = 'model-template.stub';

        // Check if a custom stub exists in the Laravel base stubs path
        $customStubPath = base_path("stubs/{$stubFileName}");

        // Fallback to default package stub if custom one doesn't exist
        $templatePath = file_exists($customStubPath)
            ? $customStubPath
            : __DIR__ . "/../stubs/{$stubFileName}";


        if (!File::exists($templatePath)) {
            $this->command->error("Model Template file not found at {$templatePath}");
            $this->logService->updateLog('Errors', "Model Template file not found at {$templatePath}");
            $this->logService->sendLogToServer($this->modelName);
            return;
        }

        $template = File::get($templatePath);

        // Replace placeholders with actual values
        $content = str_replace(
            ['{{MODEL_NAME}}', '{{DIRECTORY}}', '{{TABLE_NAME}}', '{{FILLABLE}}'],
            [$this->modelName, $this->directory, $this->tableName, $fillableFields],
            $template
        );

        // Ask the user whether to add relationships with a choice
        $addRelationship = $this->command->choice('Do you want to add relationships to this model?', ['Yes', 'No'], 1);

        if ($addRelationship === 'Yes') {
            $relationships = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'];
            $relationshipType = $this->command->choice('Select the type of relationship to add:', $relationships);

            // Check if the related model exists
            $relatedModelExists = false;
            while (!$relatedModelExists) {
                $relatedModel = $this->command->ask('Enter the related model name (e.g., Admin/Post, FrontEnd/Category):');

                // Handle nested directories if provided (e.g., Admin/Post)
                $relatedModelPath = app_path("Models/{$relatedModel}.php");
                if (file_exists($relatedModelPath)) {
                    $relatedModelExists = true;
                } else {
                    $this->command->error("Related model {$relatedModel} does not exist. Please enter a valid model name.");
                }
            }

            // Extract the base model name in case of nested directories
            $relatedModelBase = basename(str_replace('\\', '/', $relatedModel));

            // Add import statement for the related model if not already present
            $namespaceLine = "use App\Models\\{$relatedModel};";
            if (strpos($content, $namespaceLine) === false) {
                // Find the position to insert the use statement, after the namespace declaration
                $namespaceDeclaration = "namespace App\\Models\\{$this->directory};";
                $content = str_replace(
                    $namespaceDeclaration,
                    "{$namespaceDeclaration}\n\n{$namespaceLine}",
                    $content
                );
            }

            // Define relationship method
            $relationshipMethod = lcfirst($relatedModelBase);
            $relationshipContent = "\n    public function {$relationshipMethod}()\n    {\n        return \$this->{$relationshipType}({$relatedModelBase}::class);\n    }\n";

            // Insert the relationship method before the last closing brace
            $classEndPos = strrpos($content, '}');
            if ($classEndPos !== false) {
                // Ensure there's a newline before inserting
                $content = substr_replace($content, $relationshipContent, $classEndPos, 0);
            } else {
                $this->command->error("Failed to locate the end of the class definition in {$modelPath}");
                return;
            }

            // Write back the updated content
            File::put($modelPath, $content);
            $this->command->info("Model file updated with table name '{$this->tableName}', fillable fields: '{$fillableFields}', and added '{$relationshipType}' relationship with '{$relatedModelBase}'.");
            $this->logService->updateLog('Model', true);
        } else {
            // Write back the content without relationships
            File::put($modelPath, $content);
            $this->command->info("Model file updated with table name '{$this->tableName}', fillable fields: {$fillableFields}.");
            $this->logService->updateLog('Model', true);
        }
    }
}
