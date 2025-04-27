<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'app\\test\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . $relativePath . '.php';

    if (is_file($filePath)) {
        require_once $filePath;
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
