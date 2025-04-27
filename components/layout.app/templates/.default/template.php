<?php
$title = (string) ($arParams['title'] ?? 'SimpleDisk');
$headerParams = is_array($arParams['headerParams'] ?? null) ? $arParams['headerParams'] : [];
$pageComponent = (string) ($arParams['pageComponent'] ?? '');
$pageParams = is_array($arParams['pageParams'] ?? null) ? $arParams['pageParams'] : [];
?>
<!DOCTYPE html>
<html lang="ru">
    <head>
        <?php app\ui\Component::include('simpledisk:head', ['title' => $title]); ?>
    </head>
    <body class="bg-light d-flex flex-column min-vh-100">
        <?php app\ui\Component::include('simpledisk:header.app', $headerParams); ?>

        <?php if ($pageComponent !== ''): ?>
            <?php app\ui\Component::include($pageComponent, $pageParams); ?>
        <?php endif; ?>

        <footer class="bg-white text-center py-3 mt-auto" style="height: 5vh;">
            <small class="text-muted">&copy; 2026 SimpleDisk.</small>
            <script src="/public/bootstrap/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        </footer>
    </body>
</html>
