<?php

namespace CodeBider\GenerateCrud\Services;

use Illuminate\Support\Facades\File;

class BladeFileService
{
    protected $command, $directory, $tableName,$logService;

    public function __construct($command, $directory, $tableName,LogService $logService)
    {
        $this->command = $command;
        $this->directory = $directory;
        $this->tableName = $tableName;
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
        if (!File::exists($viewPath)) {
            File::put($viewPath, "<!-- View for index -->\n");
            $this->logService->updateLog('Views',true);
            $this->command->info("Created view file: {$viewPath}");
        }
    }
}
