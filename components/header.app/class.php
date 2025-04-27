<?php


namespace app\component;


use app\ui\Component;


class HeaderAppComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'search' => (string) ($this->params['search'] ?? ''),
                'smartSearch' => !empty($this->params['smartSearch']),
                'login' => (string) ($this->params['login'] ?? ''),
                'currentFolderHash' => (string) ($this->params['currentFolderHash'] ?? ''),
                'sortBy' => (string) ($this->params['sortBy'] ?? 'date'),
                'sortDirection' => (string) ($this->params['sortDirection'] ?? 'desc'),
                'viewMode' => (string) ($this->params['viewMode'] ?? 'grid'),
        ];

        return $this->includeTemplate();
    }
}
