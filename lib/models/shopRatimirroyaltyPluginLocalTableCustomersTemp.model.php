<?php
class shopRatimirroyaltyPluginLocalTableCustomersTempModel extends waModel {
    protected $table = "royalty_customers_temp";

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
    public function cloneTable()
    {
        $result = $this->query("SHOW TABLES LIKE '{$this->table}'")->fetch();
        if ($result) {
            $this->exec("DROP TABLE IF EXISTS `{$this->table}`");
        }
        $ogTable = str_replace('_temp', '', $this->table);
        
        $sql = "SHOW CREATE TABLE `". $ogTable ."`";
        $result = $this->query($sql)->fetch();
        if (!$result) {
            
            throw new waException('Не удалось получить структуру таблицы {$ogTable}');
        }
        $createTableSql = str_replace('CREATE TABLE `'.$ogTable.'`', 'CREATE TABLE `'.$this->table.'`', $result['Create Table']);
        $this->exec($createTableSql);
    }
    public function renameTable() {
        $ogTable = str_replace('_temp', '', $this->table);
        $result = $this->query("SHOW TABLES LIKE '{$ogTable}'")->fetch();
        if ($result) {
            $this->exec("DROP TABLE IF EXISTS `{$ogTable}`");
        }
        $this->exec("RENAME TABLE {$this->table} TO {$ogTable}");
    }
}