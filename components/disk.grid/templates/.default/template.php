<?php
$folders = $arResult['folders'] ?? [];
$files = $arResult['files'] ?? [];
$breadcrumbs = $arResult['breadcrumbs'] ?? [];
$parentBreadcrumb = $arResult['parentBreadcrumb'] ?? null;
$currentFolderHash = (string) ($arParams['currentFolderHash'] ?? '');
$sortBy = (string) ($arParams['sortBy'] ?? 'date');
$sortDirection = (string) ($arParams['sortDirection'] ?? 'desc');
$currentFolderName = (string) ($arParams['currentFolderName'] ?? 'Файлы');
$search = (string) ($arParams['search'] ?? '');
$smartSearch = !empty($arParams['smartSearch']);
$viewMode = (string) ($arParams['viewMode'] ?? 'grid');

$navigationQuery = [
        'sortBy' => $sortBy,
        'sortDirection' => $sortDirection,
        'viewMode' => $viewMode,
];
?>
<div class="p-4 flex-grow-1 d-flex flex-column" style="width: 100%; overflow: hidden;">
    <div class="px-4 mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider: '-';">
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <?php $crumbHash = $crumb->hash ?? ''; ?>
                    <li class="breadcrumb-item">
                        <a href="<?= $component->e($component->buildUrl('/disk/', ['folder' => $crumbHash] + $navigationQuery)) ?>"
                                class="text-secondary text-decoration-none breadcrumb-link">
                            <?= $component->e($crumb->name ?? '') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>

    <div class="px-4 mb-2 d-flex justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <?php if ($parentBreadcrumb !== null): ?>
                <?php $parentHash = $parentBreadcrumb->hash ?? ''; ?>
                <a href="<?= $component->e($component->buildUrl('/disk/', ['folder' => $parentHash] + $navigationQuery)) ?>"
                        class="btn btn-link p-0 text-secondary"
                        style="font-size: 1rem; transform: rotate(-90deg);"
                        title="На уровень выше">
                    <i class="bi bi-arrow-up"></i>
                </a>
            <?php endif; ?>

            <h2 class="mb-0"><?= $component->e($currentFolderName) ?></h2>
        </div>

        <form id="sortForm" method="get" class="d-flex align-items-center gap-2 justify-content-end controls-toolbar" action="/disk/">
            <?php if ($currentFolderHash !== ''): ?>
                <input type="hidden" name="folder" value="<?= $component->e($currentFolderHash) ?>">
            <?php endif; ?>
            <?php if ($search !== ''): ?>
                <input type="hidden" name="search" value="<?= $component->e($search) ?>">
            <?php endif; ?>
            <input type="hidden" name="smartSearch" value="<?= $smartSearch ? '1' : '0' ?>">
            <input type="hidden" name="viewMode" id="viewModeInput" value="<?= $component->e($viewMode) ?>">

            <select name="sortBy" id="sortSelect" class="form-select">
                <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>По названию</option>
                <option value="size" <?= $sortBy === 'size' ? 'selected' : '' ?>>По размеру</option>
                <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>>По дате загрузки</option>
            </select>
            <input type="hidden" name="sortDirection" id="sortDirection" value="<?= $component->e($sortDirection) ?>">
            <button type="button" id="toggleSortDirection" class="btn btn-outline-secondary" title="Изменить порядок сортировки">
                <?php if ($sortDirection === 'desc'): ?>
                    <i class="bi bi-arrow-down-short"></i>
                <?php else: ?>
                    <i class="bi bi-arrow-up-short"></i>
                <?php endif; ?>
            </button>

            <div class="btn-group view-switch" role="group" aria-label="Переключение вида">
                <button type="button"
                        class="btn <?= $viewMode === 'grid' ? 'btn-primary' : 'btn-outline-secondary' ?>"
                        data-view-mode="grid"
                        title="Плитки">
                    <i class="bi bi-grid-3x3-gap"></i>
                </button>
                <button type="button"
                        class="btn <?= $viewMode === 'table' ? 'btn-primary' : 'btn-outline-secondary' ?>"
                        data-view-mode="table"
                        title="Таблица">
                    <i class="bi bi-list-ul"></i>
                </button>
            </div>
        </form>
    </div>

    <div class="flex-grow-1 px-4 d-flex flex-column" style="overflow-y: auto; min-height: 0;">
        <?php if (!$arResult['hasItems']): ?>
            <div class="d-flex flex-grow-1 justify-content-center align-items-center">
                <div class="text-center text-muted" style="user-select: none;">
                    <i class="bi bi-folder-x" style="font-size: 4rem;"></i>
                    <p class="mt-3 mb-0 fs-4">Нет файлов</p>
                </div>
            </div>
        <?php elseif ($viewMode === 'table'): ?>
            <div class="table-responsive file-table-wrapper">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Название</th>
                            <th scope="col" class="file-table-size">Размер</th>
                            <th scope="col" class="file-table-date">Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($folders as $folder): ?>
                            <tr class="table-item table-view-row"
                                    data-hash="<?= $component->e($folder->hash ?? '') ?>"
                                    data-name="<?= $component->e($folder->name ?? '') ?>"
                                    data-item-type="folder"
                                    data-size="-"
                                    data-created-at="<?= $component->e($component->formatDate($folder->createdAt ?? null)) ?>"
                                    data-public="<?= !empty($folder->isPublic) ? '1' : '0' ?>"
                                    data-visits="<?= $component->e((string) ($folder->visitsNum ?? 0)) ?>"
                                    data-url="<?= $component->e($component->buildUrl('/disk/', ['folder' => $folder->hash ?? ''] + $navigationQuery)) ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="file-table-thumb">
                                            <i class="bi bi-folder-fill fs-3 <?= !empty($folder->isPublic) ? 'text-primary' : 'text-warning' ?>"></i>
                                        </div>
                                        <div class="fw-semibold d-flex align-items-center gap-2">
                                            <span class="item-name"><?= $component->e($folder->name ?? '') ?></span>
                                            <?php if (!empty($folder->isPublic)): ?>
                                                <i class="bi bi-link-45deg text-primary copy-link"
                                                        title="Скопировать ссылку"
                                                        data-link="/api/share?hash=<?= $component->e($folder->hash ?? '') ?>"
                                                        style="cursor: pointer;"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted">-</td>
                                <td class="text-muted"><?= $component->e($component->formatDate($folder->createdAt ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($files as $file): ?>
                            <?php $matchedPercentage = $file->matchedPercentage ?? null; ?>
                            <tr class="table-item"
                                    data-hash="<?= $component->e($file->hash ?? '') ?>"
                                    data-name="<?= $component->e($file->name ?? '') ?>"
                                    data-item-type="file"
                                    data-size="<?= $component->e($component->formatFileSize($file->fileObject->fileSize ?? 0)) ?>"
                                    data-created-at="<?= $component->e($component->formatDate($file->createdAt ?? null)) ?>"
                                    data-public="<?= !empty($file->isPublic) ? '1' : '0' ?>"
                                    data-visits="<?= $component->e((string) ($file->visitsNum ?? 0)) ?>">
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
                                        <div class="d-flex flex-column gap-1">
                                            <div class="fw-semibold d-flex align-items-center gap-2">
                                                <span class="item-name"><?= $component->e($file->name ?? '') ?></span>
                                                <?php if (!empty($file->isPublic)): ?>
                                                    <i class="bi bi-link-45deg text-primary copy-link"
                                                            title="Скопировать ссылку"
                                                            data-link="/api/share?hash=<?= $component->e($file->hash ?? '') ?>"
                                                            style="cursor: pointer;"></i>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($matchedPercentage !== null): ?>
                                                <?php
                                                $progressColor = '#28a745';
                                                if ($matchedPercentage >= 30 && $matchedPercentage < 40) {
                                                    $progressColor = '#ffc107';
                                                } elseif ($matchedPercentage >= 40 && $matchedPercentage < 60) {
                                                    $progressColor = '#8bc34a';
                                                }
                                                ?>
                                                <div class="match-indicator">
                                                    <div class="progress" style="width: 140px; height: 8px; background: #e9ecef; border-radius: 4px;">
                                                        <div class="progress-bar"
                                                                role="progressbar"
                                                                style="width: <?= $component->e((string) $matchedPercentage) ?>%; background-color: <?= $component->e($progressColor) ?>; border-radius: 4px;"
                                                                aria-valuenow="<?= $component->e((string) $matchedPercentage) ?>"
                                                                aria-valuemin="0"
                                                                aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <span style="color: <?= $component->e($progressColor) ?>;"><?= $component->e((string) $matchedPercentage) ?>%</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted"><?= $component->e($component->formatFileSize($file->fileObject->fileSize ?? 0)) ?></td>
                                <td class="text-muted"><?= $component->e($component->formatDate($file->createdAt ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="file-grid">
                <?php foreach ($folders as $folder): ?>
                    <a href="<?= $component->e($component->buildUrl('/disk/', ['folder' => $folder->hash ?? ''] + $navigationQuery)) ?>"
                            class="table-item file-grid-card text-decoration-none text-dark"
                            data-hash="<?= $component->e($folder->hash ?? '') ?>"
                            data-name="<?= $component->e($folder->name ?? '') ?>"
                            data-item-type="folder"
                            data-size="-"
                            data-created-at="<?= $component->e($component->formatDate($folder->createdAt ?? null)) ?>"
                            data-public="<?= !empty($folder->isPublic) ? '1' : '0' ?>"
                            data-visits="<?= $component->e((string) ($folder->visitsNum ?? 0)) ?>">
                        <div class="file-grid-thumb">
                            <i class="bi bi-folder-fill fs-1 <?= !empty($folder->isPublic) ? 'text-primary' : 'text-warning' ?>"></i>
                        </div>
                        <div class="text-center mt-2 file-name" title="<?= $component->e($folder->name ?? '') ?>">
                            <span class="item-name"><?= $component->e($folder->name ?? '') ?></span>
                            <?php if (!empty($folder->isPublic)): ?>
                                <i class="bi bi-link-45deg text-primary copy-link"
                                        title="Скопировать ссылку"
                                        data-link="/api/share?hash=<?= $component->e($folder->hash ?? '') ?>"
                                        style="cursor: pointer;"></i>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>

                <?php foreach ($files as $file): ?>
                    <?php $matchedPercentage = $file->matchedPercentage ?? null; ?>
                    <div class="table-item file-grid-card"
                            data-hash="<?= $component->e($file->hash ?? '') ?>"
                            data-name="<?= $component->e($file->name ?? '') ?>"
                            data-item-type="file"
                            data-size="<?= $component->e($component->formatFileSize($file->fileObject->fileSize ?? 0)) ?>"
                            data-created-at="<?= $component->e($component->formatDate($file->createdAt ?? null)) ?>"
                            data-public="<?= !empty($file->isPublic) ? '1' : '0' ?>"
                            data-visits="<?= $component->e((string) ($file->visitsNum ?? 0)) ?>">
                        <div class="file-grid-thumb">
                            <?php if (($file->fileType->name ?? '') === 'Image'): ?>
                                <img src="/thumbnail.php?hash=<?= $component->e($file->hash ?? '') ?>&size=<?= $component->e((string) \app\enum\ThumbnailQualityLevel::Low->value) ?>"
                                        alt="<?= $component->e($file->name ?? '') ?>"
                                        class="rounded">
                            <?php else: ?>
                                <i class="bi <?= $component->e($component->fileIcon($file->name ?? '')) ?> fs-1"></i>
                            <?php endif; ?>
                        </div>
                        <div class="text-center mt-2 file-name" title="<?= $component->e($file->name ?? '') ?>">
                            <span class="item-name"><?= $component->e($file->name ?? '') ?></span>
                            <?php if (!empty($file->isPublic)): ?>
                                <i class="bi bi-link-45deg text-primary copy-link"
                                        title="Скопировать ссылку"
                                        data-link="/api/share?hash=<?= $component->e($file->hash ?? '') ?>"
                                        style="cursor: pointer;"></i>
                            <?php endif; ?>
                        </div>

                        <?php if ($matchedPercentage !== null): ?>
                            <?php
                            $progressColor = '#28a745';
                            if ($matchedPercentage >= 30 && $matchedPercentage < 40) {
                                $progressColor = '#ffc107';
                            } elseif ($matchedPercentage >= 40 && $matchedPercentage < 60) {
                                $progressColor = '#8bc34a';
                            }
                            ?>
                            <div class="progress mt-2" style="width: 100px; height: 8px; background: #e9ecef; border-radius: 4px;">
                                <div class="progress-bar"
                                        role="progressbar"
                                        style="width: <?= $component->e((string) $matchedPercentage) ?>%; background-color: <?= $component->e($progressColor) ?>; border-radius: 4px;"
                                        aria-valuenow="<?= $component->e((string) $matchedPercentage) ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100">
                                </div>
                            </div>
                            <div style="font-size: 0.75rem; margin-top: 2px; color: <?= $component->e($progressColor) ?>;">
                                <?= $component->e((string) $matchedPercentage) ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php app\ui\Component::include('simpledisk:disk.context.menu'); ?>
</div>

<div id="copyToast" class="toast position-fixed bottom-0 end-0 m-4 text-white bg-success" role="alert" aria-live="assertive" aria-atomic="true" style="z-index: 1055;">
    <div class="toast-body">
        Ссылка скопирована в буфер обмена
    </div>
</div>
