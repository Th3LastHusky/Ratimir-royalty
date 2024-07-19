<?php
class shopRatimirroyaltyPluginModel extends waModel {
    protected $table = "royalty_ratimir";
    public function updateCardNumber($contact_id, $card_number) {
        return $this->query("INSERT INTO {$this->table} (`card`, `contact_id`)
            VALUES ('" . $card_number . "', '" . $contact_id . "')
            ON DUPLICATE KEY UPDATE
            card = VALUES(card);
        ");
    }
    public function getCardNumber( $contact_id ) {
        return $this->getByField('contact_id', $contact_id);
    }
    public function updateAffiliate($contact_id, $affiliate) {
        return $this->query("
            UPDATE `shop_customer` SET `affiliate_bonus` = " . $affiliate ." WHERE contact_id = ". $contact_id . ";"
        );
    }
}