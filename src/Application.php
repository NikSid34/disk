<?php


namespace app;


class Application {
    const CONFIG_FILE_NAME = 'config.json';
    const STORAGE_DIR_NAME = '../storage';
    const COMPONENTS_DIR_NAME = 'components';

    public static function getDocumentRoot(): string {
        return \dirname(__DIR__, 1);
    }

    public static function getComponentsFolder(): string {
        return getenv('SIMPLEDISK_COMPONENTS_DIR') ?: (Application::getDocumentRoot() . '/' . Application::COMPONENTS_DIR_NAME);
    }

    public static function getStorageDir(): string {
        return getenv('SIMPLEDISK_STORAGE_DIR') ?: (Application::getDocumentRoot() . '/' . Application::STORAGE_DIR_NAME);
    }

    public static function getConfigPath(): string {
        return Application::getDocumentRoot() . '/' . Application::CONFIG_FILE_NAME;
    }

    public static function getPathForUpload(int $userId): string {
        return Application::getStorageDir() . '/' . \date('y-m-d') . $userId;
    }
}
