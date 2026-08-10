<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Support serving the application from a sub-directory (e.g. https://host/demo/movie-api).
// Laravel routes (e.g. /api/login) are relative to the public directory. When the app is
// nested under a base path, Symfony must be told that base so it strips it from the URI.
// The environment isn't booted yet at this point, so read APP_URL directly from .env.
$basePath = '';
$envFile = __DIR__.'/../.env';

if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^APP_URL=(.*)$/', trim($line), $m)) {
            $basePath = rtrim((string) parse_url(trim($m[1], "\"' "), PHP_URL_PATH), '/');
            break;
        }
    }
}

if ($basePath !== '' && isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], $basePath.'/')) {
    $_SERVER['SCRIPT_NAME'] = $basePath.'/index.php';
    $_SERVER['PHP_SELF'] = $basePath.'/index.php';
}

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
