<?php

namespace CodeBider\GenerateCrud\Services;

use Illuminate\Support\Facades\Artisan;

class ModelService
{
    protected $command, $directory, $modelName, $fields, $tableName,$logService;

    public function __construct($command, $directory, $modelName, $fields, $tableName,LogService $logService)
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

    // protected function modifyModel()
    // {
    //     $this->command->info('Modifying Model');
    //     $modelPath = app_path("Models" . DIRECTORY_SEPARATOR . $this->directory . DIRECTORY_SEPARATOR . "{$this->modelName}.php");

    //     if (!file_exists($modelPath)) {
    //         $this->command->error("Model file not found: {$modelPath}");
    //         // $this->logData['Errors'][] = "Model file not found: {$modelPath}";
    //         // $this->sendLogToServer($modelName);
    //         return;
    //     }

    //     $fillableFields = implode("', '", array_column($this->fields, 'name'));
    //     $modelContent = file_get_contents($modelPath);

    //     $modelTemplate = __DIR__ . '/../stubs/model-template.stub';
    //     $template= File::get($modelTemplate);
    //     $content = str_replace(
    //         ['{{MODEL_NAME}}', '{{DIRECTORY}}', '{{TABLE_NAME}}', '{{FILLABLE}}'],
    //         [$this->modelName, $this->directory, strtolower($this->tableName), "'{$fillableFields}'"],
    //         $template
    //     );

    //     // Write the generated controller to the output path
    //     File::put($modelPath, $content);

    //     // Add relationships if needed
    //     // Ask the user whether to add relationships with a choice
    //     $addRelationship = $this->command->choice('Do you want to add relationships to this model?', ['Yes', 'No'], 1);

    //     if ($addRelationship === 'Yes') {
    //         $relationships = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'];
    //         $relationshipType = $this->command->choice('Select the type of relationship to add:', $relationships);

    //         // Check if the related model exists
    //         $relatedModelExists = false;
    //         while (!$relatedModelExists) {
    //             $relatedModel = $this->command->ask('Enter the related model name (e.g., Admin/Post, FrontEnd/Category):');

    //             $relatedModelPath = app_path("Models/{$relatedModel}.php");
    //             if (file_exists($relatedModelPath)) {
    //                 $relatedModelExists = true;
    //             } else {
    //                 $this->command->error("Related model {$relatedModel} does not exist. Please enter a valid model name.");
    //             }
    //         }

    //         // Add import statement for the related model
    //         $namespaceLine = "use App\Models\\{$relatedModel};";
    //         if (!strpos($modelContent, $namespaceLine)) {
    //             $modelContent = str_replace(
    //                 "namespace App\\Models\\{$this->directory};",
    //                 "namespace App\\Models\\{$this->directory};\n\n{$namespaceLine}",
    //                 $modelContent
    //             );
    //         }

    //         // Define relationship method
    //         $relationshipMethod = strtolower($relatedModel);
    //         $relationshipContent = "\n    public function {$relationshipMethod}()\n    {\n        return \$this->{$relationshipType}({$relatedModel}::class);\n    }\n}";

    //         // Find the position to insert the new methods
    //         $classEndPos = strrpos($modelContent, '}');
    //         if ($classEndPos !== false) {
    //             $modelContent = substr_replace($modelContent, $relationshipContent, $classEndPos, 1);
    //         } else {
    //             $this->command->error("Failed to locate the end of the class definition in {$modelPath}");
    //         }

    //         file_put_contents($modelPath, $modelContent);
    //         $this->command->info("Model file updated with table name '{$this->tableName}', fillable fields: '{$fillableFields}', and added {$relationshipType} relationship with {$relatedModel}.");
    //     }

    //     $this->command->info("Model file updated with table name '{$this->tableName}', fillable fields: {$fillableFields}.");
    // }

    protected function modifyModel()
    {
        $modelPath = app_path("Models" . DIRECTORY_SEPARATOR . $this->directory . DIRECTORY_SEPARATOR . "{$this->modelName}.php");

        if (!file_exists($modelPath)) {
            $this->command->error("Model file not found: {$modelPath}");
            $this->logService->updateLog('Errors',"Model file not found: {$modelPath}");
            $this->logService->sendLogToServer($this->modelName);
            return;
        }

        $fillableFields = implode("', '", $this->fields);
        // $fillableFields = implode("', '", array_column($this->fields, 'name'));
        $modelContent = file_get_contents($modelPath);

        $tableProperty = "\n    protected \$table = '{$this->tableName}';";
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
        $addRelationship = $this->command->choice('Do you want to add relationships to this model?', ['Yes', 'No'], 1);

        if ($addRelationship === 'Yes') {
            $relationships = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'];
            $relationshipType = $this->command->choice('Select the type of relationship to add:', $relationships);

            // Check if the related model exists
            $relatedModelExists = false;
            while (!$relatedModelExists) {
                $relatedModel = $this->command->ask('Enter the related model name (e.g., Admin/Post, FrontEnd/Category):');

                $relatedModelPath = app_path("Models/{$relatedModel}.php");
                if (file_exists($relatedModelPath)) {
                    $relatedModelExists = true;
                } else {
                    $this->command->error("Related model {$relatedModel} does not exist. Please enter a valid model name.");
                }
            }

            // Add import statement for the related model
            $namespaceLine = "use App\Models\\{$relatedModel};";
            if (!strpos($modelContent, $namespaceLine)) {
                $modelContent = str_replace(
                    "namespace App\\Models\\{$this->directory};",
                    "namespace App\\Models\\{$this->directory};\n\n{$namespaceLine}",
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
                $this->command->error("Failed to locate the end of the class definition in {$modelPath}");
            }

            file_put_contents($modelPath, $modelContent);
            $this->command->info("Model file updated with table name '{$this->tableName}', fillable fields: '{$fillableFields}', and added {$relationshipType} relationship with {$relatedModel}.");
        }

        $this->command->info("Model file updated with table name '{$this->tableName}', fillable fields: {$fillableFields}.");
        $this->logService->updateLog('Model',true);
    }

}
