<?php

declare(strict_types=1);

namespace app\test\config;

use app\config\Config;
use app\config\ConfigException;
use app\test\support\BaseTestCase;
use JsonException;

class ConfigTest extends BaseTestCase {
    public function testConstructStoresValues(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $config = new Config('host', 'db', 'user', 'password');
        // endregion.

        // region Assert.
        $this->assertSame('host', $config->dbHost);
        $this->assertSame('db', $config->dbName);
        $this->assertSame('user', $config->dbUser);
        $this->assertSame('password', $config->dbUserPassword);
        $this->assertNull($config->antiwordPath);
        $this->assertSame(Config::DEFAULT_ANTIWORD_MAPPING, $config->antiwordMapping);
        // endregion.
    }

    public function testFromFileReadsConfiguration(): void {
        // region Arrange.
        $configDir = $this->createTemporaryDirectory('simpledisk-config-');
        $toolsDir = $configDir . DIRECTORY_SEPARATOR . 'tools';
        mkdir($toolsDir, 0777, true);
        $antiwordPath = $toolsDir . DIRECTORY_SEPARATOR . 'antiword.exe';
        file_put_contents($antiwordPath, '');
        $configPath = $configDir . DIRECTORY_SEPARATOR . 'config_example.json';
        file_put_contents($configPath, json_encode([
                Config::DB_HOST_OPTION_NAME => 'localhost',
                Config::DB_NAME_OPTION_NAME => 'simpledisk',
                Config::DB_USER_OPTION_NAME => 'tester',
                Config::DB_USER_PASSWORD_OPTION_NAME => 'secret',
                Config::ANTIWORD_PATH_OPTION_NAME => 'tools/antiword.exe',
                Config::ANTIWORD_MAPPING_OPTION_NAME => 'UTF-8.txt',
        ], JSON_THROW_ON_ERROR));
        // endregion.

        // region Act.
        $config = Config::fromFile($configPath);
        // endregion.

        // region Assert.
        $this->assertSame('localhost', $config->dbHost);
        $this->assertSame('simpledisk', $config->dbName);
        $this->assertSame('tester', $config->dbUser);
        $this->assertSame('secret', $config->dbUserPassword);
        $this->assertSame($antiwordPath, $config->antiwordPath);
        $this->assertSame('UTF-8.txt', $config->antiwordMapping);
        // endregion.
    }

    public function testFromFileThrowsForEmptyFileName(): void {
        // region Arrange.
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('File name cannot be empty');
        // endregion.

        // region Act.
        Config::fromFile('');
        // endregion.
    }

    public function testFromFileThrowsForMissingFile(): void {
        // region Arrange.
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('File doesn\'t exist');
        // endregion.

        // region Act.
        Config::fromFile(__DIR__ . '/missing.json');
        // endregion.
    }

    public function testFromFileThrowsForInvalidJson(): void {
        // region Arrange.
        $configPath = $this->createTemporaryFile('{invalid json', '.json');
        // endregion.

        // region Act.
        try {
            Config::fromFile($configPath);
            $this->fail('Expected ConfigException was not thrown');
        } catch (ConfigException $e) {
            // region Assert.
            $this->assertSame('Failed to decode configuration file', $e->getMessage());
            $this->assertInstanceOf(JsonException::class, $e->getPrevious());
            // endregion.
        }
    }

    public function testFromFileThrowsWhenRequiredOptionIsMissing(): void {
        // region Arrange.
        $configPath = $this->createTemporaryFile(json_encode([
                Config::DB_HOST_OPTION_NAME => 'localhost',
                Config::DB_USER_OPTION_NAME => 'tester',
        ], JSON_THROW_ON_ERROR), '.json');
        // endregion.

        // region Act.
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Option dbName is not specified in the configuration file');
        Config::fromFile($configPath);
        // endregion.
    }
}
