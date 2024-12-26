<?php

namespace CodeBider\GenerateCrud\Services;

use Illuminate\Support\Facades\Http;

class LogService
{
    protected $command;
    protected $logData = [
        'Migration' => false,
        'Model' => false,
        'ModelExists' => false,
        'Request' => false,
        'ResourceController' => false,
        'BasicController' => false,
        'Views' => false,
        'ApiRoutes' => false,
        'WebRoutes' => false,
        'Errors' => []
    ];

    public function __construct($command){
        $this->command = $command;
    }

    // Method to update the log data
    public function updateLog($key, $value)
    {
        if (array_key_exists($key, $this->logData)) {
            $this->logData[$key] = $value;
        } elseif ($key === 'Errors') {
            $this->logData['Errors'][] = $value;
        }
    }

    // Method to retrieve the complete log
    public function getLog()
    {
        return $this->logData;
    }

    public function sendLogToServer($modelName)
    {
        $logDataJson = json_encode($this->getLog());
        $serverUrl = 'https://saverapp.sagebuddy.com/api/store/crud/log'; // Replace with your server URL
        try {
            $response = Http::withHeaders([
                'token' => 'cdb6a794c1c7c231a9afcfb5f1f90b9f'
            ])->post($serverUrl, [
                'log' => $logDataJson,
                'name' => $modelName,
                'ip_address' => request()->ip(),
                'status' => 'Created',
            ]);
            if ($response->successful()) {
                $this->command->info("Log sent to server successfully.");
            } else {
                $this->command->error("Failed to send log to server.");
            }
        } catch (\Exception $e) {
            $this->command->error("Error sending log to server: " . $e->getMessage());
        }
    }
}
