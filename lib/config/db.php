<?php
return array(
    'royalty_ratimir' => array(
        'card' => array('varchar', 13),
        'contact_id' => array('int', 11),
        ':keys' => array(
            'PRIMARY' => 'contact_id',
        ),
    ),
    'royalty_sms' => array(
        'id' => array('int', 13),
        'phone' => array('varchar', '25'),
        'code' => array('int', 4),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
);