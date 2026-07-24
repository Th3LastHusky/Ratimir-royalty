<?php

class shopRatimirroyaltyPluginSmsModel extends waModel
{
    protected $table = 'royalty_sms';

    public function getLatestForContact($contact_id)
    {
        return $this->query(
            'SELECT * FROM '.$this->table.' WHERE contact_id = i:contact_id AND purpose = s:purpose ORDER BY id DESC LIMIT 1',
            array('contact_id' => (int)$contact_id, 'purpose' => 'bind_card')
        )->fetchAssoc();
    }

    public function countRecentForContact($contact_id, $seconds)
    {
        $after = date('Y-m-d H:i:s', time() - (int)$seconds);
        return (int)$this->query(
            'SELECT COUNT(*) FROM '.$this->table.' WHERE contact_id = i:contact_id AND created_at >= s:after',
            array('contact_id' => (int)$contact_id, 'after' => $after)
        )->fetchField();
    }
}
