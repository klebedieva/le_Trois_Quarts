<?php
// This file bootstraps the Symfony test environment.
// It loads Composer's autoloader, reads environment variables from the .env file and configures the application for the test environment (e.g. APP_ENV=test, APP_DEBUG).

use Symfony\Component\Dotenv\Dotenv; // Import the Dotenv class from the Symfony Dotenv component.

require dirname(__DIR__).'/vendor/autoload.php'; // Load the Composer autoloader.

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env'); // Load the environment variables from the .env file.
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000); // Set the umask to 0000. This allows the files to be created with the correct permissions.    
}
