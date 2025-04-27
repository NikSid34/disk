<?php


namespace app\component;


use app\ui\Component;


class HeadComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'title' => (string) ($this->params['title'] ?? 'SimpleDisk'),
        ];

        return $this->includeTemplate();
    }
}
