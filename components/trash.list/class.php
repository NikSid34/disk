<?php


namespace app\component;


use app\ui\Component;


class TrashListComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $breadcrumbs = is_array($this->params['breadcrumbs'] ?? null) ? $this->params['breadcrumbs'] : [];

        $this->data = [
                'folders' => is_array($this->params['folders'] ?? null) ? $this->params['folders'] : [],
                'files' => is_array($this->params['files'] ?? null) ? $this->params['files'] : [],
                'breadcrumbs' => $breadcrumbs,
                'currentFolderHash' => (string) ($this->params['currentFolderHash'] ?? ''),
                'currentFolderName' => (string) ($this->params['currentFolderName'] ?? 'Trash'),
                'parentBreadcrumb' => $breadcrumbs !== [] ? $breadcrumbs[count($breadcrumbs) - 1] : null,
        ];

        return $this->includeTemplate();
    }
}
