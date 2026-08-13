<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_0($module)
{
    $columns = [
        'id_image' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_customization`',
        'id_currency' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_image`',
        'id_lang' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_currency`',
        'image_snapshot' => 'VARCHAR(255) NOT NULL DEFAULT "" AFTER `size_text`',
        'product_url' => 'TEXT NULL AFTER `image_snapshot`',
        'currency_iso_code' => 'VARCHAR(8) NOT NULL DEFAULT "" AFTER `product_url`',
        'tax_rate' => 'DECIMAL(10,6) NOT NULL DEFAULT 0.000000 AFTER `unit_price_tax_incl`',
        'total_price_tax_excl' => 'DECIMAL(20,6) NOT NULL DEFAULT 0.000000 AFTER `tax_rate`',
        'total_price_tax_incl' => 'DECIMAL(20,6) NOT NULL DEFAULT 0.000000 AFTER `total_price_tax_excl`',
        'snapshot_json' => 'MEDIUMTEXT NULL AFTER `total_price_tax_incl`',
    ];
    foreach ($columns as $column => $definition) {
        $exists = Db::getInstance()->getValue(
            'SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'fabricssamples_order` LIKE "' . pSQL($column) . '"'
        );
        if (!$exists && !Db::getInstance()->execute(
            'ALTER TABLE `' . _DB_PREFIX_ . 'fabricssamples_order` ADD COLUMN `' . bqSQL($column) . '` ' . $definition
        )) {
            return false;
        }
    }


    $couponSql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'fabricssamples_coupon` (
      `id_fabricssamples_coupon` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `id_order` INT UNSIGNED NOT NULL,
      `id_customer` INT UNSIGNED NOT NULL,
      `id_shop` INT UNSIGNED NOT NULL,
      `id_cart_rule` INT UNSIGNED NOT NULL,
      `code` VARCHAR(254) NOT NULL,
      `discount_mode` VARCHAR(32) NOT NULL DEFAULT "full",
      `discount_value` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
      `sample_total_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
      `minimum_order` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
      `limited_to_products` TINYINT(1) NOT NULL DEFAULT 0,
      `product_ids` TEXT NULL,
      `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
      `date_from` DATETIME NOT NULL,
      `date_to` DATETIME NOT NULL,
      `date_add` DATETIME NOT NULL,
      `date_upd` DATETIME NOT NULL,
      PRIMARY KEY (`id_fabricssamples_coupon`),
      UNIQUE KEY `uniq_coupon_order` (`id_order`),
      UNIQUE KEY `uniq_coupon_cart_rule` (`id_cart_rule`),
      KEY `idx_coupon_customer` (`id_customer`,`date_add`),
      KEY `idx_coupon_shop` (`id_shop`,`date_add`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    if (!Db::getInstance()->execute($couponSql)) {
        return false;
    }

    $conversionSql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'fabricssamples_conversion` (
      `id_fabricssamples_conversion` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `id_sample_order` INT UNSIGNED NOT NULL,
      `id_purchase_order` INT UNSIGNED NOT NULL,
      `id_customer` INT UNSIGNED NOT NULL,
      `id_shop` INT UNSIGNED NOT NULL,
      `id_product` INT UNSIGNED NOT NULL,
      `id_fabricssamples_coupon` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `date_add` DATETIME NOT NULL,
      PRIMARY KEY (`id_fabricssamples_conversion`),
      UNIQUE KEY `uniq_sample_purchase_product` (`id_sample_order`,`id_purchase_order`,`id_product`),
      KEY `idx_conversion_customer` (`id_customer`,`date_add`),
      KEY `idx_conversion_product` (`id_product`,`date_add`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    if (!Db::getInstance()->execute($conversionSql)) {
        return false;
    }

    Db::getInstance()->execute(
        'UPDATE `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
        . ' INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order_detail=fso.id_order_detail'
        . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
        . ' LEFT JOIN `' . _DB_PREFIX_ . 'currency` c ON c.id_currency=o.id_currency'
        . ' SET fso.id_currency=o.id_currency, fso.id_lang=o.id_lang,'
        . ' fso.currency_iso_code=COALESCE(c.iso_code, ""),'
        . ' fso.total_price_tax_excl=od.total_price_tax_excl,'
        . ' fso.total_price_tax_incl=od.total_price_tax_incl,'
        . ' fso.tax_rate=IF(od.unit_price_tax_excl>0,((od.unit_price_tax_incl/od.unit_price_tax_excl)-1)*100,0)'
    );

    $defaults = \NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration::defaults();
    foreach (array_keys($defaults) as $key) {
        if (str_starts_with($key, 'COUPON_') && Configuration::get('FABRICS_SAMPLES_' . $key) === false) {
            Configuration::updateValue('FABRICS_SAMPLES_' . $key, $defaults[$key], true);
        }
    }

    foreach (['actionOrderStatusPostUpdate', 'displayPDFInvoice', 'displayPDFDeliverySlip'] as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }

    return true;
}
