<?php


namespace app\component;


use app\ui\Component;


class ShareListComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'files' => is_array($this->params['files'] ?? null) ? $this->params['files'] : [],
                'folderName' => $this->params['folderName'] ?? null,
        ];

        return $this->includeTemplate();
    }
}
