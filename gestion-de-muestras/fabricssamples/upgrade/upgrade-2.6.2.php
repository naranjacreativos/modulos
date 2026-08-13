<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_2($module)
{
    Configuration::updateValue('FABRICS_SAMPLES_COUPON_ENABLED', 1, true);
    Configuration::updateValue('FABRICS_SAMPLES_COUPON_TRIGGER', 'order', true);
    if (Configuration::get('FABRICS_SAMPLES_COUPON_VALID_DAYS') === false) {
        Configuration::updateValue('FABRICS_SAMPLES_COUPON_VALID_DAYS', 60, true);
    }

    $db = Db::getInstance();
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
    if (!$db->execute($couponSql)) {
        return false;
    }

    foreach (['actionValidateOrder', 'actionOrderStatusPostUpdate', 'displayCustomerAccount', 'displayMyAccountBlock', 'displayAdminOrderMainBottom', 'displayOrderDetail'] as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }

    $parentId = (int) Tab::getIdFromClassName('AdminFabricSamplesParent');
    if ($parentId <= 0) {
        $parent = new Tab();
        $parent->active = 1;
        $parent->class_name = 'AdminFabricSamplesParent';
        $parent->module = $module->name;
        $parent->id_parent = 0;
        foreach (Language::getLanguages(false) as $lang) {
            $parent->name[(int) $lang['id_lang']] = 'Muestras de tejidos';
        }
        if (!$parent->add()) {
            return false;
        }
        $parentId = (int) $parent->id;
    }

    $couponTabId = (int) Tab::getIdFromClassName('AdminFabricSamplesCoupons');
    if ($couponTabId <= 0) {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminFabricSamplesCoupons';
        $tab->module = $module->name;
        $tab->id_parent = $parentId;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Cupones de muestras';
        }
        if (!$tab->add()) {
            return false;
        }
    }

    try {
        if (method_exists($module, 'rebuildHistoryAndCoupons')) {
            $module->rebuildHistoryAndCoupons((int) Context::getContext()->shop->id);
        }
    } catch (Throwable $exception) {
        PrestaShopLogger::addLog('fabricssamples 2.6.2: reparación diferida: ' . $exception->getMessage(), 2);
    }

    return true;
}
