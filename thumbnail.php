<?php


use app\container\AppContainer;
use app\enum\ThumbnailQualityLevel;


require 'vendor/autoload.php';
\session_start();

$hash = \trim((string) ($_GET['hash'] ?? ''));
$quality = ThumbnailQualityLevel::tryFrom((int) ($_GET['size'] ?? ThumbnailQualityLevel::Low->value))
        ?? ThumbnailQualityLevel::Low;

if ($hash === '') {
    \http_response_code(400);
    exit;
}

try {
    $container = AppContainer::fromDefaultConfig();
    $file = $container->fileService()->getFileByHash($hash);

    if ($file === null) {
        \http_response_code(404);
        exit;
    }

    $thumbnailPath = $container->thumbnailService()->ensureThumbnail($file, $quality);
    \header('Content-Type: image/jpeg');
    \header('Content-Length: ' . filesize($thumbnailPath));
    \readfile($thumbnailPath);
} catch (Throwable) {
    \http_response_code(404);
}

exit;
