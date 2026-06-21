<?php

namespace App\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;

trait EnvironmentVariable {

    /**
     * Update or create an environment variable in the .env file.
     * @param Request $request
     */
    function updateEnvVariable($key, $value)
    {
        // Path to the .env file
        $envPath = base_path('.env');

        // Read the current .env file content
        $envContent = File::get($envPath);

        // Create a pattern to find the target variable
        $pattern = "/^{$key}=.*/m";

        // Replace the target variable value
        $replacement = "{$key}={$value}";

        // Check if the variable exists in the .env file
        if (preg_match($pattern, $envContent)) {
            // Replace the existing value
            $envContent = preg_replace($pattern, $replacement, $envContent);
        } else {
            // Append the new variable at the end of the file
            $envContent .= "\n{$replacement}";
        }

        // Write the updated content back to the .env file
        File::put($envPath, $envContent);
    }
}
