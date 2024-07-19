<?php

class shopRatimirroyaltyPluginFrontendAction extends waViewAction
{
    public function execute() {
        $action = waRequest::get("action");
        switch ($action) {
            case 'first_login':
                $this->firstLogin();
                break;
            default: break;
        }
    }
    private function firstLogin() {
        $contact_id = waRequest::get('contact_id');
        $data = shopRatimirroyaltyPlugin::getDataFromAPI($contact_id);
        if ($data === null) {
            echo 'Ошибка в телефоне';
        } else {
            echo $data['cards']['a:Number'];
        }
        
    }
}