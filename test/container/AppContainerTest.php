<?php

declare(strict_types=1);

namespace app\test\container;

use app\Application;
use app\container\AppContainer;
use app\container\ComponentDependencyResolver;
use app\controller\ApiController;
use app\controller\DiskController;
use app\controller\UserController;
use app\repository\FileRepository;
use app\repository\FolderRepository;
use app\repository\UserRepository;
use app\service\AiService;
use app\service\FileContentExtractor;
use app\service\FileIndexingService;
use app\service\FileLifecycleService;
use app\service\FileService;
use app\service\FolderService;
use app\service\QdrantService;
use app\service\SearchIndexService;
use app\service\StorageService;
use app\service\ThumbnailService;
use app\service\UserService;
use app\test\support\BaseTestCase;
use app\test\support\FakePdo;

class AppContainerTest extends BaseTestCase {
    public function testFromDefaultConfigReturnsSingleton(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $first = AppContainer::fromDefaultConfig();
        $second = AppContainer::fromDefaultConfig();
        // endregion.

        // region Assert.
        $this->assertSame($first, $second);
        $this->assertSame(Application::getConfigPath(), $this->getPrivateProperty($first, 'configPath'));
        // endregion.
    }

    public function testReturnsInjectedPdoAndCachesRepositoriesAndServices(): void {
        // region Arrange.
        $container = new AppContainer('config_example.json');
        $pdo = new FakePdo();
        $this->setPrivateProperty($container, 'pdo', $pdo);
        // endregion.

        // region Act.
        $userRepository = $container->userRepository();
        $folderRepository = $container->folderRepository();
        $fileRepository = $container->fileRepository();
        $thumbnailService = $container->thumbnailService();
        $fileContentExtractor = $container->fileContentExtractor();
        $fileLifecycleService = $container->fileLifecycleService();
        $aiService = $container->aiService();
        $qdrantService = $container->qdrantService();
        $searchIndexService = $container->searchIndexService();
        $fileIndexingService = $container->fileIndexingService();
        $userService = $container->userService();
        $folderService = $container->folderService();
        $fileService = $container->fileService();
        $storageService = $container->storageService();
        $resolver = $container->componentDependencyResolver();
        // endregion.

        // region Assert.
        $this->assertSame($pdo, $container->pdo());
        $this->assertInstanceOf(UserRepository::class, $userRepository);
        $this->assertInstanceOf(FolderRepository::class, $folderRepository);
        $this->assertInstanceOf(FileRepository::class, $fileRepository);
        $this->assertInstanceOf(ThumbnailService::class, $thumbnailService);
        $this->assertInstanceOf(FileContentExtractor::class, $fileContentExtractor);
        $this->assertInstanceOf(FileLifecycleService::class, $fileLifecycleService);
        $this->assertInstanceOf(AiService::class, $aiService);
        $this->assertInstanceOf(QdrantService::class, $qdrantService);
        $this->assertInstanceOf(SearchIndexService::class, $searchIndexService);
        $this->assertInstanceOf(FileIndexingService::class, $fileIndexingService);
        $this->assertInstanceOf(UserService::class, $userService);
        $this->assertInstanceOf(FolderService::class, $folderService);
        $this->assertInstanceOf(FileService::class, $fileService);
        $this->assertInstanceOf(StorageService::class, $storageService);
        $this->assertInstanceOf(ComponentDependencyResolver::class, $resolver);

        $this->assertSame($userRepository, $container->userRepository());
        $this->assertSame($folderRepository, $container->folderRepository());
        $this->assertSame($fileRepository, $container->fileRepository());
        $this->assertSame($thumbnailService, $container->thumbnailService());
        $this->assertSame($fileContentExtractor, $container->fileContentExtractor());
        $this->assertSame($fileLifecycleService, $container->fileLifecycleService());
        $this->assertSame($aiService, $container->aiService());
        $this->assertSame($qdrantService, $container->qdrantService());
        $this->assertSame($searchIndexService, $container->searchIndexService());
        $this->assertSame($fileIndexingService, $container->fileIndexingService());
        $this->assertSame($userService, $container->userService());
        $this->assertSame($folderService, $container->folderService());
        $this->assertSame($fileService, $container->fileService());
        $this->assertSame($storageService, $container->storageService());
        $this->assertSame($resolver, $container->componentDependencyResolver());
        // endregion.
    }

    public function testCreatesControllers(): void {
        // region Arrange.
        $container = new AppContainer('config_example.json');
        $this->setPrivateProperty($container, 'pdo', new FakePdo());
        // endregion.

        // region Act.
        $diskController = $container->diskController();
        $userController = $container->userController();
        $apiController = $container->apiController();
        // endregion.

        // region Assert.
        $this->assertInstanceOf(DiskController::class, $diskController);
        $this->assertInstanceOf(UserController::class, $userController);
        $this->assertInstanceOf(ApiController::class, $apiController);
        $this->assertNotSame($diskController, $container->diskController());
        $this->assertNotSame($userController, $container->userController());
        $this->assertNotSame($apiController, $container->apiController());
        // endregion.
    }
}
