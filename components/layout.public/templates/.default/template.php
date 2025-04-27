<?php
$title = (string) ($arParams['title'] ?? 'SimpleDisk');
$bodyClass = (string) ($arParams['bodyClass'] ?? 'd-flex flex-column min-vh-100');
$pageComponent = (string) ($arParams['pageComponent'] ?? '');
$pageParams = is_array($arParams['pageParams'] ?? null) ? $arParams['pageParams'] : [];
$showFooter = !array_key_exists('showFooter', $arParams) || (bool) $arParams['showFooter'];
?>
<!DOCTYPE html>
<html lang="ru">
    <head>
        <?php app\ui\Component::include('simpledisk:head', ['title' => $title]); ?>
    </head>
    <body class="<?= $component->e($bodyClass) ?>">
        <?php app\ui\Component::include('simpledisk:header.public'); ?>

        <?php if ($pageComponent !== ''): ?>
            <?php app\ui\Component::include($pageComponent, $pageParams); ?>
        <?php endif; ?>

        <?php if ($showFooter): ?>
            <footer class="bg-white text-center py-3 shadow-sm">
                <small class="text-muted">&copy; 2026 SimpleDisk.</small>
            </footer>
        <?php endif; ?>

        <script src="/public/bootstrap/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    </body>
</html>
