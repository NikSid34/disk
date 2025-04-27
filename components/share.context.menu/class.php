<?php


namespace app\component;


use app\ui\Component;


class ShareContextMenuComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [];

        return $this->includeTemplate();
    }
}
