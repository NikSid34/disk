<?php


namespace app\controller;


use app\container\AppContainer;
use app\service\FileService;
use app\service\FolderService;
use app\service\StorageService;
use app\service\UserService;
use app\ui\Component;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Stream;
use Throwable;


class DiskController {
    function __construct(
            private readonly FileService $fileService,
            private readonly FolderService $folderService,
            private readonly UserService $userService,
            private readonly StorageService $storageService
    ) {
    }

    static function instance(): self {
        return AppContainer::fromDefaultConfig()->diskController();
    }

    function files(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        try {
            $queryParams = $request->getQueryParams();
            $folderHash = !empty($queryParams['folder']) ? $queryParams['folder'] : null;
            $sortBy = strtolower((string) ($queryParams['sortBy'] ?? 'date'));
            $sortDirection = strtolower((string) ($queryParams['sortDirection'] ?? 'desc'));
            $viewMode = strtolower((string) ($queryParams['viewMode'] ?? 'grid'));
            $search = trim($queryParams['search'] ?? '');
            $smartSearch = isset($queryParams['smartSearch']) && (int) $queryParams['smartSearch'] === 1;

            if (!in_array($sortBy, ['name', 'size', 'date'], true)) {
                $sortBy = 'date';
            }

            if (!in_array($sortDirection, ['asc', 'desc'], true)) {
                $sortDirection = 'desc';
            }

            if (!in_array($viewMode, ['grid', 'table'], true)) {
                $viewMode = 'grid';
            }

            $userId = $this->userService::getCurrentUserId();
            $userLogin = $this->userService->getLogin();

            $files = $this->fileService->getUserFiles($userId, $folderHash, $sortBy, $sortDirection, $search, $smartSearch);
            $folders = $this->folderService->getUserFolders($userId, $folderHash, $sortBy, $sortDirection, $search);

            $currentFolderName = $folderHash
                    ? $this->folderService->getFolderName($folderHash, $userId)
                    : $this->folderService::BASE_FOLDER_NAME;

            $breadcrumbs = $folderHash
                    ? $this->folderService->getBreadcrumbs($folderHash, $userId)
                    : [];

            $html = Component::render('simpledisk:layout.app', [
                    'title' => 'SimpleDisk',
                    'headerParams' => [
                            'search' => $search,
                            'smartSearch' => $smartSearch,
                            'login' => $userLogin,
                            'currentFolderHash' => $folderHash,
                            'sortBy' => $sortBy,
                            'sortDirection' => $sortDirection,
                            'viewMode' => $viewMode,
                    ],
                    'pageComponent' => 'simpledisk:disk.page',
                    'pageParams' => [
                            'files' => $files,
                            'folders' => $folders,
                            'sortBy' => $sortBy,
                            'sortDirection' => $sortDirection,
                            'currentFolderHash' => $folderHash,
                            'currentFolderName' => empty($search)
                                    ? $currentFolderName
                                    : ($smartSearch ? "Умный поиск по \"$search\"" : "Поиск по \"$search\""),
                            'usedSpace' => $this->storageService->getUserUsedSpace($userId),
                            'totalSpace' => $this->storageService->getUserTotalSpace($userId),
                            'breadcrumbs' => $breadcrumbs,
                            'search' => $search,
                            'smartSearch' => $smartSearch,
                            'viewMode' => $viewMode,
                    ],
            ]);

            $response->getBody()->write($html);

            return $response;
        } catch (Throwable) {
            $response->getBody()->write($this->renderErrorPage());

            return $response->withStatus(500);
        }
    }

    function uploadFile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $folderHash = isset($data['folderHash']) && mb_strlen(trim($data['folderHash']))
                ? trim($data['folderHash']) : null;
        $uploadedFiles = $request->getUploadedFiles();

        try {
            foreach ($uploadedFiles['files'] as $uploadedFile) {
                if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                    $this->fileService->handleFileUpload($this->userService::getCurrentUserId(), $uploadedFile, $folderHash);
                }
            }

