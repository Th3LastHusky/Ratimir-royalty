<?php
class shopRatimirroyaltyPluginLocalTableIndicatorsTempModel extends waModel {
    protected $table = "royalty_indicators_temp";
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