<?php
$folders = is_array($data['folders'] ?? null) ? $data['folders'] : [];
$files = is_array($data['files'] ?? null) ? $data['files'] : [];
$currentFolderHash = (string) ($data['currentFolderHash'] ?? '');
$currentFolderName = (string) ($data['currentFolderName'] ?? 'Trash');
$parentBreadcrumb = $data['parentBreadcrumb'] ?? null;
?>
<div class="p-4 flex-grow-1 d-flex flex-column" style="width: 100%; overflow: hidden;">
    <div class="px-4 mb-2 d-flex justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <?php if ($parentBreadcrumb !== null && !empty($parentBreadcrumb->hash)): ?>
                <a href="/disk/trash?folder=<?= $component->e($parentBreadcrumb->hash ?? '') ?>"
                        class="btn btn-link p-0 text-secondary"
                        style="font-size: 1rem; transform: rotate(-90deg);"
                        title="На уровень выше">
                    <i class="bi bi-arrow-up"></i>
                </a>
            <?php elseif ($currentFolderHash !== ''): ?>
                <a href="/disk/trash"
                        class="btn btn-link p-0 text-secondary"
                        style="font-size: 1rem; transform: rotate(-90deg);"
                        title="В корень корзины">
                    <i class="bi bi-arrow-up"></i>
                </a>
            <?php endif; ?>
            <h2 class="mb-0"><?= $component->e($currentFolderName) ?></h2>
        </div>

        <?php if ($folders !== [] || $files !== []): ?>
            <form method="post" action="/disk/trash/clear" class="m-0">
                <input type="hidden" name="folderHash" value="<?= $component->e($currentFolderHash) ?>">
                <button type="submit"
                        class="btn btn-outline-danger"
                        data-confirm="Удалить все элементы корзины навсегда? Это действие необратимо.">
                    <i class="bi bi-trash3 me-2"></i>Удалить все
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="flex-grow-1 px-4 d-flex flex-column" style="overflow-y: auto; min-height: 0;">
        <?php if ($folders === [] && $files === []): ?>
            <div class="d-flex flex-grow-1 justify-content-center align-items-center">
                <div class="text-center text-muted" style="user-select: none;">
                    <i class="bi bi-trash" style="font-size: 4rem;"></i>
                    <p class="mt-3 mb-0 fs-4">Корзина пуста</p>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive file-table-wrapper">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Название</th>
                            <th scope="col" class="file-table-size">Размер</th>
                            <th scope="col" class="file-table-date">Дата</th>
                            <th scope="col" class="file-table-retention">Хранение</th>
                            <th scope="col" class="file-table-actions text-end">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($folders as $folder): ?>
                            <tr class="table-item table-view-row"
                                    data-url="/disk/trash?folder=<?= $component->e($folder->hash ?? '') ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="file-table-thumb">
                                            <i class="bi bi-folder-fill fs-3 text-warning"></i>
                                        </div>
                                        <div class="fw-semibold">
                                            <span class="item-name"><?= $component->e($folder->name ?? '') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted file-table-size">-</td>
                                <td class="text-muted file-table-date"><?= $component->e($component->formatDate($folder->createdAt ?? null)) ?></td>
                                <td class="text-muted file-table-retention">Осталось 30 дней</td>
                                <td class="text-end file-table-actions">
                                    <div class="d-inline-flex gap-2">
                                        <form method="post" action="/disk/trash/restoreFolder" class="m-0">
                                            <input type="hidden" name="hash" value="<?= $component->e($folder->hash ?? '') ?>">
                                            <input type="hidden" name="returnFolderHash" value="<?= $component->e($currentFolderHash) ?>">
                                            <button type="submit" class="btn btn-sm btn-primary" title="Восстановить">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="/disk/trash/deleteFolder" class="m-0">
                                            <input type="hidden" name="hash" value="<?= $component->e($folder->hash ?? '') ?>">
                                            <input type="hidden" name="returnFolderHash" value="<?= $component->e($currentFolderHash) ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-danger"
                                                    title="Удалить навсегда"
                                                    data-confirm="Удалить папку навсегда? Это действие необратимо.">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($files as $file): ?>
                            <tr class="table-item">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="file-table-thumb">
                                            <?php if (($file->fileType->name ?? '') === 'Image'): ?>
                                                <img src="/thumbnail.php?hash=<?= $component->e($file->hash ?? '') ?>&size=<?= $component->e((string) \app\enum\ThumbnailQualityLevel::Low->value) ?>"
                                                        alt="<?= $component->e($file->name ?? '') ?>">
                                            <?php else: ?>
                                                <i class="bi <?= $component->e($component->fileIcon($file->name ?? '')) ?> fs-3"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fw-semibold">
                                            <span class="item-name"><?= $component->e($file->name ?? '') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted file-table-size"><?= $component->e($component->formatFileSize($file->fileObject->fileSize ?? 0)) ?></td>
                                <td class="text-muted file-table-date"><?= $component->e($component->formatDate($file->createdAt ?? null)) ?></td>
                                <td class="text-muted file-table-retention">Осталось 30 дней</td>
                                <td class="text-end file-table-actions">
                                    <div class="d-inline-flex gap-2">
                                        <form method="post" action="/disk/trash/restoreFile" class="m-0">
                                            <input type="hidden" name="hash" value="<?= $component->e($file->hash ?? '') ?>">
                                            <input type="hidden" name="returnFolderHash" value="<?= $component->e($currentFolderHash) ?>">
                                            <button type="submit" class="btn btn-sm btn-primary" title="Восстановить">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="/disk/trash/deleteFile" class="m-0">
                                            <input type="hidden" name="hash" value="<?= $component->e($file->hash ?? '') ?>">
                                            <input type="hidden" name="returnFolderHash" value="<?= $component->e($currentFolderHash) ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-danger"
                                                    title="Удалить навсегда"
                                                    data-confirm="Удалить файл навсегда? Это действие необратимо.">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
