<?php
$activePage = (string) ($arParams['activePage'] ?? 'disk');
$currentFolderHash = $arParams['currentFolderHash'] ?? null;
$percent = (int) ($arResult['percent'] ?? 0);
?>
<aside class="bg-white border-end d-flex flex-column p-3" style="width: 250px;">
    <div class="flex-grow-1">
        <div id="loader" class="loader"></div>
        <button type="button" class="btn btn-primary w-100 mt-3" id="fileUploadButton">
            <i class="bi bi-upload me-1"></i> Загрузить
        </button>

        <button class="btn btn-outline-secondary w-100 mt-3 mb-4" data-bs-toggle="modal" data-bs-target="#createFolderModal">
            <i class="bi bi-folder-plus me-1"></i> Создать папку
        </button>

        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a class="nav-link text-dark d-flex align-items-center hover-bg <?= $activePage === 'statistics' ? 'active bg-light fw-semibold' : '' ?>"
                        href="/disk/statistics"
                        style="border-radius: 0.375rem;">
                    <i class="bi bi-bar-chart me-2"></i> Статистика
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-dark d-flex align-items-center hover-bg <?= $activePage === 'disk' ? 'active bg-light fw-semibold' : '' ?>"
                        href="/disk/"
                        style="border-radius: 0.375rem;">
                    <i class="bi bi-folder2-open me-2"></i> Файлы
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-dark d-flex align-items-center hover-bg <?= $activePage === 'trash' ? 'active bg-light fw-semibold' : '' ?>"
                        href="/disk/trash"
                        style="border-radius: 0.375rem;">
                    <i class="bi bi-trash3 me-2"></i> Корзина
                </a>
            </li>
        </ul>
    </div>

    <div class="text-center small mb-2">
        Занято <?= $component->e($component->formatFileSize($arParams['usedSpace'] ?? 0)) ?>
        из <?= $component->e($component->formatFileSize($arParams['totalSpace'] ?? 0, 0)) ?>
    </div>
    <div class="progress" style="height: 20px;">
        <div class="progress-bar"
                role="progressbar"
                style="width: <?= $percent ?>%;"
                aria-valuenow="<?= $percent ?>"
                aria-valuemin="0"
                aria-valuemax="100">
        </div>
    </div>

    <form id="sidebarUploadForm" action="/disk/uploadFile" method="post" enctype="multipart/form-data">
        <input type="hidden" id="folderHash" name="folderHash" value="<?= $component->e((string) $currentFolderHash) ?>">
        <input type="file" name="files[]" id="fileUploadTrigger" class="d-none" multiple>
    </form>
</aside>

<div class="modal fade" id="createFolderModal" tabindex="-1" aria-labelledby="createFolderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="createFolderForm">
                <input type="hidden" id="currentFolderHash" name="currentFolderHash" value="<?= $component->e((string) $currentFolderHash) ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="createFolderModalLabel">Создать папку</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="folderName" class="form-label">Название папки</label>
                        <input type="text" class="form-control" id="folderName" name="folderName" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать</button>
                </div>
            </form>
        </div>
    </div>
</div>

