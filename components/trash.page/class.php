<?php


namespace app\component;


use app\ui\Component;


class TrashPageComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'sidebarParams' => [
                        'activePage' => 'trash',
                        'currentFolderHash' => $this->params['currentFolderHash'] ?? null,
                        'usedSpace' => $this->params['usedSpace'] ?? 0,
                        'totalSpace' => $this->params['totalSpace'] ?? 0,
                ],
                'listParams' => $this->params,
        ];

        return $this->includeTemplate();
    }
}
