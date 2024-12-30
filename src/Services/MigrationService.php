<?php

namespace CodeBider\GenerateCrud\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class MigrationService
{
    protected $command, $directory, $tableName, $logService, $modelName;

    public function __construct($command, $directory, $tableName, LogService $logService, $modelName)
    {
        $this->command = $command;
        $this->directory = $directory;
        $this->tableName = $tableName;
        $this->logService = $logService;
        $this->modelName = $modelName;
    }

    public function createMigration()
    {
        $this->command->info('Creating Migration File');
        // Create migration
        Artisan::call('make:migration', [
            'name' => "create_{$this->tableName}_table",
            '--create' => $this->tableName,
            '--path' => "database/migrations/{$this->directory}",
        ]);
    }
    public function askFields()
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
            $fieldName = $this->command->ask('Enter field name (or press enter to stop adding fields)');
            if (empty($fieldName)) {
                break;
            }

            $fieldType = $this->command->choice('Select field type', $fieldTypes, 0);

            // Handle special cases
            $additionalOptions = [];
            $enumValues = null;
            if ($fieldType === 'enum') {
                $enumValues = $this->command->ask('Enter enum values separated by comma');
                $nullable = $this->command->choice('Nullable ', ['Yes', 'No'], 0);
            } else if ($fieldType === 'decimal') {
                $precision = $this->command->ask('Enter precision (total digits)');
                $scale = $this->command->ask('Enter scale (digits after decimal)');
                $additionalOptions['precision'] = $precision;
                $additionalOptions['scale'] = $scale;
                $default = $this->command->ask('Enter default value ', '0.00');
                $additionalOptions['default'] = $default;
            } elseif ($fieldType === 'boolean') {
                $default = $this->command->ask('Enter default value (1 for true, 0 for false,default is 0)', '0');
                $additionalOptions['default'] = $default;
            } else {
                $nullable = $this->command->choice('Nullable ', ['Yes', 'No'], 0);
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

    public function modifyMigration($fields)
    {
        $this->command->info('Updating Migration File');
        $migrationFile = $this->getLatestMigrationFile();
        $migrationPath = database_path("migrations/{$this->directory}/" . $migrationFile);

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
                $fieldLines .= "\$table->boolean('{$field['name']}')->default($default);\n            ";
            } elseif ($field['type'] === 'enum') {
                $enumArray = implode("', '", explode(',', $field['enum']));
                $fieldLines .= "\$table->enum('{$field['name']}', ['{$enumArray}']);\n                   ";
            } else {
                $fieldLines .= "\$table->{$field['type']}('{$field['name']}'){$nullable};\n            ";
            }
        }

        // $fieldLines .= "\$table->softDeletes();\n            ";

        $migrationContent = file_get_contents($migrationPath);
        $migrationContent = str_replace(
            '$table->id();',
            '$table->id();' . "\n            " . $fieldLines,
            $migrationContent
        );
        file_put_contents($migrationPath, $migrationContent);

        $this->command->info("fields are : " . implode(', ', array_column($fields, 'name')));

        $newlyfields = $this->reviewMigration();
        return $newlyfields;
    }

    public function getLatestMigrationFile()
    {
        $files = scandir(database_path("migrations/{$this->directory}"), SCANDIR_SORT_DESCENDING);
        return $files[0];
    }

    protected function reviewMigration()
    {
        $reviewMigration = $this->command->choice('Do you want to preview the migration file before proceeding?', ['Yes', 'No'], 0);
        if ($reviewMigration === 'Yes') {
            $migrationFile = $this->getLatestMigrationFile();
            $migrationPath = database_path("migrations/{$this->directory}/" . $migrationFile);
            $this->command->info("Opening migration file: {$migrationPath}");
            // Open migration file in default editor (works on Unix-like systems)
            $OS = Config::get('crud_generator.OS') ?? 'Windows';
            if ($OS === 'Windows') {
                system("notepad {$migrationPath}");
            } else {
                system("nano {$migrationPath}"); // You can replace 'nano' with your preferred editor

            }
            $migrationFeedback = $this->command->choice('Is the migration file correct?', ['Yes', 'No'], 0);
            if ($migrationFeedback === 'Yes') {
                $this->command->info('Migration file is correct.');
                Artisan::call('migrate', [
                    '--path' => "database/migrations/{$this->directory}"
                ]);
                $this->logService->updateLog('Migration', true);
                $this->command->info('Migrated Successfully.');
                $newlyFields = DB::connection()->getSchemaBuilder()->getColumnListing($this->tableName);
                $columnsToRemove = Config::get('columnsToRemove') ?? ['id', 'created_at', 'updated_at'];
                // Remove the specified columns
                $newlyFields = array_diff($newlyFields, $columnsToRemove);
                return $newlyFields;
            } else {
                // Delete migration file if incorrect
                if (File::exists($migrationPath)) {
                    File::delete($migrationPath);
                    $this->command->info('Migration file deleted.');
                }
                $this->logService->updateLog('Errors', 'Migration file is incorrect');
                $this->command->info('Crud Operation Skipped.');
                $this->logService->sendLogToServer($this->modelName);
                return; // Exit the command
            }
        } else {
            Artisan::call('migrate', [
                '--path' => "database/migrations/{$this->directory}"
            ]);
            $this->logService->updateLog('Migration', true);
            $this->command->info('Migrated Successfully.');

            $newlyFields = DB::connection()->getSchemaBuilder()->getColumnListing($this->tableName);
            $columnsToRemove = Config::get('columnsToRemove') ?? ['id', 'created_at', 'updated_at'];
            // Remove the specified columns
            $newlyFields = array_diff($newlyFields, $columnsToRemove);
            return $newlyFields;
        }
    }
}
