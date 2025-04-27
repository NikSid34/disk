<div class="container-fluid flex-grow-1 d-flex p-0">
    <?php app\ui\Component::include('simpledisk:sidebar', [
        'activePage' => 'disk',
        'currentFolderHash' => $arParams['currentFolderHash'] ?? null,
        'usedSpace' => $arParams['usedSpace'] ?? 0,
        'totalSpace' => $arParams['totalSpace'] ?? 0,
    ]); ?>

    <div class="container-fluid flex-grow-1 d-flex p-0 disk-page-content">
        <?php app\ui\Component::include('simpledisk:disk.grid', $arParams); ?>
    </div>
</div>
