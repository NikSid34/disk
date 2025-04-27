<?php


namespace app\component;


use app\ui\Component;


class LayoutPublicComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'title' => (string) ($this->params['title'] ?? 'SimpleDisk'),
                'bodyClass' => (string) ($this->params['bodyClass'] ?? 'd-flex flex-column min-vh-100'),
                'pageComponent' => (string) ($this->params['pageComponent'] ?? ''),
                'pageParams' => is_array($this->params['pageParams'] ?? null) ? $this->params['pageParams'] : [],
                'showFooter' => !array_key_exists('showFooter', $this->params) || (bool) $this->params['showFooter'],
        ];

        return $this->includeTemplate();
    }
}
