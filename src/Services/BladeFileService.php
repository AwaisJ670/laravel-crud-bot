<?php

namespace CodeBider\GenerateCrud\Services;

use Illuminate\Support\Facades\File;

class BladeFileService
{
    protected $command, $directory, $tableName,$logService,$modelName;

    public function __construct($command, $directory, $tableName,$modelName,LogService $logService)
    {
        $this->command = $command;
        $this->directory = $directory;
        $this->tableName = $tableName;
        $this->modelName = $modelName;
        $this->logService = $logService;

    }

    public function askGenerateViews()
    {
        return $this->command->choice('Do you want to generate views?', ['Yes', 'No'], 0) === 'Yes';
    }
    public function generateViews()
    {
        $this->command->info('Generating views...');
        $viewsPath = resource_path("views" . DIRECTORY_SEPARATOR . "{$this->directory}" . DIRECTORY_SEPARATOR . "{$this->tableName}");

        if (!File::exists($viewsPath)) {
            File::makeDirectory($viewsPath, 0755, true);
        }

        $viewPath = "{$viewsPath}/index.blade.php";
        $stubFileName = 'index-blade.stub';

        // Check if a custom stub exists in the Laravel base stubs path
        $customStubPath = base_path("stubs/{$stubFileName}");

        // Fallback to default package stub if custom one doesn't exist
        $templatePath = file_exists($customStubPath)
            ? $customStubPath
            : __DIR__ . "/../stubs/{$stubFileName}";

        if (!File::exists($templatePath)) {
            $this->command->error("Template file not found at {$templatePath}");
            $this->logService->updateLog('Errors',"Template file not found at {$templatePath}");
            $this->logService->sendLogToServer($this->modelName);
            return;
        }

        $template = File::get($templatePath);
        if (!File::exists($viewPath)) {
            File::put($viewPath, $template);
            $this->logService->updateLog('Views',true);
            $this->command->info("Created view file: {$viewPath}");
        }
    }
}
