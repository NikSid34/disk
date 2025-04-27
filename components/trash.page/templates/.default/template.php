<div class="container-fluid flex-grow-1 d-flex p-0">
    <?php $component->includeComponent('simpledisk:sidebar', $data['sidebarParams'] ?? []); ?>

    <div class="container-fluid flex-grow-1 d-flex p-0 trash-page-content">
        <?php $component->includeComponent('simpledisk:trash.list', $data['listParams'] ?? []); ?>
    </div>
</div>
