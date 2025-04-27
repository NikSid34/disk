<?php


namespace app\component;


use app\ui\Component;


class SidebarComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $totalSpace = (float) ($this->params['totalSpace'] ?? 0);
        $usedSpace = (float) ($this->params['usedSpace'] ?? 0);

        $this->data = [
                'activePage' => (string) ($this->params['activePage'] ?? 'disk'),
                'currentFolderHash' => $this->params['currentFolderHash'] ?? null,
                'usedSpace' => $usedSpace,
                'totalSpace' => $totalSpace,
                'percent' => $totalSpace > 0
                        ? (int) floor(($usedSpace / $totalSpace) * 100)
                        : 0,
        ];

        return $this->includeTemplate();
    }
}
