<?php
class shopRatimirroyaltyPluginLocalModel extends waModel {
    public function getCustomerId($phone) {
        $phone = preg_replace('/\D/', '', $phone);
        $data = $this->query("SELECT `CustomerID` FROM royalty_customerphones WHERE Phone = ". $phone)->fetchAll();
        if (!empty($data)) {
            foreach ($data as $d) {
                $customer_id = $d['CustomerID'];
            }
        } else {
            return null;
        }
        return $customer_id;
    }
    public function getAccountId($customer_id) {
        $data = $this->query('SELECT `AccountID` FROM `royalty_accounts` WHERE CustomerID = '. $customer_id)->fetchAll();
        if (!empty($data)) {
            foreach ($data as $d) {
                $account_id = $d['AccountID'];
            }
        } else {
            return null;
        }
        return $account_id;
    }
    public function getCard($account_id) {
        $data = $this->query('SELECT `Number`, `Barcode` FROM royalty_discount_cards WHERE AccountID = '. $account_id);
        foreach ($data as $d) {
            $card_number = $d['Number'];
            $barcode = $d['Barcode'];
        }
        $card['number'] = $card_number;
        $card['barcode'] = $barcode;
        return $card;
    }
    public function getIndicatorBalance($account_id) {
        $data = $this->query('SELECT `Bonus` FROM royalty_indicators WHERE AccountId = '. $account_id);
        foreach ($data as $d) {
            $balance = $d['Bonus'];
        }
        return $balance;
    }
}