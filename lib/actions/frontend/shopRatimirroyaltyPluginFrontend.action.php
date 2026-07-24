<?php

class shopRatimirroyaltyPluginFrontendAction extends waViewAction
{
    public function execute()
    {
        $action = waRequest::get("action");

        switch ($action) {
            case 'first_login':
                $this->firstLogin();
                break;
            case 'new_customer':
                $this->newCustomer();
                // $this->bindVirtualCard();
                break;
            case 'bindDiffentPhone':
                $this->sendBindSms();
                break;
            case 'checkCode':
                $this->checkSmsCode();
                break;
            case 'checkCard':
                $this->checkCard();
                break;
            case 'checkCardNew':
                $this->checkCardNew();
                break;
            case 'checkCodeByCard':
                $this->checkSmsCodeByCard();
                break;
            case 'editProfileData':
                $this->editProfileData();
                break;
            case 'getLoyaltyProfile':
                $this->getLoyaltyProfile();
                break;
            default:
                break;
        }
    }

    /**
     * Диагностика: сравнивает локальный профиль контакта с тем, что реально
     * сохранено в системе лояльности (dbo.Customers/CustomerPropertyValues) —
     * чтобы проверить, что синхронизация профиля не поломалась.
     *
     * Доступ: только администратор магазина, либо сам залогиненный пользователь
     * может проверить собственный профиль (contact_id по умолчанию — текущий юзер).
     *
     * GET-параметры:
     *   contact_id — необязателен для не-админа (тогда берётся текущий пользователь);
     *                для админа можно передать id любого контакта.
     *
     * Пример: /ratimirroyalty/?action=getLoyaltyProfile&contact_id=123
     */
    private function getLoyaltyProfile()
    {
        header('Content-Type: application/json; charset=utf-8');

        $currentUserId = wa()->getUser()->getId();
        $isAdmin = wa()->getUser()->isAdmin('shop');
        $contact_id = waRequest::get('contact_id', $currentUserId, 'int');

        if (!$isAdmin && $contact_id != $currentUserId) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Доступно только для своего профиля или администратора'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$contact_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'contact_id не указан'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = shopRatimirroyaltyPlugin::getLoyaltyProfileByContactId($contact_id);
        echo json_encode(['status' => 'ok'] + $result, JSON_UNESCAPED_UNICODE);
    }

    private function checkCardNew()
    {
        $card = waRequest::get('card');
        $contact_id = waRequest::get('contact_id');

        $sqlHelper = new shopRatimirroyaltyPluginSQL();
        $accountData = $sqlHelper->getAccountDataByDiscountCard($card);
        $model = new shopRatimirroyaltyPluginLocalTableDiscountCardsModel();
        $account_model = new shopRatimirroyaltyPluginLocalTableAccountsModel();
        if (!empty($accountData['AccountID'])) {
            $account_id = $accountData['AccountID'];

            if (!empty($accountData['CustomerID'])) {
                $customer_id = $accountData['CustomerID'];
                $phone = $accountData['Phone'];

                if (!empty($phone)) {
                    $this->sendSMSAndLog($phone, $model);
                    return;
                } else {
                    $this->bindPhoneToCustomer($contact_id, $customer_id, $sqlHelper);
                    waLog::dump($accountData);

                    echo json_encode(['status' => 'success']);
                    return;
                }
            } else {
                $this->bindCustomerToCard($contact_id, $account_id, $card, $account_model, $sqlHelper, $model);
                return;
            }
        }

        echo json_encode(['status' => 'no-card']);
        return;
    }

    private function sendSMSAndLog($phone, $model)
    {
        $phone = $phone['Phone'];
        $sms = new waSMS();
        $code = random_int(1000, 9999);
        $query = "INSERT INTO royalty_sms (phone, code) VALUES ('{$phone}', '{$code}')";
        $model->exec($query);
        $sms->send($phone, $code, 'Ratimir');

        // waLog::dump($phone);


        echo json_encode(['action' => 'sendedSMS', 'phone' => $phone]);
    }

    private function bindPhoneToCustomer($contact_id, $customer_id, $sqlHelper)
    {
        $contact = new waContact($contact_id);
        // waLog::dump($contact);
        $phone = $contact->get('phone', 'value');
        // waLog::dump($phone);
        if (is_array($phone)) {
            foreach ($phone as $ph) {
                break;
            }
        }
        $phone = $ph;
        $phone = shopRatimirroyaltyPlugin::normalizePhoneNumber($phone);
        // waLog::dump($phone);
        // waLog::dump($contact_id, $customer_id);
        $sqlHelper->insertPhone($phone, $customer_id);
    }

