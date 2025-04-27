<?php


namespace app\component;


use app\ui\Component;


class SharePageComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'listParams' => $this->params,
        ];

        return $this->includeTemplate();
    }
}
