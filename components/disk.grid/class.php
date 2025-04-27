<?php


namespace app\component;


use app\ui\Component;


class DiskGridComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $folders = is_array($this->params['folders'] ?? null) ? $this->params['folders'] : [];
        $files = is_array($this->params['files'] ?? null) ? $this->params['files'] : [];
        $breadcrumbs = is_array($this->params['breadcrumbs'] ?? null) ? $this->params['breadcrumbs'] : [];
        $sortBy = (string) ($this->params['sortBy'] ?? 'date');
        $sortDirection = (string) ($this->params['sortDirection'] ?? 'desc');
        $viewMode = (string) ($this->params['viewMode'] ?? 'grid');

        $this->data = [
                'folders' => $folders,
                'files' => $files,
                'breadcrumbs' => $breadcrumbs,
                'hasItems' => $folders !== [] || $files !== [],
                'parentBreadcrumb' => $breadcrumbs !== [] ? $breadcrumbs[count($breadcrumbs) - 1] : null,
                'currentFolderHash' => (string) ($this->params['currentFolderHash'] ?? ''),
                'sortBy' => $sortBy,
                'sortDirection' => $sortDirection,
                'currentFolderName' => (string) ($this->params['currentFolderName'] ?? 'Р¤Р°Р№Р»С‹'),
                'search' => (string) ($this->params['search'] ?? ''),
                'smartSearch' => !empty($this->params['smartSearch']),
                'viewMode' => $viewMode,
                'navigationQuery' => [
                        'sortBy' => $sortBy,
                        'sortDirection' => $sortDirection,
                        'viewMode' => $viewMode,
                ],
        ];

        return $this->includeTemplate();
    }
}
