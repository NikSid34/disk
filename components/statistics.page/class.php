<?php


namespace app\component;


use app\ui\Component;


class StatisticsPageComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'sidebarParams' => [
                        'activePage' => 'statistics',
                        'currentFolderHash' => $this->params['currentFolderHash'] ?? null,
                        'usedSpace' => $this->params['usedSpace'] ?? 0,
                        'totalSpace' => $this->params['totalSpace'] ?? 0,
                ],
                'dashboardParams' => $this->params,
        ];

        return $this->includeTemplate();
    }
}
