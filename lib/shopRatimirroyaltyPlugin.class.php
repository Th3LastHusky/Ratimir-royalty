<?php

class shopRatimirroyaltyPlugin extends shopPlugin
{
    /**
     * Приведение номера телефона к стандартному виду 79990009999
     * @param mixed $phoneNumber
     * @return string|null
     */
    public static function normalizePhoneNumber($phoneNumber)
    {
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);

        // Если номер начинается с 8, заменяем его на 7
        if (strlen($phoneNumber) == 11 && $phoneNumber[0] == '8') {
            $phoneNumber[0] = '7';
        }

        // Если номер начинается с 7 и имеет длину 11 символов, оставляем его как есть
        if (strlen($phoneNumber) == 11 && $phoneNumber[0] == '7') {
            return $phoneNumber;
        }

        // Если номер начинается с 9 и имеет длину 10 символов, добавляем 7 в начале
        if (strlen($phoneNumber) == 10 && $phoneNumber[0] == '9') {
            return '7' . $phoneNumber;
        }

        // Если номер не соответствует ожидаемым форматам, возвращаем null
        return null;
    }
    public static function createAPI($api, $log = false)
    {

        $wsdl = "https://vpn.ratimir.ru:1681/RS.Loyalty.WebClientPortal.Service/RSLoyaltyClientService.svc?wsdl";
        $options = [
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 30,
            'location' => 'https://vpn.ratimir.ru:1681/RS.Loyalty.WebClientPortal.Service/RSLoyaltyClientService.svc?wsdl',
            'uri' => 'https://vpn.ratimir.ru:1681/RS.Loyalty.WebClientPortal.Service/RSLoyaltyClientService.svc?wsdl',
            'cache_wsdl' => WSDL_CACHE_NONE,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ])
        ];
        $client = $api->initializeSoapClient($wsdl, $options);
        if ($log) {
            waLog::dump($client, 'bindVirtalCard.log');
        }
        return $client;
    }

    /**
     * Привязка к пользователю виртуальной карты посредством api
     * @param mixed $contact_id
     * @return array|null
     */
    public static function bindVirtualCard($contact_id, $log = false)
    {
        $api = new shopRatimirroyaltyPluginAPI();
        $client = shopRatimirroyaltyPlugin::createAPI($api);
        if ($log) {
            waLog::dump($client, 'bindVirtalCard.log');
        }
        $tokenParams = $api->tokenParams($contact_id);
        if ($tokenParams === null) {
            return null;
        }
        $token = $api->executeSoapCall($client, 'GetTokenByType', $tokenParams);
        $token = $token["s:Body"]["GetTokenByTypeResponse"]["GetTokenByTypeResult"];
        $params = [
            'token' => $token
        ];
        $bindVirtualCard = $api->executeSoapCall($client, 'BindVirtualDiscountCardToCustomer', $params);
        
        if ($bindVirtualCard['s:Body']['BindVirtualDiscountCardToCustomerResponse']['BindVirtualDiscountCardToCustomerResult'] == []) {
            $cards = $api->executeSoapCall($client, 'GetDiscountCards', $params);
            $cards = $cards['s:Body']['GetDiscountCardsResponse']['GetDiscountCardsResult']["a:DiscountCard"];
            
            $model = new shopRatimirroyaltyPluginModel();
            $model->updateCardNumber($contact_id, $cards['a:Number']);
            $discountCardID = $cards['a:DiscountCardID'];
            $sql = new shopRatimirroyaltyPluginSQL();
            $barcode = $sql->getBarcode($discountCardID);
            $model->updateCardNumber($contact_id, $barcode);
        }
        
        // $HasFreeVirtualDiscountCards = $api->executeSoapCall($client, 'HasFreeVirtualDiscountCards', $params);
        // wa_dumpc($HasFreeVirtualDiscountCards);
        return $bindVirtualCard;
    }
    public static function getDataFromAPI($contact_id)
    {

        $api = new shopRatimirroyaltyPluginAPI();
        $client = shopRatimirroyaltyPlugin::createAPI($api);
        $tokenParams = $api->tokenParams($contact_id);

        if ($tokenParams === null) {
            
            return 'no-phone';
        }
        $token = $api->executeSoapCall($client, 'GetTokenByType', $tokenParams);
        $token = $token["s:Body"]["GetTokenByTypeResponse"]["GetTokenByTypeResult"];
        $getCardsParams = [
            'token' => $token
        ];
        
        $cards = $api->executeSoapCall($client, 'GetDiscountCards', $getCardsParams);
        $cards = $cards['s:Body']['GetDiscountCardsResponse']['GetDiscountCardsResult']["a:DiscountCard"];

        $indicatorsParams = [
            'token' => $token
        ];
        $bonuses = $api->executeSoapCall($client, 'GetIndicators', $indicatorsParams);
        $bonuses = $bonuses["s:Body"]["GetIndicatorsResponse"]["GetIndicatorsResult"]["a:Indicator"];
        $bonus = 0;
        foreach ($bonuses as $bonuse) {
            if ($bonuse['a:Company']['a:CompanyID'] == 3) {
                $bonus = $bonuse["a:BonusAmount"];
            }
        }

        //объект карты такой-же как и в запросе по токену
        $card_id = (string) $cards['a:DiscountCardID'];
        $cardParams = [
            'token' => $token,
            'discountCardId' => $card_id
        ];
        $cardObject = $api->executeSoapCall($client, 'GetDiscountCardById', $cardParams);

        // wa_dumpc($cardObject);

        $returnArray = [
            'cards' => $cards,
        ];
        $model = new shopRatimirroyaltyPluginModel();
        $model->updateAffiliate($contact_id, $bonus);
        $model->updateCardNumber($contact_id, $cards['a:Number']);
        return $returnArray;
    }
    /**
     * Получение количества бонусов по api
     * @param mixed $contact_id
     * @return void
     */
    public static function syncBonuses($contact_id)
    {
        $api = new shopRatimirroyaltyPluginAPI();
        $model = new waModel();
        $client = shopRatimirroyaltyPlugin::createAPI($api);
        $tokenParams = $api->tokenParams($contact_id);
        

        $token = $api->executeSoapCall($client, 'GetTokenByType', $tokenParams);
        

        $token = $token["s:Body"]["GetTokenByTypeResponse"]["GetTokenByTypeResult"];
        $indicatorsParams = [
            'token' => $token
        ];
        $bonuses = $api->executeSoapCall($client, 'GetIndicators', $indicatorsParams);
        $bonuses = $bonuses["s:Body"]["GetIndicatorsResponse"]["GetIndicatorsResult"]["a:Indicator"];
        foreach ($bonuses as $bonuse) {
            if ($bonuse['a:Company']['a:CompanyID'] == 3) {
                $bonus = $bonuse["a:BonusAmount"];
            }
        }
        if (!empty($bonus)) {

            $model->query("UPDATE `shop_customer` SET `affiliate_bonus` = " . $bonus . " WHERE `contact_id` = " . $contact_id);
        } else {
            
        }
    }
    public static function getCardNumber($contact_id)
    {
        $model = new shopRatimirroyaltyPluginModel();
        $card_number = $model->getCardNumber($contact_id);
        return $card_number['card'];
    }
    /** 
     * Данные - это номер карты и баланс на кошельке
     * Функция только для получения тех данных, что находятся в скопированных из бд ратимира таблицах
     * @param int $contact_id waContactId
     * 
     
    */
    public static function getLocalData($contact_id, $phone = null)
    {
        

        // Если телефон не передан, получаем его из контакта
        if (is_null($phone)) {
            $contact = new waContact($contact_id);
            $phone = $contact->get('phone', 'value');

            if (empty($phone) || empty($phone[0])) {
                
                return null;
            }

            // Нормализация номера телефона
            $phone = self::normalizePhoneNumber($phone[0]);
        } else {
            // Нормализуем переданный телефон
            $phone = self::normalizePhoneNumber($phone);
        }

        // Работа с локальной моделью
        $localModel = new shopRatimirroyaltyPluginLocalModel();

        // Получаем customer_id по номеру телефона
        $customer_id = $localModel->getCustomerId($phone);

        if (is_null($customer_id) || empty($customer_id)) {
            
            return null;
        }

        // Получаем account_id по customer_id
        $account_id = $localModel->getAccountId($customer_id);

        // Проверка на наличие account_id
        if (is_null($account_id) || empty($account_id)) {
            
            return null;
        }

        // Получаем баланс по account_id
        $balance = $localModel->getIndicatorBalance($account_id);

        // Получаем данные карты по account_id
        $card = $localModel->getCard($account_id);

        // Возвращаемые данные
        return [
            'card' => $card,
            'balance' => $balance
        ];
    }


    /**
     * Получаем данные из выгруженных таблиц и пишем их в royalty_ratimir
     * @param mixed $contact_id
     * @return array|null
     */
    public static function connectDataFromLocalTables($contact_id, $phone = null)
    {
        

        // Получение данных из локальных таблиц в зависимости от наличия телефона
        if (!is_null($phone)) {
            
            $localData = self::getLocalData($contact_id, $phone);
        } else {
            
            $localData = self::getLocalData($contact_id);
        }

        // Проверяем, получены ли данные
        if (is_null($localData)) {
            
            return null;
        }

        // Логирование полученных данных
        

        $card = $localData['card'];
        $balance = $localData['balance'];
        $customer_id = $localData['customer_id'];
        // Получаем данные об имени из ratimir_customers и вносим их в наш контакт
        $customerModel = new shopRatimirroyaltyPluginLocalTableCustomersModel();
        $customer = $customerModel->getByField('CustomerID', $customer_id);
        $firstName = $customer['FirstName'];
        $secondName = $customer['SecondName'];
        $lastName = $customer['LastName'];
        $contact = new waContact();
        $contact->set('firstname', $firstName);
        $contact->set('middlename', $secondName);
        $contact->set('lastname', $lastName);
        $contact->set('name', $firstName . ' ' . $secondName . ' ' . $lastName);
        // Обновляем данные карты и баланса
        $model = new shopRatimirroyaltyPluginModel();
        
        $model->updateCardNumber($contact_id, $card['barcode']);

        
        $model->updateAffiliate($contact_id, $balance);

        // Подготавливаем данные для возврата
        $data = [
            'card' => $card,
            'balance' => $balance
        ];

        

        return $data;
    }

    /**
     * Костыль для доставания из indicators значения companyId по barcode
     * @param mixed $AccountID
     * @return void
     */
    public static function getCompanyId($barcode) {
        $dcModel = new shopRatimirroyaltyPluginLocalTableDiscountCardsModel();
        $dc = $dcModel->getByField('Barcode', $barcode);
        $AccountID = $dc['AccountID'];
        $model = new shopRatimirroyaltyPluginLocalTableIndicatorsModel();
        $userData = $model->getByField('AccountID', $AccountID);
        return $userData['CompanyID'];
    }


    /**
     * Функция для получения данных в шаблоне, сначала проверяем в royalty_ratimir, потом в выгруженных таблицах
     * @param mixed $contact_id
     * @return array|null
     */
    public static function getContactData($contact_id)
    {
        

        $model = new shopRatimirroyaltyPluginModel();

        // Получаем номер карты клиента
        
        $card = $model->getCardNumber($contact_id);
        // waLog::dump('card data for contact_id = '.$contact_id, $card, 'royalty/card.log');
        if (is_null($card) || empty($card['card'])) {
            

            // Проверка на локальных таблицах
            $localData = self::connectDataFromLocalTables($contact_id);
            if (!empty($localData['card']['barcode'])) {
                
                $localData['card']['card'] = $localData['card']['barcode'];
                return $localData;
            } else {
                
            }

            /* Логирование убрано для закомментированного кода
            $apiData = self::getDataFromAPI($contact_id);
            if ($apiData == 'no-phone') {
                
                return null;
            }
            if (!is_null($apiData)) {
                $card = $model->getCardNumber($contact_id);
                $balance = $model->query("SELECT * FROM shop_customer WHERE contact_id = ".$contact_id)->fetch();
            } else {
                
                return null;
            }
            */
        }

        
        $balance = $model->query("SELECT * FROM shop_customer WHERE contact_id = " . $contact_id)->fetch();

        if (!empty($balance)) {
            $balance = $balance["affiliate_bonus"];
            
        }

        if (empty($balance) || is_null($balance)) {
            
            $balance = 0;
            $cardModel = new shopRatimirroyaltyPluginLocalTableDiscountCardsModel();

            if (is_array($card)) {
                $_card = $card['card'];
                
            }

            $account_id = $cardModel->getByField("barcode", $_card);
            $account_id = $account_id["AccountID"];
            

            $balModel = new shopRatimirroyaltyPluginLocalTableIndicatorsModel();
            $bal = $balModel->getByField('AccountID', $account_id, true);
            

            foreach ($bal as $b) {
                $balance += $b['Bonus'];
            }
            
        }
        if (is_null($card['DiscountCardID']) && is_null($card['DiscountCardGroupID']) && !empty($card['card'])) {
            try {
                $localCardModel = new shopRatimirroyaltyPluginLocalTableDiscountCardsModel();
                $c2cgModel = new shopRatimirroyaltyPluginLocalTableDc2dcgModel();
    
                $cardInfo = $localCardModel->getByField('Barcode', $card['card']);
                $cardId = $cardInfo['DiscountCardID'];
                $cardGroupInfo = $c2cgModel->getByField('DiscountCardID', $cardId);
                $cardGroupId = $cardGroupInfo['DiscountCardGroupID'];
                $updatedCheck = $model->updateByField('contact_id', (int)$contact_id, [
                    'DiscountCardID' => (int)$cardId,
                    'DiscountCardGroupID' => (int)$cardGroupId
                ]);
                // waLog::dump('Card data updated for contact_id = '.$contact_id, $updatedCheck, $card, 'royalty/updateCardInfo.log');
                $card['DiscountCardID'] = $cardId;
                $card['DiscountCardGroupID'] = $cardGroupId;
            } catch (waException $e) {
                waLog::dump('Failed to add updated data to royalty_ratimir', $card, $e->getMessage(), 'royalty/getCotactData.log');
            }
            
        }
        $result = [
            "card" => $card,
            "balance" => $balance
        ];

        
        return $result;
    }

    public static function testSelect(): void
    {
        $serverName = "td.ratimir.ru";
        $port = 42981;
        $database = "RS_LOYALTY";
        $username = "loy_sql";
        $password = "YE7zFG4^Gf9*9k9!";
        try {
            $dsn = sprintf("dblib:host=%s:%d;dbname=%s;charset=UTF-8", $serverName, $port, $database);
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);




            $sql = "SELECT * FROM dbo.Customers WHERE FirstName LIKE '%2991415%'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([]);
            wa_dumpc($sql);
            // Получение результатов
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($res as $t) {
                wa_dumpc($t);
            }

            $sql = "SELECT * FROM dbo.CustomerPhones WHERE Phone LIKE '%2991415%'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([]);
            wa_dumpc($sql);
            // Получение результатов
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($res as $t) {
                wa_dumpc($t);
            }
            $api = new shopRatimirroyaltyPluginAPI();
            $client = shopRatimirroyaltyPlugin::createAPI($api);
            $tokenParams = [
                'authToken' => '79502991415',
                'type' => 'Phone',
            ];
            $token = $api->executeSoapCall($client, 'GetTokenByType', $tokenParams);
            wa_dumpc($token);
            $token = $token["s:Body"]["GetTokenByTypeResponse"]["GetTokenByTypeResult"];
            wa_dumpc($token);
            $params = [
                'token' => $token
            ];
            function checkUrlAvailability($url)
            {
                // Инициализация cURL
                $ch = curl_init($url);

                // Установка опций cURL
                // curl_setopt($ch, CURLOPT_NOBODY, true); // Не загружать тело ответа
                curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Установить таймаут в секундах
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Следовать за редиректами
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Возвращать ответ как строку
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Отключить проверку SSL-сертификата (если необходимо)

                // Выполнение запроса
                curl_exec($ch);
                wa_dumpc(curl_getinfo($ch));
                // Получение HTTP-кода ответа
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                wa_dumpc($httpCode);
                // Закрытие cURL-сессии
                curl_close($ch);

                // Проверка кода ответа
                return ($httpCode >= 200 && $httpCode < 400);
            }

            // Пример использования
            $url = "https://vpn.ratimir.ru:1681/RS.Loyalty.WebClientPortal.Service/RSLoyaltyClientService.svc?wsdl";
            if (checkUrlAvailability($url)) {
                echo "URL доступен.";
            } else {
                echo "URL недоступен.";
            }
        } catch (PDOException $e) {
            
        }
    }
    public static function testSelectT()
    {
        $sql = new shopRatimirroyaltyPluginSQL();
        waLog::dump($sql->getCustomerPhones(),'testSelectT.log');
    }
}