    private function bindCustomerToCard($contact_id, $account_id, $card, $account_model, $sqlHelper, $model)
    {
        $contact = new waContact($contact_id);
        $phone = $contact->get('phone', 'value')[0] ?? null;
        $firstName = $contact->get('first_name', 'value');
        $middleName = $contact->get('second_name', 'value');
        $lastName = $contact->get('last_name', 'value');

        $customerID = $sqlHelper->bindCustomerToCard($account_id, $phone, $firstName, $middleName, $lastName, $card);

        if ($customerID) {
            $account_model->updateByField('AccountID', $account_id, ['CustomerID' => $customerID]);
            if (!empty($phone)) {
                $this->sendSMSAndLog($phone, $model);
            } else {
                $this->bindPhoneToCustomer($contact_id, $customerID, $sqlHelper);
                echo json_encode(['status' => 'success']);
            }
        } else {
            echo json_encode(['status' => 'error-binding']);
        }
    }


    private function checkCard()
    {
        $card = waRequest::get('card');
        $contact_id = waRequest::get('contact_id');


        $model = new shopRatimirroyaltyPluginLocalTableDiscountCardsModel();
        $cardOwner = $model->getByField('Barcode', $card);


        if (isset($cardOwner['AccountID']) && !empty($cardOwner['AccountID'])) {
            $account_id = $cardOwner['AccountID'];
            $account_model = new shopRatimirroyaltyPluginLocalTableAccountsModel();
            $customer = $account_model->getByField('AccountID', $account_id);


            if (isset($customer['CustomerID']) && !empty($customer['CustomerID'])) {
                // Клиент уже привязан, продолжаем как обычно
                $customer_id = $customer['CustomerID'];
                $phone_model = new shopRatimirroyaltyPluginLocalTableCustomerPhonesModel();
                $phone = $phone_model->getByField('CustomerID', $customer_id);
                waLog::dump($phone);


                if (isset($phone['Phone']) && !empty($phone['Phone'])) {
                    $phone = $phone['Phone'];
                    $sms = new waSMS();
                    $code = random_int(1000, 9999);

                    $model->exec('INSERT INTO `royalty_sms` (`phone`, `code`) VALUES ("' . $phone . '", "' . $code . '");');

                    echo '{"action":"sendedSMS","phone":"' . $phone . '"}';
                    return;
                } else {
                    shopRatimirroyaltyPlugin::connectDataFromLocalTables($contact_id);

                    echo 'Success';
                    return;
                }
            } else {
                // Клиент не привязан к карте, привязываем его 


                // Получаем данные о клиенте из контактов
                $contact = new waContact($contact_id);
                $phone = $contact->get('phone', 'value');
                $firstName = $contact->get('first_name', 'value');
                $middleName = $contact->get('second_name', 'value');
                $lastName = $contact->get('last_name', 'value');

                // Используем новый метод для привязки клиента
                $sql = new shopRatimirroyaltyPluginSQL();
                $customerID = $sql->bindCustomerToCard($account_id, $phone[0], $firstName, $middleName, $lastName, $card);

                if ($customerID) {


                    // Обновляем запись в таблице accounts
                    $account_model->updateByField('AccountID', $account_id, ['CustomerID' => $customerID]);

                    // Далее продолжаем как если бы CustomerID был сразу
                    $phone_model = new shopRatimirroyaltyPluginLocalTableCustomerPhonesModel();
                    $phone = $phone_model->getByField('CustomerID', $customerID);


                    if (isset($phone['Phone']) && !empty($phone['Phone'])) {
                        $phone = $phone['Phone'];
                        $sms = new waSMS();
                        $code = random_int(1000, 9999);

                        $model->exec('INSERT INTO `royalty_sms` (`phone`, `code`) VALUES ("' . $phone . '", "' . $code . '");');

                        echo '{"action":"sendedSMS","phone":"' . $phone . '"}';
                        return;
                    } else {
                        shopRatimirroyaltyPlugin::connectDataFromLocalTables($contact_id);

                        echo 'Success';
                        return;
                    }
                } else {

                    echo json_encode('error-binding');
                    return;
                }
            }
        }

        echo json_encode('no-card');
        return;
    }

