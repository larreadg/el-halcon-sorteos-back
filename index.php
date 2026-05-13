<?php

declare(strict_types=1);

define('APP_ROOT', __DIR__);

date_default_timezone_set('America/Argentina/Buenos_Aires');

foreach (file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

require_once APP_ROOT . '/flight/autoload.php';

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'Mike42\\Escpos\\')) {
        return;
    }
    $relative = str_replace(['Mike42\\Escpos\\', '\\'], ['', '/'], $class);
    $file = APP_ROOT . '/escpos/' . $relative . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
require_once APP_ROOT . '/app/core/ApiResponse.php';
require_once APP_ROOT . '/app/config/app.php';
require_once APP_ROOT . '/app/config/database.php';
require_once APP_ROOT . '/app/routes/api.php';

Flight::before('start', function () {
    $response = Flight::response();
    $response->header('Access-Control-Allow-Origin', '*');
    $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        $response->status(204)->send();
        exit;
    }
});

Flight::start();
