<?php
class shopRatimirroyaltyPluginLocalTableCustomersModel extends waModel {
    protected $table = "royalty_customers";

    /**
     * Получение данных о клиенте по CustomerID
     *
     * @param int $customer_id Идентификатор клиента
     * @return array|null Возвращает массив данных о клиенте или null, если клиент не найден
     */
    public function getCustomerDataById($customer_id)
    {
        // SQL-запрос для получения данных о клиенте по CustomerID
        $sql = "SELECT FirstName, SecondName, LastName
                FROM {$this->table} 
                WHERE CustomerID = ? 
                AND IsDeleted = 0";  // Убедимся, что клиент не удалён

        // Выполняем запрос
        $customerData = $this->query($sql, $customer_id)->fetch();

        // Если данные о клиенте найдены, возвращаем массив данных
        if ($customerData) {
            return [
                'first_name' => $customerData['FirstName'],
                'second_name' => $customerData['SecondName'],  // Отчество
                'last_name' => $customerData['LastName']
            ];
        }

        // Если клиент не найден, возвращаем null
        return null;
    }
    public function truncateTable() {
        return $this->exec("TRUNCATE {$this->table}");
    }
}