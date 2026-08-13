<?php

function upgrade_module_2_6_10($module)
{
    $table = _DB_PREFIX_ . 'fabricssamples_coupon';
    $columns = [
        'deleted_permanently' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_sent`",
        'date_deleted' => "DATETIME NULL AFTER `deleted_permanently`",
        'deleted_by_employee' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER `date_deleted`",
    ];
    foreach ($columns as $column => $definition) {
        $exists = (bool) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='" . pSQL(_DB_NAME_)
            . "' AND table_name='" . pSQL($table) . "' AND column_name='" . pSQL($column) . "'"
        );
        if (!$exists && !Db::getInstance()->execute(
            'ALTER TABLE `' . bqSQL($table) . '` ADD COLUMN `' . bqSQL($column) . '` ' . $definition
        )) {
            return false;
        }
    }

    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'fabricssamples_coupon_suppression` ('
        . '`id_order` INT UNSIGNED NOT NULL,'
        . '`id_shop` INT UNSIGNED NOT NULL DEFAULT 0,'
        . '`id_customer` INT UNSIGNED NOT NULL DEFAULT 0,'
        . "`reason` VARCHAR(64) NOT NULL DEFAULT 'manual_delete',"
        . '`date_add` DATETIME NOT NULL,'
        . 'PRIMARY KEY (`id_order`),'
        . 'KEY `idx_suppression_shop` (`id_shop`,`date_add`),'
        . 'KEY `idx_suppression_customer` (`id_customer`,`date_add`)'
        . ') ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return Db::getInstance()->execute($sql);
}
