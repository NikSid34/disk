<?php


namespace app\component;


use app\ui\Component;


class ErrorPageComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'message' => (string) ($this->params['message'] ?? 'Неизвестная ошибка.'),
        ];

        return $this->includeTemplate();
    }
}
