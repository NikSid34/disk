<?php


namespace app\component;


use app\ui\Component;


class DiskContextMenuComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [];

        return $this->includeTemplate();
    }
}
