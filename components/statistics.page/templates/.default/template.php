<div class="container-fluid flex-grow-1 d-flex p-0">
    <?php app\ui\Component::include('simpledisk:sidebar', [
        'activePage' => 'statistics',
        'currentFolderHash' => $arParams['currentFolderHash'] ?? null,
        'usedSpace' => $arParams['usedSpace'] ?? 0,
        'totalSpace' => $arParams['totalSpace'] ?? 0,
    ]); ?>

    <?php app\ui\Component::include('simpledisk:statistics.dashboard', $arParams); ?>
</div>
