<?php
class shopRatimirroyaltyPluginLocalTableCustomerpropertyvaluesModel extends waModel {
    protected $table = "royalty_customerpropertyvalues";
    public function truncateTable() {
        return $this->exec("TRUNCATE {$this->table}");
    }
}