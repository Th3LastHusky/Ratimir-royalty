<?php
class shopRatimirroyaltyPluginSQL {

    /**
    * Функция для создания нового пользователя в базе royalty
    *
    * @param string $phone
    * @param string $firstName
    * @param string $middleName
    * @param string $lastName
    * @return void
    */
    public function createNewCustomer($phone, $firstName = '', $middleName = '', $lastName = '') {

        // Подключение к Microsoft SQL Server
        $serverName = "192.168.31.122";
        $connectionOptions = array(
            "Database" => "ratimir",
            "Uid" => "admin",
            "PWD" => "admin"
        );
        wa_dumpc($connectionOptions);
        // Установите соединение
        try {
            $conn = sqlsrv_connect($serverName, $connectionOptions);
            if ($conn === false) {
                wa_dumpc(sqlsrv_errors());
                die(print_r(sqlsrv_errors(), true));
            }
        } catch (Exception $e) {
            waLog::dump($e->getMessage(), 'royalty_sql.log');
        }
        $currentDateTime = date('Y-m-d H:i:s');
        // SQL запрос на вставку данных и получение CustomerID
        $sql = "INSERT INTO dbo.Customers (FirstName, SecondName, LastName, CreatedDate, ModifiedDate, ActivationDate, ActivatedTypeId) 
                VALUES (?, ?, ?, ?, ?, ?, ?);
                SELECT SCOPE_IDENTITY() AS CustomerID;";

        // Параметры для вставки
        $params = array($firstName, $middleName, $lastName, $currentDateTime, $currentDateTime, $currentDateTime, 0);

        // Выполнение запроса
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            die(wa_dumpc(sqlsrv_errors(), true));
        } else {
            // Переключение на следующий результат (результат SELECT SCOPE_IDENTITY())
            if (sqlsrv_next_result($stmt) && sqlsrv_fetch($stmt)) {
                $customerID = sqlsrv_get_field($stmt, 0);
                wa_dumpc("dbo.Customers --- Данные успешно вставлены! CustomerID: " . $customerID);
            } else {
                die(wa_dumpc(sqlsrv_errors(), true));
            }
        }
        
        $sql = "INSERT INTO dbo.Accounts (CustomerID) VALUES (?) SELECT SCOPE_IDENTITY() AS AccountID;";
        $params = array($customerID);
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            die(wa_dumpc(sqlsrv_errors(), true));
        } else {
            if (sqlsrv_next_result($stmt) && sqlsrv_fetch($stmt)) {
                $accountID = sqlsrv_get_field($stmt, 0);
                wa_dumpc("dbo.Accounts --- Данные успешно вставлены! AccountID: " . $accountID);
            } else {
                die(wa_dumpc(sqlsrv_errors(), true));
            }
        }
        $phone = preg_replace('/\D/', '', $phone);
        $sql = "INSERT INTO dbo.CustomerPhones (PropertyID, CustomerID, Phone) VALUES (?, ?, ?);";
        $params = array(57, $customerID, $phone);
        $stmt = sqlsrv_query($conn, $sql, $params);
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
    }
}