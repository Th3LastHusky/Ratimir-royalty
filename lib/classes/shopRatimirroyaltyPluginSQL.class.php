<?php
class shopRatimirroyaltyPluginSQL
{
    /**
     * Описание таблицы
     */
    public function getCustomersTableDescription()
    {
        try {
            $conn = $this->getLoyaltyDBConnection();
            $query = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'Customers'";

            $stmt = $conn->query($query);

            // Получение всех строк результата
            $tableDescription = $stmt->fetchAll(PDO::FETCH_ASSOC);

            

            return $tableDescription;
        } catch (Exception $e) {
            
            return [];
        }
    }

    /**
     * Получение кодировки таблицы
     */
    public function getTableCollationFromColumns($tableName = 'Customers')
    {
        try {
            $conn = $this->getLoyaltyDBConnection();

            // Запрос для получения кодировки столбцов таблицы
            $query = "SELECT COLUMN_NAME, COLLATION_NAME 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_NAME = ? AND TABLE_SCHEMA = 'dbo'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$tableName]);

            // Получение результата
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            

            // Если кодировка есть у первого столбца, возвращаем её
            if (!empty($columns)) {
                return $columns[0]['COLLATION_NAME'] ?? null;
            }

            return null;
        } catch (Exception $e) {
            
            return null;
        }
    }



    /** 
     * Получаем пользователей роялти
     */
    public function getCustomers()
    {
        try {
            $conn = $this->getLoyaltyDBConnection();
            $query = "SELECT * FROM dbo.Customers";
            $stmt = $conn->query($query);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            

            return $customers;
        } catch (Exception $e) {
            
            return [];
        }
    }

    public function getCategories()
    {
        try {
            $conn = $this->getLoyaltyDBConnection();
            $query = "SELECT * FROM dbo.DiscountCardGroups";
            $stmt = $conn->query($query);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            

            return $customers;
        } catch (Exception $e) {
            
            return [];
        }
    }
    public function getCustomerPhones()
    {
        try {
            $conn = $this->getLoyaltyDBConnection();
            $query = "SELECT * FROM dbo.CustomerPhones WHERE Phone = '79800884843'";
            $stmt = $conn->query($query);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
            return $customers;
        } catch (Exception $e) {
            
            return [];
        }
    }

    public function otvyazkaCartsYea()
    {
        try {
            
        } catch (Exception $e) {
            waLog::dump($e);
            return [];
        }
    }

    /**
     * Функция для получения номера телефона пользователя по его карте
     * @param mixed $barcode
     * @return mixed
     */
    public function getAccountDataByDiscountCard($barcode) {
        // $barcode = strval($barcode);
        $conn = $this->getLoyaltyDBConnection();
        // waLog::dump($barcode);
        $query = "
            SELECT 
                a.AccountID, 
                a.CustomerID
            FROM dbo.DiscountCards dc
            INNER JOIN dbo.Accounts a ON dc.AccountID = a.AccountID
            WHERE dc.Barcode = :barcode
        ";
    
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute([':barcode' => $barcode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                return null;
            }
            $query = "SELECT Phone FROM dbo.CustomerPhones WHERE CustomerID = :customer";
            $stmt = $conn->prepare($query);
            $stmt->execute([':customer' => $result['CustomerID']]);
            $result['Phone'] = $stmt->fetch(PDO::FETCH_ASSOC);
            // waLog::dump($result);
            // return null;
            // $query = "SELECT AccountID FROM dbo.DiscountCards WHERE Barcode LIKE :barcode";
            // $stmt = $conn->prepare($query);
            // $stmt->execute([':barcode' => $barcode]);
            // $dcard = $stmt->fetch(PDO::FETCH_ASSOC);
            // waLog::dump($dcard);
        } catch (PDOException $e) {
            waLog::dump($e->getMessage());
            return null;
        }
    
        return $result ?: null;
    }
    public function insertPhone($phone, $customerID) {
        try {
            $conn = $this->getLoyaltyDBConnection();
            $sql = "INSERT INTO dbo.CustomerPhones (PropertyID, CustomerID, Phone) VALUES (?, ?, ?);";
            $params = array(57, $customerID, $phone);
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            waLog::dump($e->getMessage());
            return null;
        }
        
    }


