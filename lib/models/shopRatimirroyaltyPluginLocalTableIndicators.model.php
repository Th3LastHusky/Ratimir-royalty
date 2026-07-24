<?php
class shopRatimirroyaltyPluginLocalTableIndicatorsModel extends waModel {
    protected $table = "royalty_indicators";
    public function truncateTable() {
        return $this->exec("TRUNCATE {$this->table}");
    }
}