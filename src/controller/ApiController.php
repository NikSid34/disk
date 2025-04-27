<?php


namespace app\controller;


use app\container\AppContainer;
use app\service\FileService;
use app\service\FolderService;
use app\ui\Component;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class ApiController {
    function __construct(
            private readonly FileService $fileService,
            private readonly FolderService $folderService
    ) {
    }

    static function instance(): self {
        return AppContainer::fromDefaultConfig()->apiController();
    }

    function share(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $hash = trim((string) ($queryParams['hash'] ?? ''));
        if ($hash === '') {
            return $this->renderNotFound($response);
        }

        $folder = $this->folderService->getFolderByHash($hash);

        if ($folder && $folder->isPublic) {
            $this->folderService->increasePublicCounter($hash);
            $files = $this->fileService->getUserFiles($folder->userId, $folder->hash, '', '', '');
        } else {
            $file = $this->fileService->getFileByHash($hash);
            if ($file === null || !$file->isPublic) {
                return $this->renderNotFound($response);
            }

            $this->fileService->increasePublicCounter($hash);
            $files = [$file];
            $folder = null;
        }

        $html = Component::render('simpledisk:layout.public', [
                'title' => 'Публичный доступ | SimpleDisk',
                'pageComponent' => 'simpledisk:share.page',
                'pageParams' => [
                        'hash' => $hash,
                        'files' => $files,
                        'folderName' => $folder?->name,
                ],
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    private function renderNotFound(ResponseInterface $response): ResponseInterface {
        $html = Component::render('simpledisk:layout.public', [
                'title' => 'Публичный доступ | SimpleDisk',
                'pageComponent' => 'simpledisk:error.page',
                'pageParams' => [
                        'message' => 'Публичная ссылка не найдена или больше недоступна.',
                ],
        ]);

        $response->getBody()->write($html);

        return $response->withStatus(404);
    }
}
