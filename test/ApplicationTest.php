<?php

declare(strict_types=1);

namespace app\test;

use app\Application;
use app\test\support\BaseTestCase;

class ApplicationTest extends BaseTestCase {
    public function testReturnsProjectPaths(): void {
        // region Arrange.
        $documentRoot = dirname(__DIR__);
        // endregion.

        // region Act.
        $componentsFolder = Application::getComponentsFolder();
        $storageDir = Application::getStorageDir();
        $configPath = Application::getConfigPath();
        $uploadPath = Application::getPathForUpload(15);
        // endregion.

        // region Assert.
        $this->assertSame($documentRoot, Application::getDocumentRoot());
        $this->assertSame($documentRoot . '/components', $componentsFolder);
        $this->assertSame($documentRoot . '/../storage', $storageDir);
        $this->assertSame($documentRoot . '/config.json', $configPath);
        $this->assertSame($storageDir . '/' . date('y-m-d') . '15', $uploadPath);
        // endregion.
    }
}
