<?php


namespace app\component;


use app\ui\Component;


class StatisticsDashboardComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'largestFiles' => is_array($this->params['largestFiles'] ?? null) ? $this->params['largestFiles'] : [],
                'publicFolders' => is_array($this->params['publicFolders'] ?? null) ? $this->params['publicFolders'] : [],
                'publicFiles' => is_array($this->params['publicFiles'] ?? null) ? $this->params['publicFiles'] : [],
                'totalFiles' => (int) ($this->params['totalFiles'] ?? 0),
                'usedSpace' => $this->params['usedSpace'] ?? 0,
                'uniqFiles' => (int) ($this->params['uniqFiles'] ?? 0),
        ];

        return $this->includeTemplate();
    }
}
