<?php
class shopRatimirroyaltyPluginLocalTableDiscountCardsModel extends waModel {
    protected $table = "royalty_discount_cards";
    public function truncateTable() {
        return $this->exec("TRUNCATE {$this->table}");
    }
}