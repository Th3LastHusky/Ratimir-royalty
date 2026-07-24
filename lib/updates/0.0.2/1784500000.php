<?php

$model = new waModel();
$model->exec('DROP TABLE IF EXISTS `royalty_sms`');
$model->exec(
    'CREATE TABLE `royalty_sms` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `contact_id` INT(11) NOT NULL,
        `phone` VARCHAR(25) NOT NULL,
        `code_hash` VARCHAR(255) NOT NULL,
        `purpose` VARCHAR(32) NOT NULL,
        `payload` TEXT NOT NULL,
        `attempts` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `expires_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `contact_created` (`contact_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);
