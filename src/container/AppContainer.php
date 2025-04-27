<?php


namespace app\container;


use app\Application;
use app\config\Config;
use app\controller\ApiController;
use app\controller\DiskController;
use app\controller\UserController;
use app\repository\FileRepository;
use app\repository\FolderRepository;
use app\repository\UserRepository;
use app\service\AiService;
use app\service\factory\ConnectionFactory;
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
use PDO;


class AppContainer {
    private static ?self $default = null;

    private ?Config $config = null;
    private ?PDO $pdo = null;
    private ?UserRepository $userRepository = null;
    private ?FolderRepository $folderRepository = null;
    private ?FileRepository $fileRepository = null;
    private ?ThumbnailService $thumbnailService = null;
    private ?FileLifecycleService $fileLifecycleService = null;
    private ?FileContentExtractor $fileContentExtractor = null;
    private ?FileIndexingService $fileIndexingService = null;
    private ?UserService $userService = null;
    private ?FolderService $folderService = null;
    private ?FileService $fileService = null;
    private ?StorageService $storageService = null;
    private ?AiService $aiService = null;
    private ?QdrantService $qdrantService = null;
    private ?SearchIndexService $searchIndexService = null;
    private ?ComponentDependencyResolver $componentDependencyResolver = null;

    public function __construct(
            private readonly string $configPath
    ) {
    }

    public static function fromDefaultConfig(): self {
        return self::$default ??= new self(Application::getConfigPath());
    }

    public function pdo(): PDO {
        if ($this->pdo === null) {
            $this->pdo = (new ConnectionFactory($this->configPath))->create();
        }

        return $this->pdo;
    }

    public function userRepository(): UserRepository {
        return $this->userRepository ??= new UserRepository($this->pdo());
    }

    public function folderRepository(): FolderRepository {
        return $this->folderRepository ??= new FolderRepository($this->pdo());
    }

    public function fileRepository(): FileRepository {
        return $this->fileRepository ??= new FileRepository($this->pdo());
    }

    public function thumbnailService(): ThumbnailService {
        return $this->thumbnailService ??= new ThumbnailService();
    }

    public function fileContentExtractor(): FileContentExtractor {
        return $this->fileContentExtractor ??= new FileContentExtractor(
                $this->config()->antiwordPath,
                $this->config()->antiwordMapping
        );
    }

    public function fileLifecycleService(): FileLifecycleService {
        return $this->fileLifecycleService ??= new FileLifecycleService(
                $this->fileRepository(),
                $this->thumbnailService(),
                $this->searchIndexService()
        );
    }

    public function aiService(): AiService {
        return $this->aiService ??= new AiService($this->fileContentExtractor());
    }

    public function qdrantService(): QdrantService {
        return $this->qdrantService ??= new QdrantService();
    }

    public function searchIndexService(): SearchIndexService {
        return $this->searchIndexService ??= new SearchIndexService($this->fileContentExtractor());
    }

    public function fileIndexingService(): FileIndexingService {
        return $this->fileIndexingService ??= new FileIndexingService(
                $this->fileRepository(),
                $this->aiService(),
                $this->qdrantService(),
                $this->searchIndexService()
        );
    }

    public function userService(): UserService {
        return $this->userService ??= new UserService($this->userRepository());
    }

    public function folderService(): FolderService {
        return $this->folderService ??= new FolderService(
                $this->folderRepository(),
                $this->fileRepository(),
                $this->fileLifecycleService()
        );
    }

    public function fileService(): FileService {
        return $this->fileService ??= new FileService(
                $this->folderService(),
                $this->aiService(),
                $this->qdrantService(),
                $this->fileRepository(),
                $this->thumbnailService(),
                $this->fileLifecycleService(),
                $this->searchIndexService()
        );
    }

    public function storageService(): StorageService {
        return $this->storageService ??= new StorageService(
                $this->userService(),
                $this->fileRepository()
        );
    }

    public function componentDependencyResolver(): ComponentDependencyResolver {
        return $this->componentDependencyResolver ??= new ComponentDependencyResolver($this);
    }

    private function config(): Config {
        return $this->config ??= Config::fromFile($this->configPath);
    }

    public function diskController(): DiskController {
        return new DiskController(
                $this->fileService(),
                $this->folderService(),
                $this->userService(),
                $this->storageService()
        );
    }

    public function userController(): UserController {
        return new UserController(
                $this->userService()
        );
    }

    public function apiController(): ApiController {
        return new ApiController(
                $this->fileService(),
                $this->folderService()
        );
    }
}