            return $response->withStatus(200);
        } catch (Throwable $e) {
            $data = ['error' => 'Failed to upload file: ' . $e->getMessage()];
            $response->getBody()->write(json_encode($data));

            return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(500);
        }
    }

    function createFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = json_decode(json: $request->getBody()->getContents(), associative: true, flags: JSON_THROW_ON_ERROR);
        $folderName = isset($data['name']) && mb_strlen(trim($data['name']))
                ? trim($data['name']) : null;
        $parentHash = isset($data['parentHash']) && mb_strlen(trim($data['parentHash']))
                ? trim($data['parentHash']) : null;

        if (!$folderName) {
            $response->getBody()->write('Invalid folder name');

            return $response->withStatus(400);
        }

        $this->folderService->create($this->userService::getCurrentUserId(), $folderName, $parentHash);

        return $response->withStatus(200);
    }

    function statistics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $folderHash = !empty($queryParams['folder']) ? $queryParams['folder'] : null;
        $sortBy = $queryParams['sortBy'] ?? null;
        $sortDirection = $queryParams['sortDirection'] ?? null;

        $userId = $this->userService::getCurrentUserId();
        $userLogin = $this->userService->getLogin();

        $files = $this->fileService->getUserFiles($userId, $folderHash, $sortBy, $sortDirection, '');
        $totalFiles = $this->fileService->getUserTotalFiles($userId);
        $uniqFiles = $this->fileService->getUserUniqFiles($userId);
        $largestFiles = $this->fileService->getTopLargestFiles($userId);
        $publicFiles = $this->fileService->getUserPublicFiles($userId);

        $folders = $this->folderService->getUserFolders($userId, $folderHash, $sortBy, $sortDirection, '');
        $publicFolders = $this->folderService->getUserPublicFolders($userId);

        $currentFolderName = $folderHash
                ? $this->folderService->getFolderName($folderHash, $userId)
                : $this->folderService::BASE_FOLDER_NAME;

        $breadcrumbs = $folderHash
                ? $this->folderService->getBreadcrumbs($folderHash, $userId)
                : [];

        $html = Component::render('simpledisk:layout.app', [
                'title' => 'Статистика | SimpleDisk',
                'headerParams' => [
                        'login' => $userLogin,
                        'search' => '',
                        'smartSearch' => false,
                ],
                'pageComponent' => 'simpledisk:statistics.page',
                'pageParams' => [
                        'files' => $files,
                        'totalFiles' => $totalFiles,
                        'uniqFiles' => $uniqFiles,
                        'largestFiles' => $largestFiles,
                        'publicFiles' => $publicFiles,
                        'folders' => $folders,
                        'publicFolders' => $publicFolders,
                        'sortBy' => $sortBy,
                        'sortDirection' => $sortDirection,
                        'currentFolderHash' => $folderHash,
                        'currentFolderName' => $currentFolderName,
                        'usedSpace' => $this->storageService->getUserUsedSpace($userId),
                        'totalSpace' => $this->storageService->getUserTotalSpace($userId),
                        'breadcrumbs' => $breadcrumbs,
                ],
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    function trash(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $folderHash = !empty($queryParams['folder']) ? $queryParams['folder'] : null;
        $sortBy = $queryParams['sortBy'] ?? null;
        $sortDirection = $queryParams['sortDirection'] ?? null;

        $userId = $this->userService::getCurrentUserId();
        $userLogin = $this->userService->getLogin();

        $files = $this->fileService->getTrashedFiles($userId, $folderHash, $sortBy, $sortDirection);
        $folders = $this->folderService->getTrashedFolders($userId, $folderHash, $sortBy, $sortDirection);

        $currentFolderName = $folderHash
                ? $this->folderService->getFolderName($folderHash, $userId)
                : 'Корзина';

        $breadcrumbs = $folderHash
                ? $this->folderService->getBreadcrumbs($folderHash, $userId)
                : [];

        $html = Component::render('simpledisk:layout.app', [
                'title' => 'Корзина | SimpleDisk',
                'headerParams' => [
                        'login' => $userLogin,
                        'search' => '',
                        'smartSearch' => false,
                ],
                'pageComponent' => 'simpledisk:trash.page',
                'pageParams' => [
                        'files' => $files,
                        'folders' => $folders,
                        'sortBy' => $sortBy,
                        'sortDirection' => $sortDirection,
                        'currentFolderHash' => $folderHash,
                        'currentFolderName' => $currentFolderName,
                        'usedSpace' => $this->storageService->getUserUsedSpace($userId),
                        'totalSpace' => $this->storageService->getUserTotalSpace($userId),
                        'breadcrumbs' => $breadcrumbs,
                ],
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    function deleteFile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $hash = !empty($data['hash']) ? (string) $data['hash'] : null;

        $this->fileService->deleteForever($hash);

        return $this->redirectToTrash($response, $data['returnFolderHash'] ?? null);
    }

    function restoreFile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $hash = !empty($data['hash']) ? (string) $data['hash'] : null;

        $this->fileService->restoreFromTrash($hash);

        return $this->redirectToTrash($response, $data['returnFolderHash'] ?? null);
    }

    function deleteFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $hash = !empty($data['hash']) ? (string) $data['hash'] : null;

        $this->folderService->deleteForever($hash);

        return $this->redirectToTrash($response, $data['returnFolderHash'] ?? null);
    }

    function restoreFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $hash = !empty($data['hash']) ? (string) $data['hash'] : null;

        $this->folderService->restoreFromTrash($hash);

        return $this->redirectToTrash($response, $data['returnFolderHash'] ?? null);
    }

    function clearTrash(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->requestData($request);
        $folderHash = !empty($data['folderHash']) ? (string) $data['folderHash'] : null;
        $userId = $this->userService::getCurrentUserId();

        $folders = $this->folderService->getTrashedFolders($userId, $folderHash, null, null);
        $files = $this->fileService->getTrashedFiles($userId, $folderHash, null, null);

        foreach ($folders as $folder) {
            $this->folderService->deleteForever($folder->hash);
        }

        foreach ($files as $file) {
            $this->fileService->deleteForever($file->hash);
        }

        return $this->redirectToTrash($response, $folderHash);
    }

    function download(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $hash = !empty($data['hash']) ? (string) $data['hash'] : null;

        $folder = $this->folderService->getFolderByHash($hash);
        if (!is_null($folder)) {
            $response->getBody()->write('Скачивание папок пока не поддерживается');

            return $response->withStatus(400);
        }

        $file = $this->fileService->getFileByHash($hash, false);
        $filename = $file->name;
        $filepath = $file->fileObject->storagePath;

        if (!file_exists($filepath)) {
            $response->getBody()->write('Файл не найден');

            return $response->withStatus(404);
        }

        $fileStream = new Stream(fopen($filepath, 'rb'));

        return $response
                ->withHeader('Content-Type', mime_content_type($filepath))
                ->withHeader('Content-Disposition', 'attachment; filename="' . basename($filename) . '"')
                ->withHeader('Content-Length', (string) filesize($filepath))
                ->withBody($fileStream);
    }

    function share(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->requestData($request);
        $hash = $this->extractHash($data);
        if ($hash === null) {
            return $this->jsonResponse($response, ['error' => 'Не указан файл или папка'], 400);
        }

        $folder = $this->folderService->getFolderByHash($hash);
        if (!is_null($folder)) {
            $this->folderService->share($hash);
        } else {
            if ($this->fileService->getFileByHash($hash) === null) {
                return $this->jsonResponse($response, ['error' => 'Файл или папка не найдены'], 404);
            }

            $this->fileService->share($hash);
        }

        $link = '/api/share?hash=' . rawurlencode($hash);
        $absoluteLink = $this->absoluteUrl($request, $link);

        return $this->jsonResponse($response, [
                'link' => $link,
                'absoluteLink' => $absoluteLink,
        ]);
    }

    function move(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->requestData($request);
        $hash = $this->extractHash($data);
        $targetFolderId = isset($data['targetFolderId']) && is_numeric($data['targetFolderId'])
                ? (int) $data['targetFolderId'] : null;
        if ($hash === null) {
            return $this->jsonResponse($response, ['error' => 'Не указан файл'], 400);
        }

        $folder = $this->folderService->getFolderByHash($hash);
        if (!is_null($folder)) {
            $response->getBody()->write(json_encode(['error' => 'Перемещение папок пока не поддерживается']));

            return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(400);
        }

        $this->fileService->getFileByHash($hash);
        $this->fileService->move($hash, $targetFolderId);

        return $response
                ->withHeader('Location', '/disk/')
                ->withStatus(302);
    }

    function copy(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->requestData($request);
        $hash = $this->extractHash($data);
        if ($hash === null) {
            return $this->jsonResponse($response, ['error' => 'Не указан файл'], 400);
        }

        $folder = $this->folderService->getFolderByHash($hash);
        if (!is_null($folder)) {
            $response->getBody()->write(json_encode(['error' => 'Копирование папок пока не поддерживается']));
            return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(400);
        }

        $this->fileService->getFileByHash($hash);
        $this->fileService->copy($hash);

        return $response
                ->withHeader('Location', '/disk/')
                ->withStatus(302);
    }

    function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        try {
            $data = $this->requestData($request);
            $hash = $this->extractHash($data);
            if ($hash === null) {
                return $this->jsonResponse($response, ['error' => 'Не указан файл или папка'], 400);
            }

            $folder = $this->folderService->getFolderByHash($hash);
            if (!is_null($folder)) {
                $this->folderService->moveToTrash($hash);
            } else {
                $file = $this->fileService->getFileByHash($hash);
                if (!is_null($file)) {
                    $this->fileService->moveToTrash($hash);
                }
            }
        } catch (Throwable $e) {
            $data = ['error' => 'Failed to delete: ' . $e->getMessage()];
            $response->getBody()->write(json_encode($data));

            return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(500);
        }

        return $response
                ->withHeader('Location', '/disk/')
                ->withStatus(302);
    }

    function getFolders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $userId = $this->userService::getCurrentUserId();
        $folders = $this->folderService->getUserFolders($userId, null, null, null, null);

        $data = array_map(fn($folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
        ], $folders);

        $response->getBody()->write(json_encode(array_values($data)));

        return $response;
    }

    private function renderErrorPage(string $message = 'Не удалось подготовить страницу.'): string {
        return Component::render('simpledisk:layout.public', [
                'title' => 'Ошибка | SimpleDisk',
                'pageComponent' => 'simpledisk:error.page',
                'pageParams' => [
                        'message' => $message,
                ],
        ]);
    }

    private function redirectToTrash(ResponseInterface $response, mixed $returnFolderHash = null): ResponseInterface {
        $location = '/disk/trash';
        if (is_string($returnFolderHash) && trim($returnFolderHash) !== '') {
            $location .= '?folder=' . urlencode(trim($returnFolderHash));
        }

        return $response
                ->withHeader('Location', $location)
                ->withStatus(302);
    }

    private function requestData(ServerRequestInterface $request): array {
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody)) {
            return $parsedBody;
        }

        $body = trim($request->getBody()->getContents());
        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode(json: $body, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function extractHash(array $data): ?string {
        $hash = trim((string) ($data['hash'] ?? ''));

        return $hash !== '' ? $hash : null;
    }

    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($status);
    }

    private function absoluteUrl(ServerRequestInterface $request, string $path): string {
        $uri = $request->getUri();
        $authority = $uri->getAuthority();
        if ($authority === '') {
            return $path;
        }

        $scheme = $uri->getScheme() !== '' ? $uri->getScheme() : 'http';

        return $scheme . '://' . $authority . $path;
    }
}
