<?php


namespace app\config;


use JsonException;


class Config {
    const DB_HOST_OPTION_NAME = 'dbHost';
    const DB_NAME_OPTION_NAME = 'dbName';
    const DB_USER_OPTION_NAME = 'dbUser';
    const DB_USER_PASSWORD_OPTION_NAME = 'dbUserPassword';
    const ANTIWORD_PATH_OPTION_NAME = 'antiwordPath';
    const ANTIWORD_MAPPING_OPTION_NAME = 'antiwordMapping';
    const DEFAULT_ANTIWORD_MAPPING = 'UTF-8.txt';

    function __construct(
        public readonly string $dbHost,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbUserPassword,
        public readonly ?string $antiwordPath = null,
        public readonly string $antiwordMapping = self::DEFAULT_ANTIWORD_MAPPING
    ) {
    }

    /**
     * @throws ConfigException
     */
    static function fromFile(string $fileName): Config {
        if ($fileName === '') {
            throw new ConfigException('File name cannot be empty');
        }

        if (!is_file($fileName)) {
            throw new ConfigException('File doesn\'t exist');
        }

        $configJson = file_get_contents($fileName);
        if ($configJson === false) {
            throw new ConfigException('Failed to read configuration file');
        }

        try {
            $configParams = json_decode(json: $configJson, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ConfigException('Failed to decode configuration file', previous: $e);
        }

        if (!isset($configParams[Config::DB_HOST_OPTION_NAME]) || !strlen($configParams[Config::DB_HOST_OPTION_NAME])) {
            throw new ConfigException('Option ' . Config::DB_HOST_OPTION_NAME . ' is not specified in the configuration file');
        }

        if (!isset($configParams[Config::DB_NAME_OPTION_NAME]) || !strlen($configParams[Config::DB_NAME_OPTION_NAME])) {
            throw new ConfigException('Option ' . Config::DB_NAME_OPTION_NAME . ' is not specified in the configuration file');
        }

        if (!isset($configParams[Config::DB_USER_OPTION_NAME]) || !strlen($configParams[Config::DB_USER_OPTION_NAME])) {
            throw new ConfigException('Option ' . Config::DB_USER_OPTION_NAME . ' is not specified in the configuration file');
        }

        $antiwordPath = self::optionalString($configParams, self::ANTIWORD_PATH_OPTION_NAME);
        if ($antiwordPath !== null) {
            $antiwordPath = self::resolvePath($antiwordPath, dirname($fileName));
        }

        return new Config(
                $configParams[Config::DB_HOST_OPTION_NAME],
                $configParams[Config::DB_NAME_OPTION_NAME],
                $configParams[Config::DB_USER_OPTION_NAME],
                (string) ($configParams[Config::DB_USER_PASSWORD_OPTION_NAME] ?? ''),
                $antiwordPath,
                self::optionalString($configParams, self::ANTIWORD_MAPPING_OPTION_NAME) ?? self::DEFAULT_ANTIWORD_MAPPING
        );
    }

    /**
     * @param array<string, mixed> $configParams
     */
    private static function optionalString(array $configParams, string $optionName): ?string {
        if (!isset($configParams[$optionName])) {
            return null;
        }

        $value = trim((string) $configParams[$optionName]);

        return $value !== '' ? $value : null;
    }

    private static function resolvePath(string $path, string $baseDir): string {
        if (!self::isAbsolutePath($path)) {
            $path = $baseDir . DIRECTORY_SEPARATOR . $path;
        }

        $realPath = realpath($path);

        return $realPath !== false ? $realPath : $path;
    }

    private static function isAbsolutePath(string $path): bool {
        return str_starts_with($path, '/')
                || str_starts_with($path, '\\')
                || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