    /**
     * Функция для создания нового пользователя в базе royalty
     * 
     * @param string $phone
     * @param string $firstName
     * @param string $middleName
     * @param string $lastName
     * @return void
     */
    public function createNewCustomer($phone, $firstName = '', $middleName = '', $lastName = '')
    {
        $firstName = empty($firstName) ? $phone : $firstName;
        $lastName = empty($lastName) ? $phone : $lastName;
        $middleName = empty($middleName) ? $phone : $middleName;

        try {
            // Получаем подключение к базе данных роялти
            $conn = $this->getLoyaltyDBConnection();
            $currentDateTime = date('Y-m-d H:i:s');


            // Проверяем наличие существующего аккаунта с таким номером телефона

            $query = "SELECT * FROM dbo.CustomerPhones WHERE Phone = {$phone}";
            $stmt = $conn->query($query);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($customers)) {
                //Мы нашли хотя бы один номер телефона
                foreach ($customers as $customer) {
                    $customerID = $customer['CustomerID'];
                    break;
                }
            } else {            

                // Вставка данных в dbo.Customers
                $sql = "INSERT INTO dbo.Customers (FirstName, SecondName, LastName, CreatedDate, ModifiedDate, ActivationDate, ActivatedTypeId) 
                    VALUES (?, ?, ?, ?, ?, ?, ?);
                    SELECT SCOPE_IDENTITY() AS CustomerID;";
                $params = array($firstName, $middleName, $lastName, $currentDateTime, $currentDateTime, $currentDateTime, 0);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $stmt->nextRowset();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $customerID = $result['CustomerID'];
                if (!$customerID) {
                    
                    
                    throw new Exception("Ошибка при получении CustomerID");
                }

                // Вставка в dbo.Accounts
                $sql = "INSERT INTO dbo.Accounts (CustomerID) VALUES (?); SELECT SCOPE_IDENTITY() AS AccountID;";
                $params = array($customerID);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $stmt->nextRowset();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $accountID = $result['AccountID'];

                if (!$accountID) {
                    throw new Exception("Ошибка при получении AccountID");
                }

                // Вставка в dbo.CustomerPhones
                $phone = preg_replace('/\D/', '', $phone);
                $sql = "INSERT INTO dbo.CustomerPhones (PropertyID, CustomerID, Phone) VALUES (?, ?, ?);";
                $params = array(57, $customerID, $phone);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }

            // Обработка данных для пола и даты рождения из Shop-Script
            $contact_id = wa()->getUser()->getId();
            $contact = new waContact($contact_id);

            // Получаем данные даты рождения
            $birth_day = $contact->get('birth_day');
            $birth_month = $contact->get('birth_month');
            $birth_year = $contact->get('birth_year');

            // Модель для локальной таблицы свойств клиента
            $customerPropertyValuesModel = new shopRatimirroyaltyPluginLocalTableCustomerpropertyvaluesModel();

            if ($birth_day && $birth_month && $birth_year) {
                $birthDate = sprintf('%04d-%02d-%02d 00:00:00', $birth_year, $birth_month, $birth_day);

                // Вставка даты рождения в dbo.CustomerPropertyValues
                $sql = "INSERT INTO dbo.CustomerPropertyValues (DateValue, CustomerID, PropertyID) VALUES (?, ?, ?);";
                $params = array($birthDate, $customerID, 54);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);

                // Вставка даты рождения в локальную таблицу через модель
                $customerPropertyValuesModel->insert(array(
                    'DateValue' => $birthDate,
                    'CustomerID' => $customerID,
                    'PropertyID' => 54
                ));
            }

            // Получаем пол клиента
            $gender = $contact->get('sex');
            if ($gender) {
                $enumGender = ($gender === 'm') ? 5 : 6;

                // Вставка пола в dbo.CustomerPropertyValues
                $sql = "INSERT INTO dbo.CustomerPropertyValues (EnumPropertyValueID, CustomerID, PropertyID) VALUES (?, ?, ?);";
                $params = array($enumGender, $customerID, 55);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);

