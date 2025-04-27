<?php
$search = (string) ($arParams['search'] ?? '');
$smartSearch = !empty($arParams['smartSearch']);
$login = (string) ($arParams['login'] ?? '');
$currentFolderHash = (string) ($arParams['currentFolderHash'] ?? '');
$sortBy = (string) ($arParams['sortBy'] ?? 'date');
$sortDirection = (string) ($arParams['sortDirection'] ?? 'desc');
$viewMode = (string) ($arParams['viewMode'] ?? 'grid');
?>
<header class="bg-white shadow-sm mb-0" style="height: 9vh;">
    <nav class="navbar navbar-expand-lg navbar-light container-fluid py-2 px-4">
        <a class="navbar-brand fw-bold me-4 fs-4 d-flex align-items-center" href="/">
            <img src="/public/icons/icon.ico" alt="Logo" width="48" height="48" class="me-2">
            SimpleDisk
        </a>

        <form class="me-auto w-25" style="min-width: 220px;" method="get" action="/disk/" id="searchForm">
            <div class="d-flex">
                <?php if ($currentFolderHash !== ''): ?>
                    <input type="hidden" name="folder" value="<?= $component->e($currentFolderHash) ?>">
                <?php endif; ?>
                <input type="hidden" name="sortBy" value="<?= $component->e($sortBy) ?>">
                <input type="hidden" name="sortDirection" value="<?= $component->e($sortDirection) ?>">
                <input type="hidden" name="viewMode" value="<?= $component->e($viewMode) ?>">

                <input class="form-control me-2"
                        type="search"
                        placeholder="Поиск файлов..."
                        name="search"
                        value="<?= $component->e($search) ?>"
                        id="searchInput">

                <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>

            <div class="search-mode mt-2">
                <label class="switch">
                    <input type="checkbox" id="smartToggle" <?= $smartSearch ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>

                <input type="hidden" name="smartSearch" id="smartInput" value="<?= $smartSearch ? '1' : '0' ?>">

                <span class="mode-label smart tooltip-wrapper">
                    Умный поиск
                    <span class="tooltip">
                        Умный поиск анализирует <b>содержимое файлов</b> и
                        <b>описание изображений</b>.<br><br>
                        В стандартном режиме поиск выполняется только по <b>названию файлов</b>.
                    </span>
                </span>
            </div>
        </form>

        <ul class="navbar-nav ms-auto lk">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <span class="fs-6 me-2"><?= $component->e($login) ?></span>
                    <i class="bi bi-person-circle fs-4"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end profile-popup">
                    <li>
                        <form action="/user/logout" method="POST" class="px-3">
                            <button type="submit" class="btn btn-no-style w-100">Выйти</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
</header>


