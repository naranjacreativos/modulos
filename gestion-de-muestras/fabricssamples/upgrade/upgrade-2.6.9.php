<?php

function upgrade_module_2_6_9($module)
{
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
    if (!Db::getInstance()->execute($sql)) {
        return false;
    }
    if (!$module->isRegisteredInHook('actionObjectOrderDeleteAfter') && !$module->registerHook('actionObjectOrderDeleteAfter')) {
        return false;
    }
    if (method_exists($module, 'purgeOrphanOrderData')) {
        $module->purgeOrphanOrderData();
    }
    return true;
}