                // Вставка пола в локальную таблицу через модель
                $customerPropertyValuesModel->insert(array(
                    'EnumPropertyValueID' => $enumGender,
                    'CustomerID' => $customerID,
                    'PropertyID' => 55
                ));
            }

            // Выполняем обновление локальных таблиц на основе данных из роялти
            $this->updateLocalTablesAfterCreation($customerID);

            return $phone;

        } catch (Exception $e) {
            
        }
    }

    /**
     * Метод для обновления локальных таблиц после создания нового клиента в роялти
     *
     * @param int $customerID Идентификатор клиента
     * @return void
     */
    public function updateLocalTablesAfterCreation($customerID)
    {
        // Подключение к базе роялти
        $conn = $this->getLoyaltyDBConnection();

        // Получаем данные из таблицы Customers
        $sql = "SELECT * FROM dbo.Customers WHERE CustomerID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$customerID]);
        $customerData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Получаем данные из таблицы Accounts
        $sql = "SELECT * FROM dbo.Accounts WHERE CustomerID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$customerID]);
        $accountData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Получаем данные из таблицы CustomerPhones
        $sql = "SELECT * FROM dbo.CustomerPhones WHERE CustomerID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$customerID]);
        $phoneData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Обновляем локальные таблицы
        $this->updateLocalTables($customerData, $accountData, $phoneData);
    }

    public function updateLocalTables($customerData, $accountData, $phoneData)
    {
        // Обновляем таблицу клиентов (Customers)
        $localCustomerModel = new shopRatimirroyaltyPluginLocalTableCustomersModel();
        $localCustomerModel->insert(array(
            'CustomerID' => $customerData['CustomerID'],
            'FirstName' => $customerData['FirstName'],
            'SecondName' => $customerData['SecondName'],
            'LastName' => $customerData['LastName'],
            'CreatedDate' => $customerData['CreatedDate'],
            'ModifiedDate' => $customerData['ModifiedDate'],
            'IsActivated' => $customerData['IsActivated'],
            'IsDeleted' => $customerData['IsDeleted'],
            'IsSubscribed' => $customerData['IsSubscribed'],
            'IsSmsSubscribed' => $customerData['IsSmsSubscribed'],
            'IsFillAllProperties' => $customerData['IsFillAllProperties'],
            'IsAddedBonusForRegistration' => $customerData['IsAddedBonusForRegistration'],
            'State' => hex2bin(trim($customerData['State'], '0x')),
            'AuthorizationToken' => $customerData['AuthorizationToken'],
            'PhoneIsChecked' => $customerData['PhoneIsChecked'],
            'IsAddedBonusForMobileRegistration' => $customerData['IsAddedBonusForMobileRegistration'],
            'AdministrativeAreaID' => $customerData['AdministrativeAreaID'],
            'LocalityID' => $customerData['LocalityID'],
            'EmailIsChecked' => $customerData['EmailIsChecked'],
            'IsSmsActivated' => $customerData['IsSmsActivated'],
            'ActivationDate' => $customerData['ActivationDate'],
            'ActivatedTypeId' => $customerData['ActivatedTypeId'],
        ));

        // Обновляем таблицу аккаунтов (Accounts)
        $localAccountModel = new shopRatimirroyaltyPluginLocalTableAccountsModel();
        $localAccountModel->insert(array(
            'AccountID' => $accountData['AccountID'],
            'CustomerID' => $accountData['CustomerID'],
            'State' => hex2bin(trim($accountData['State'], '0x')),
            'BeginBlockingDateTime' => $accountData['BeginBlockingDateTime'],
            'EndBlockingDateTime' => $accountData['EndBlockingDateTime'],
            'BlockReason' => $accountData['BlockReason'],
        ));

        // Обновляем таблицу телефонов (CustomerPhones)
        $localPhoneModel = new shopRatimirroyaltyPluginLocalTableCustomerPhonesModel();
        $localPhoneModel->insert(array(
            'PropertyID' => $phoneData['PropertyID'],
            'CustomerID' => $phoneData['CustomerID'],
            'Phone' => $phoneData['Phone'],
        ));

        
    }

    /**
     * Получение для созданной виртуальной карты правильного штрихкода 
     * @param int $AccountID
     * @return string
     */
    public function getBarcode($card_id)
    {

        $serverName = "td.ratimir.ru";
        $port = 42981;
        $database = "RS_LOYALTY";
        $username = "loy_sql";
        $password = "YE7zFG4^Gf9*9k9!";
        // $dsn = sprintf("odbc:DRIVER=FreeTDS;SERVER=%s;PORT=%d;DATABASE=%s", $serverName, $port, $database);
        $dsn = sprintf("dblib:host=%s:%d;dbname=%s;charset=CP1251;tds_version=8.0", $serverName, $port, $database);
        $conn = new PDO($dsn, $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        // $sql = "SELECT * FROM dbo.DiscountCards WHERE DiscountCardID = ?;";
        // $sql = "SELECT * FROM dbo.DiscountCards WHERE DiscountCardID = CAST(? AS VARCHAR);";
        $sql = "SELECT DiscountCardID, CAST(Number AS VARCHAR(8000)) AS Number, AccountID, DiscountCardUID, CAST(Barcode AS VARCHAR(8000)) AS Barcode FROM dbo.DiscountCards WHERE DiscountCardID = ?;";
        // $params = array($card_id);
        $params = array(iconv('UTF-8', 'CP1251//IGNORE', $card_id));
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $barcode = $result['Barcode'];
        return $barcode;
    }
    public function getLoyaltyDBConnection()
    {
        // Параметры подключения к базе данных системы роялти
        $serverName = "td.ratimir.ru";
        $port = 42981;
        $database = "RS_LOYALTY";
        // $username = "loy_sql";
        // $password = "YE7zFG4^Gf9*9k9!";
        $username = "u_loy";
        $password = "vM444zcEWifO7d";
        try {
            // Формирование DSN (Data Source Name) для подключения через ODBC
            // $dsn = sprintf("odbc:DRIVER=FreeTDS;SERVER=%s;PORT=%d;DATABASE=%s", $serverName, $port, $database);
            $dsn = sprintf("dblib:host=%s:%d;dbname=%s;charset=UTF-8", $serverName, $port, $database);
            // Создание объекта PDO для подключения
            $conn = new PDO($dsn, $username, $password);

            // Установка режима обработки ошибок (генерация исключений)
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            

            return $conn;
        } catch (PDOException $e) {
            // Логирование ошибки подключения
            waLog::dump($e->getMessage(),'royalty/sqlError.log');
            throw new Exception("Ошибка подключения к базе данных роялти: " . $e->getMessage());
        }
    }

    public function getLoyaltyDBConnectionCirilic()
    {
        // Параметры подключения к базе данных системы роялти
        $serverName = "td.ratimir.ru,42981"; // Сервер и порт
        $database = "RS_LOYALTY";
        $username = "loy_sql";
        $password = "YE7zFG4^Gf9*9k9!";

        try {
            // Формирование DSN для драйвера sqlsrv
            $dsn = "sqlsrv:Server=$serverName;Database=$database";
            $options = [
                PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
            ];

            // Создание объекта PDO для подключения
            $conn = new PDO($dsn, $username, $password, $options);

            // Установка режима обработки ошибок (генерация исключений)
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            

            return $conn;
        } catch (PDOException $e) {
            // Логирование ошибки подключения
            
            throw new Exception("Ошибка подключения к базе данных роялти: " . $e->getMessage());
        }
    }


    /**
     * Привязывает карту к новому клиенту в роялти.
     * 
     * Этот метод создаёт новую запись о клиенте в таблице dbo.Customers и локально
     *
     * @param string $accountID Идентификатор учётной записи (AccountID) (НЕ ПРИВЯЗАННОЙ К КЛИЕНТУ КАРТЫ-), к которому привязывается карта.
     * @param string $phone Угадай.
     * @param string $firstName Угадай.
     * @param string $middleName Угадай.
     * @param string $lastName Угадай.
     *
     * @return int|false Возвращает CustomerID в случае успешной привязки, false в случае ошибки.
     */
    public function bindCustomerToCard($accountID, $phone, $firstName = '', $middleName = '', $lastName = '', $cardNumber)
    {
        $serverName = "td.ratimir.ru";
        $port = 42981;
        $database = "RS_LOYALTY";
        $username = "loy_sql";
        $password = "YE7zFG4^Gf9*9k9!";

        // Логируем входные данные
        

        $firstName = empty($firstName) ? $phone : $firstName;
        $lastName = empty($lastName) ? $phone : $lastName;

        

        try {
            // Логируем информацию о попытке подключения к базе данных
            

            // $dsn = sprintf("odbc:DRIVER=FreeTDS;SERVER=%s;PORT=%d;DATABASE=%s", $serverName, $port, $database);
            $dsn = sprintf("dblib:host=%s:%d;dbname=%s;charset=UTF-8", $serverName, $port, $database);
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            

            // Текущая дата
            $currentDateTime = date('Y-m-d H:i:s');

            // Вставляем нового клиента в таблицу dbo.Customers
            
            $sql = "INSERT INTO dbo.Customers (FirstName, SecondName, LastName, CreatedDate, ModifiedDate, ActivationDate, ActivatedTypeId) 
            VALUES (?, ?, ?, ?, ?, ?, ?);
            SELECT SCOPE_IDENTITY() AS CustomerID;";
            $params = array($firstName, $middleName, $lastName, $currentDateTime, $currentDateTime, $currentDateTime, 0);
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $stmt->nextRowset();

            // Получаем CustomerID
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $customerID = $result ? $result['CustomerID'] : null;

            if (!$customerID) {
                
                throw new Exception("Ошибка при получении CustomerID");
            }

            

            $phone = preg_replace('/\D/', '', $phone);
            // Логируем перед выполнением операций
            

            // Проверяем, есть ли запись с таким телефоном
            $sql = "SELECT CustomerID FROM dbo.CustomerPhones WHERE Phone = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$phone]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Обновляем CustomerID, если запись с таким телефоном уже существует
                $existingCustomerID = $result['CustomerID'];
                

                $sql = "UPDATE dbo.CustomerPhones SET CustomerID = ? WHERE Phone = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$customerID, $phone]);
            } else {
                // Вставляем новую запись, если телефона нет в таблице
                

                $sql = "INSERT INTO dbo.CustomerPhones (PropertyID, CustomerID, Phone) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([57, $customerID, $phone]);
            }

            // Обновляем запись в таблице dbo.Accounts на стороне роялти
            
            $sql = "UPDATE dbo.Accounts SET CustomerID = ? WHERE AccountID = ?";
            $params = array($customerID, $accountID);
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $firstName = iconv('CP1251', 'UTF-8//IGNORE', $firstName);
            $middleName = iconv('CP1251', 'UTF-8//IGNORE', $middleName);
            $lastName = iconv('CP1251', 'UTF-8//IGNORE', $lastName);
            $localCustomersModel = new shopRatimirroyaltyPluginLocalTableCustomersModel();
            $localCustomersModel->insert(array(
                'first_name' => $firstName,
                'second_name' => $middleName,
                'last_name' => $lastName,
                'created_date' => $currentDateTime,
                'modified_date' => $currentDateTime,
                'activation_date' => $currentDateTime,
                'activated_type_id' => 0,
            ));

            // Логируем перед выполнением операций в БД интернет-магазина
            

            $localCustomerPhonesModel = new shopRatimirroyaltyPluginLocalTableCustomerPhonesModel();

            // Проверяем наличие записи с указанным телефоном
            $existingPhone = $localCustomerPhonesModel->getByField('Phone', $phone);

            if ($existingPhone) {
                // Если телефон найден, обновляем CustomerID
                

                $localCustomerPhonesModel->updateByField('Phone', $phone, [
                    'customer_id' => $customerID
                ]);
            } else {
                // Если телефон не найден, вставляем новую запись
                

                $localCustomerPhonesModel->insert([
                    'PropertyID' => 57,
                    'CustomerID' => $customerID,
                    'Phone' => $phone,
                ]);
            }

            
            $localAccountsModel = new shopRatimirroyaltyPluginLocalTableAccountsModel();
            $localAccountsModel->updateByField('AccountID', $accountID, array('CustomerID' => $customerID));
            

            $user_id = wa()->getUser()->getId();
            $ratimirModel = new shopRatimirroyaltyPluginModel();
            $ratimirModel->updateCardNumber($user_id, $cardNumber);

            

            return $customerID;

        } catch (Exception $e) {
            waLog::dump('exception', $e->getMessage(), 'royalty/sqlError.log');
            return false;
        }
    }

    /**
     * Обновляет профиль клиента в таблицах роялти и локальных.
     *
     * @param string $customerID Идентификатор клиента.
     * @param string $firstName Имя клиента.
     * @param string $middleName Отчество клиента.
     * @param string $lastName Фамилия клиента.
     * @param string $birthDate Дата рождения клиента (в формате YYYY-MM-DD).
     * @return void
     */
    public function updateCustomerProfile($customerID, $firstName, $middleName, $lastName, $birthDate, $gender = null)
    {
        try {
            $conn = $this->getLoyaltyDBConnection();
            $currentDateTime = date('Y-m-d H:i:s');

            // SQL-запрос без лишнего CAST
            $sql = "UPDATE dbo.Customers
                    SET FirstName = N?, 
                        SecondName = N?, 
                        LastName = N?, 
                        ModifiedDate = ?
                    WHERE CustomerID = ?";
                    
            $firstName = mb_convert_encoding($firstName, 'Windows-1251', 'UTF-8');
            $middleName = mb_convert_encoding($middleName, 'Windows-1251', 'UTF-8');
            $lastName = mb_convert_encoding($lastName, 'Windows-1251', 'UTF-8');

            $params = array($firstName, $middleName, $lastName, $currentDateTime, $customerID);
            

            // Выполняем запрос
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            // Логируем параметры для отладки
            

            // Проверка на существование даты рождения в dbo.CustomerPropertyValues
            if ($birthDate) {
                $sql = "SELECT COUNT(*) FROM dbo.CustomerPropertyValues WHERE CustomerID = ? AND PropertyID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$customerID, 54]);  // 54 - ID свойства для даты рождения
                $exists = $stmt->fetchColumn();

                if ($exists) {
                    // Если запись существует, обновляем
                    $sql = "UPDATE dbo.CustomerPropertyValues 
                        SET DateValue = ? 
                        WHERE CustomerID = ? AND PropertyID = ?";
                    $params = array($birthDate, $customerID, 54);
                } else {
                    // Если записи нет, вставляем новую
                    $sql = "INSERT INTO dbo.CustomerPropertyValues (DateValue, CustomerID, PropertyID) 
                        VALUES (?, ?, ?)";
                    $params = array($birthDate, $customerID, 54);
                }
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }

            // Проверка на существование пола клиента в dbo.CustomerPropertyValues
            if ($gender) {
                $enumGender = ($gender === 'm') ? 5 : 6;  // Соответствие значений пола в системе роялти

                $sql = "SELECT COUNT(*) FROM dbo.CustomerPropertyValues WHERE CustomerID = ? AND PropertyID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$customerID, 55]);  // 55 - ID свойства для пола
                $exists = $stmt->fetchColumn();

                if ($exists) {
                    // Если запись существует, обновляем
                    $sql = "UPDATE dbo.CustomerPropertyValues 
                        SET EnumPropertyValueID = ? 
                        WHERE CustomerID = ? AND PropertyID = ?";
                    $params = array($enumGender, $customerID, 55);
                } else {
                    // Если записи нет, вставляем новую
                    $sql = "INSERT INTO dbo.CustomerPropertyValues (EnumPropertyValueID, CustomerID, PropertyID) 
                        VALUES (?, ?, ?)";
                    $params = array($enumGender, $customerID, 55);
                }
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }

            // Обновляем локальные таблицы свойств клиента
            $customerPropertyValuesModel = new shopRatimirroyaltyPluginLocalTableCustomerpropertyvaluesModel();

            // Проверка на существование и обновление даты рождения в локальной базе данных
            if ($birthDate) {
                $exists = $customerPropertyValuesModel->getByField(['CustomerID' => $customerID, 'PropertyID' => 54]);
                if ($exists) {
                    // Обновляем, если запись существует
                    $customerPropertyValuesModel->updateByField(
                        ['CustomerID' => $customerID, 'PropertyID' => 54],
                        ['DateValue' => $birthDate]
                    );
                } else {
                    // Вставляем, если записи нет
                    $customerPropertyValuesModel->insert(array(
                        'DateValue' => $birthDate,
                        'CustomerID' => $customerID,
                        'PropertyID' => 54
                    ));
                }
            }

            // Проверка на существование и обновление пола в локальной базе данных
            if ($gender) {
                $exists = $customerPropertyValuesModel->getByField(['CustomerID' => $customerID, 'PropertyID' => 55]);
                if ($exists) {
                    // Обновляем, если запись существует
                    $customerPropertyValuesModel->updateByField(
                        ['CustomerID' => $customerID, 'PropertyID' => 55],
                        ['EnumPropertyValueID' => $enumGender]
                    );
                } else {
                    // Вставляем, если записи нет
                    $customerPropertyValuesModel->insert(array(
                        'EnumPropertyValueID' => $enumGender,
                        'CustomerID' => $customerID,
                        'PropertyID' => 55
                    ));
                }
            }/*
           $sql = "SELECT * FROM dbo.Customers
               WHERE CustomerID = ?";
           $params = array($customerID);
           $stmt = $conn->prepare($sql);
           $stmt->execute($params);
           $exists = $stmt->fetch();
           
           */
            
        } catch (Exception $e) {

        }
    }

    /**
     * Читает текущий профиль клиента напрямую из системы лояльности (dbo.Customers +
     * dbo.CustomerPropertyValues + dbo.CustomerPhones) — для сверки с локальными
     * данными и проверки, что синхронизация профиля (updateCustomerProfile) реально
     * долетает и сохраняется на стороне роялти.
     *
     * @param int|string $customerID
     * @return array|null Возвращает null, если клиент с таким CustomerID не найден.
     */
    public function getCustomerProfile($customerID)
    {
        try {
            $conn = $this->getLoyaltyDBConnection();

            $sql = "SELECT CustomerID, FirstName, SecondName, LastName, ModifiedDate
                    FROM dbo.Customers
                    WHERE CustomerID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$customerID]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                return null;
            }

            // Обратная конвертация — симметрично записи в updateCustomerProfile()
            // (там значения переводятся в Windows-1251 перед отправкой в N-параметр).
            foreach (['FirstName', 'SecondName', 'LastName'] as $field) {
                if (isset($customer[$field]) && $customer[$field] !== null) {
                    $customer[$field] = mb_convert_encoding($customer[$field], 'UTF-8', 'Windows-1251');
                }
            }

            // Дата рождения (PropertyID = 54)
            $sql = "SELECT DateValue FROM dbo.CustomerPropertyValues WHERE CustomerID = ? AND PropertyID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$customerID, 54]);
            $birthDate = $stmt->fetchColumn();

            // Пол (PropertyID = 55; 5 = мужской, 6 = женский — см. updateCustomerProfile)
            $sql = "SELECT EnumPropertyValueID FROM dbo.CustomerPropertyValues WHERE CustomerID = ? AND PropertyID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$customerID, 55]);
            $genderEnum = $stmt->fetchColumn();
            $gender = null;
            if ($genderEnum !== false && $genderEnum !== null) {
                $gender = ((int)$genderEnum === 5) ? 'm' : (((int)$genderEnum === 6) ? 'f' : null);
            }

            // Телефон(ы), привязанные к клиенту
            $sql = "SELECT Phone FROM dbo.CustomerPhones WHERE CustomerID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$customerID]);
            $phones = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return [
                'CustomerID'   => $customer['CustomerID'],
                'FirstName'    => $customer['FirstName'],
                'SecondName'   => $customer['SecondName'],
                'LastName'     => $customer['LastName'],
                'BirthDate'    => $birthDate ?: null,
                'Gender'       => $gender,
                'Phones'       => $phones ?: [],
                'ModifiedDate' => $customer['ModifiedDate'],
            ];
        } catch (Exception $e) {
            waLog::dump($e->getMessage(), 'royalty/getCustomerProfile.log');
            return null;
        }
    }

}