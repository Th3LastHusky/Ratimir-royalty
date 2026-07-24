<?php
class shopRatimirroyaltyPluginLocalTableCustomerPhonesModel extends waModel
{
    protected $table = "royalty_customerphones";

    /**
     * Получение CustomerID по номеру телефона
     *
     * @param string $phone Нормализованный номер телефона
     * @return int|null Возвращает CustomerID или null, если не найден
     */
    public function getCustomerIdByPhone($phone)
    {
        // SQL-запрос для получения CustomerID по номеру телефона
        $sql = "SELECT CustomerID FROM {$this->table} WHERE Phone = ?";

        // Выполняем запрос
        return $this->query($sql, $phone)->fetchField();
    }
    public function truncateTable() {
        return $this->exec("TRUNCATE {$this->table}");
    }
}