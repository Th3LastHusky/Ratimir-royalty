<?php
class shopRatimirroyaltyPluginLocalTableDc2dcgModel extends waModel {
    protected $table = 'royalty_discountcard2discountcardgroup';
    public function truncateTable() {
        return $this->exec("TRUNCATE {$this->table}");
    }
}