<?php


namespace app\component;


use app\ui\Component;


class LoginPageComponent extends Component {
    public function __construct() {
    }

    public function execute(): string {
        $this->data = [
                'registration' => !empty($this->params['registration']),
                'invalidLogin' => !empty($this->params['invalidLogin']),
                'unmatchedPassword' => !empty($this->params['unmatchedPassword']),
                'loginAlreadyExists' => !empty($this->params['loginAlreadyExists']),
                'emailAlreadyExists' => !empty($this->params['emailAlreadyExists']),
        ];
        $this->data['hasErrors'] = $this->data['invalidLogin']
                || $this->data['unmatchedPassword']
                || $this->data['loginAlreadyExists']
                || $this->data['emailAlreadyExists'];
        $this->data['showRegister'] = $this->data['registration'];

        return $this->includeTemplate();
    }
}
