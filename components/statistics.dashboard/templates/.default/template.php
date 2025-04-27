<?php
$largestFiles = is_array($arParams['largestFiles'] ?? null) ? $arParams['largestFiles'] : [];
$publicFolders = is_array($arParams['publicFolders'] ?? null) ? $arParams['publicFolders'] : [];
$publicFiles = is_array($arParams['publicFiles'] ?? null) ? $arParams['publicFiles'] : [];
?>
<div class="statistics-dashboard flex-grow-1 px-4 py-3 d-flex flex-column gap-4">
    <div>
        <h4 class="mb-3">Статистика хранилища</h4>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-1">Всего файлов</h5>
                        <p class="fs-4 mb-0"><?= $component->e((string) ($arParams['totalFiles'] ?? 0)) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-1">Общий объём</h5>
                        <p class="fs-4 mb-0"><?= $component->e($component->formatFileSize($arParams['usedSpace'] ?? 0)) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-1">Уникальных файлов</h5>
                        <p class="fs-4 mb-0"><?= $component->e((string) ($arParams['uniqFiles'] ?? 0)) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <h4 class="mb-3">Файлы, занимающие больше всего места</h4>
        <div class="list-group">
            <?php foreach ($largestFiles as $file): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi <?= $component->e($component->fileIcon($file->name ?? '')) ?> fs-4"></i>
                        <div>
                            <div class="fw-bold"><?= $component->e($file->name ?? '') ?></div>
                            <small class="text-muted"><?= $component->e($component->formatFileSize($file->fileObject->fileSize ?? 0)) ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div>
        <h4 class="mb-3">Публичные файлы и папки</h4>

        <?php foreach ($publicFolders as $folder): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-folder-fill text-primary fs-4"></i>
                    <div>
                        <div class="fw-bold"><?= $component->e($folder->name ?? '') ?></div>
                        <small class="text-muted">Публичных просмотров: <?= $component->e((string) ($folder->visitsNum ?? 0)) ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ($publicFiles as $file): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi <?= $component->e($component->fileIcon($file->name ?? '')) ?> fs-4"></i>
                    <div>
                        <div class="fw-bold"><?= $component->e($file->name ?? '') ?></div>
                        <small class="text-muted">Публичных просмотров: <?= $component->e((string) ($file->visitsNum ?? 0)) ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
