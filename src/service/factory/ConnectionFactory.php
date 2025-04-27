<?php


namespace app\service\factory;


use app\config\Config;
use app\config\ConfigException;
use PDO;
use PDOException;
use RuntimeException;


class ConnectionFactory {
    public function __construct(
            private readonly string $configPath
    ) {
    }

    public function create(): PDO {
        try {
            $config = Config::fromFile($this->configPath);
        } catch (ConfigException $e) {
            throw new RuntimeException('Failed to read database configuration', previous: $e);
        }

        $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $config->dbHost,
                $config->dbName
        );

        try {
            return new PDO($dsn, $config->dbUser, $config->dbUserPassword, array(
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
            ));
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to establish database connection', previous: $e);
        }
    }
}
