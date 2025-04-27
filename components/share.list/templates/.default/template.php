<?php
$files = is_array($arParams['files'] ?? null) ? $arParams['files'] : [];
$folderName = $arParams['folderName'] ?? null;
?>
<div class="p-4 flex-grow-1 d-flex flex-column" style="width: 100%; overflow: hidden;">
    <?php if (!empty($folderName)): ?>
        <div class="px-4 mb-2 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <h2 class="mb-0"><?= $component->e((string) $folderName) ?></h2>
            </div>
        </div>
    <?php endif; ?>

    <div class="flex-grow-1 px-4 d-flex flex-column" style="overflow-y: auto; min-height: 0;">
        <div class="list-group flex-grow-1 d-flex flex-column">
            <?php if ($files === []): ?>
                <div class="list-group-item flex-grow-1 d-flex flex-column justify-content-center align-items-center text-muted text-center" style="min-height: 200px; user-select: none;">
                    <i class="bi bi-folder-x" style="font-size: 4rem;"></i>
                    <p class="mt-3 mb-0 fs-4">Нет файлов</p>
                </div>
            <?php endif; ?>

            <?php foreach ($files as $file): ?>
                <div class="list-group-item list-group-item-action d-flex align-items-center justify-content-between"
                        style="min-height: 6vh"
                        data-hash="<?= $component->e($file->hash ?? '') ?>">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi <?= $component->e($component->fileIcon($file->name ?? '')) ?> fs-4"></i>
                        <div class="fw-bold"><?= $component->e($file->name ?? '') ?></div>
                    </div>
                    <div class="text-end text-muted small d-flex flex-column justify-content-end">
                        <div><?= $component->e($component->formatFileSize($file->fileObject->fileSize ?? 0)) ?></div>
                        <div><?= $component->e($component->formatDate($file->createdAt ?? null)) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php app\ui\Component::include('simpledisk:share.context.menu'); ?>
</div>
