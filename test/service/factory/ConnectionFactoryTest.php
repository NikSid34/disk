<?php

declare(strict_types=1);

namespace app\test\service\factory;

use app\config\ConfigException;
use app\service\factory\ConnectionFactory;
use app\test\support\BaseTestCase;
use RuntimeException;

class ConnectionFactoryTest extends BaseTestCase {
    public function testCreateThrowsWhenConfigCannotBeRead(): void {
        // region Arrange.
        $factory = new ConnectionFactory(__DIR__ . '/missing-config.json');
        // endregion.

        // region Act.
        try {
            $factory->create();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            // region Assert.
            $this->assertSame('Failed to read database configuration', $e->getMessage());
            $this->assertInstanceOf(ConfigException::class, $e->getPrevious());
            // endregion.
        }
    }

    public function testCreateThrowsWhenConnectionCannotBeEstablished(): void {
        // region Arrange.
        $configPath = $this->createTemporaryFile(json_encode([
                'dbHost' => '256.256.256.256',
                'dbName' => 'simpledisk',
                'dbUser' => 'root',
                'dbUserPassword' => '',
        ], JSON_THROW_ON_ERROR), '.json');
        $factory = new ConnectionFactory($configPath);
        // endregion.

        // region Act.
        try {
            $factory->create();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            // region Assert.
            $this->assertSame('Failed to establish database connection', $e->getMessage());
            $this->assertNotNull($e->getPrevious());
            // endregion.
        }
    }
}