    private function checkSmsCodeByCard()
    {
        $contact_id = waRequest::get('contact_id');
        $phone = waRequest::get('phone');
        $code = waRequest::get('code');
        $barcode = waRequest::get('barcode');

        $model = new waModel();
        $codeArray = $model->query('SELECT * FROM `royalty_sms` WHERE phone = "' . $phone . '"')->fetchAll();


        $_code = end($codeArray)['code'] ?? null;

        if ($code == $_code) {
            // $data = shopRatimirroyaltyPlugin::connectDataFromLocalTables($contact_id, $phone);


            if (!is_null($barcode) && !empty($barcode)) {
                $model->exec("DELETE FROM `royalty_sms` WHERE `phone` = '" . $phone . "';");
                $model = new shopRatimirroyaltyPluginModel();
                $model->updateCardNumber($contact_id, $barcode);
                echo 'Success';
                return;
            } else {
                $model->exec("DELETE FROM `royalty_sms` WHERE `phone` = '" . $phone . "';");

                echo json_encode('no-card');
                return;
            }
        } else {

            echo "wrong";
            return;
        }
    }
    private function firstLogin()
    {
        $contact_id = waRequest::get('contact_id');


        $data = shopRatimirroyaltyPlugin::getDataFromAPI($contact_id);


        if ($data === null) {
            echo 'Ошибка в телефоне';
        } else {
            echo $data['cards']['a:Number'];
        }
    }
    private function newCustomer()
    {
        $contact_id = waRequest::get('contact_id');
        $contact = new waContact($contact_id);


        $phone = $contact->get('phone', 'value');
        $firstName = $contact->get('first_name', 'value');
        $secondName = $contact->get('second_name', 'value');
        $lastName = $contact->get('last_name', 'value');

        if (empty($phone) || empty($phone[0])) {

            return null;
        }

        $phone = preg_replace('/[^\d+]/', '', $phone[0]);
        $phone = shopRatimirroyaltyPlugin::normalizePhoneNumber($phone);


        $sql = new shopRatimirroyaltyPluginSQL();
        $data = $sql->createNewCustomer($phone, $firstName, $secondName, $lastName);

        sleep(5);

        $this->bindVirtualCard($contact_id);
        return $data;
    }
    private function bindVirtualCard($contact_id = '')
    {
        if (empty($contact_id)) {
            $contact_id = waRequest::get('contact_id');
        }

        $card = shopRatimirroyaltyPlugin::bindVirtualCard($contact_id);
        if (!is_null($card)) {
            echo 'success';
        } else {
            echo 'error';
        }
    }
    private function sendBindSms()
    {
        $contact_id = waRequest::get('contact_id');
        $sms = new waSMS();
        $phone = waRequest::get('phone');
        $model = new waModel();
        $code = random_int(1000, 9999);

        $model->exec('INSERT INTO `royalty_sms` (`phone`, `code`) VALUES ("' . $phone . '", "' . $code . '");');

        return;
    }
    private function checkSmsCode()
    {
        $contact_id = waRequest::get('contact_id');
        $model = new waModel();
        $phone = waRequest::get('phone');
        $code = waRequest::get('code');
        $codeArray = $model->query('SELECT * FROM `royalty_sms` WHERE phone = "' . $phone . '"')->fetchAll();
        foreach ($codeArray as $codeA) {
            $_code = $codeA['code'];
        }
        if ($code == $_code) {
            $data = shopRatimirroyaltyPlugin::connectDataFromLocalTables($contact_id);
            if (!is_null($data) && !empty($data['card']['card'])) {
                $model->exec("DELETE FROM `royalty_sms` WHERE `phone` = '" . $phone . "';");
                echo 'Success';
                return;
            } else {
                $model->exec("DELETE FROM `royalty_sms` WHERE `phone` = '" . $phone . "';");
                echo json_encode('no-card');
                return;
            }
        } else {
            echo "wrong";
            return;
        }
    }


    private function editProfileData()
    {
        $get = waRequest::get();

        waLog::dump('array of get', $get, 'editProfileData.log');
        // Извлекаем данные профиля из массива $get['profile']
        $firstname = $get['profile']['firstname'] ?? '';
        $middlename = $get['profile']['middlename'] ?? '';
        $lastname = $get['profile']['lastname'] ?? '';
        $phone = $get['profile']['phone'] ?? '';
        $sex = $get['profile']['sex'] ?? '';

        $birthday_day = $get['profile']['birthday']['day'] ?? '';
        $birthday_month = $get['profile']['birthday']['month'] ?? '';
        $birthday_year = $get['profile']['birthday']['year'] ?? '';


        // Получить customerId по номеру телефона в таблице royalty_customerphones
        $customerPhonesModel = new shopRatimirroyaltyPluginLocalTableCustomerPhonesModel();
        $customerPhoneData = $customerPhonesModel->getByField('Phone', waContactPhoneField::cleanPhoneNumber($phone));

        waLog::dump('customer phone data', $customerPhoneData, 'editProfileData.log');

        if (!$customerPhoneData) {

            return;
        }

        $customerID = $customerPhoneData['CustomerID'];

        try {
            $sqlHelper = new shopRatimirroyaltyPluginSQL();

            // Сформируем дату рождения
            $birthday = sprintf('%04d-%02d-%02d', $birthday_year, $birthday_month, $birthday_day);

            // Вызываем метод для обновления данных клиента
            $sqlHelper->updateCustomerProfile($customerID, $firstname, $middlename, $lastname, $birthday, $sex);

            // Успешный ответ
            echo json_encode([
                'status' => 'success',
                'message' => 'Профиль успешно обновлен'
            ]);
        } catch (Exception $e) {
            // Логируем ошибку (если у тебя есть система логирования)
            waLog::log("Ошибка обновления профиля: " . $e->getMessage(), 'editProfileData.log');

            // Возвращаем JSON с ошибкой
            echo json_encode([
                'status' => 'error',
                'message' => 'Ошибка при обновлении профиля'
            ]);
        }


    }
}