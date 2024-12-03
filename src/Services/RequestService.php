<?php

namespace CodeBider\GenerateCrud\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class RequestService
{
    protected $command, $directory, $modelName, $fields,$logService;

    public function __construct($command, $directory, $modelName, $fields,LogService $logService)
    {
        $this->command = $command;
        $this->directory = $directory;
        $this->modelName = $modelName;
        $this->fields = $fields;
        $this->logService = $logService;
    }



    public function generateRequestClass()
    {
        // Run artisan command to generate the request class
        Artisan::call('make:request', [
            'name' => "{$this->directory}/{$this->modelName}Request",
        ]);

        $requestClassName = "{$this->modelName}Request";
        $requestPath = app_path("Http" . DIRECTORY_SEPARATOR . "Requests" . DIRECTORY_SEPARATOR . $this->directory . DIRECTORY_SEPARATOR . "{$requestClassName}.php");

        // Check if the request class exists
        if (file_exists($requestPath)) {
            $stubPath = __DIR__ . '/../stubs/request-template.stub';
            if (!file_exists($stubPath)) {
                $this->command->error("Stub file not found at {$stubPath}");
                $this->logService->updateLog('Errors',"Stub file not found at {$stubPath}");
            }

            $stub = File::get($stubPath);
            $namespace = "App\Http\Requests\\{$this->directory}";

            // Replace placeholders in the stub
            $stub = str_replace(
                ['{{REQUEST_CLASS_NAME}}', '{{NAMESPACE}}'],
                [$requestClassName, $namespace],
                $stub
            );

            // Write the modified stub content to the request file
            File::put($requestPath, $stub);
            $this->command->info("Request class created: {$requestClassName}");
            // Read the newly generated request class file content
            $requestContent = File::get($requestPath);

            // Add validation rules to the request class
            $rules = [];
            foreach ($this->fields as $field) {
                $fieldName = $field['name'];

                // Determine if the field should be nullable or required
                $baseRule = $field['nullable'] === 'Yes' ? 'nullable' : 'required';

                // Add validation rules based on field type
                if ($field['type'] === 'enum') {
                    $rules[$fieldName] = $baseRule . '|in:' . implode(',', $field['enum']);
                } else if ($field['type'] === 'boolean') {
                    $rules[$fieldName] = $baseRule . '|boolean';
                } else if ($field['type'] === 'unsignedInteger' || $field['type'] === 'unsignedBigInteger') {
                    $rules[$fieldName] = $baseRule . '|integer|min:0';
                } else if ($field['type'] === 'integer' || $field['type'] === 'bigInteger') {
                    $rules[$fieldName] = $baseRule . '|integer';
                } else if ($field['type'] === 'string') {
                    $rules[$fieldName] = $baseRule . '|string';
                } else if ($field['type'] === 'text' || $field['type'] === 'longText') {
                    $rules[$fieldName] = $baseRule . '|string'; // Text fields are also treated as strings in validation
                } else if ($field['type'] === 'json' || $field['type'] === 'jsonb') {
                    $rules[$fieldName] = $baseRule . '|json';
                } else if ($field['type'] === 'decimal' || $field['type'] === 'float') {
                    $rules[$fieldName] = $baseRule . '|numeric';
                } else if ($field['type'] === 'ipAddress') {
                    $rules[$fieldName] = $baseRule . '|ip';
                } else if ($field['type'] === 'boolean') {
                    $rules[$fieldName] = $baseRule . '|boolean';
                } else if ($field['type'] === 'date') {
                    $rules[$fieldName] = $baseRule . '|date';
                } else if ($field['type'] === 'datetime' || $field['type'] === 'timestamp') {
                    $rules[$fieldName] = $baseRule . '|date_format:Y-m-d H:i:s';
                } else {
                    $rules[$fieldName] = $baseRule;
                }
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

            $this->command->info("Validation rules added to {$requestClassName}");
            $this->logService->updateLog('Request',true);
        } else {
            $this->command->error("No Request Found");
            $this->logService->updateLog('Errors',"No Request Found");
        }
    }
}
