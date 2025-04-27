<?php


namespace app\component;


use app\ui\Component;


class LayoutAppComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'title' => (string) ($this->params['title'] ?? 'SimpleDisk'),
                'headerParams' => is_array($this->params['headerParams'] ?? null) ? $this->params['headerParams'] : [],
                'pageComponent' => (string) ($this->params['pageComponent'] ?? ''),
                'pageParams' => is_array($this->params['pageParams'] ?? null) ? $this->params['pageParams'] : [],
        ];

        return $this->includeTemplate();
    }
}
