<?php
class shopRatimirroyaltyPluginLocalTableAccountsModel extends waModel {
    protected $table = "royalty_accounts";
    public function truncateTable() {
        return $this->exec("TRUNCATE {$this->table}");
    }
}