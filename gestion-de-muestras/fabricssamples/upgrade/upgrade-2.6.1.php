<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_1($module)
{
    $db = Db::getInstance();
    $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'fabricssamples_order` MODIFY `id_order_detail` INT UNSIGNED NOT NULL DEFAULT 0');

    $uniqExists = $db->getValue(
        'SHOW INDEX FROM `' . _DB_PREFIX_ . 'fabricssamples_order` WHERE Key_name="uniq_order_detail"'
    );
    if ($uniqExists) {
        $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'fabricssamples_order` DROP INDEX `uniq_order_detail`');
    }
    if (!$db->getValue('SHOW INDEX FROM `' . _DB_PREFIX_ . 'fabricssamples_order` WHERE Key_name="idx_order_detail"')) {
        $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'fabricssamples_order` ADD KEY `idx_order_detail` (`id_order_detail`)');
    }
    if (!$db->getValue('SHOW INDEX FROM `' . _DB_PREFIX_ . 'fabricssamples_order` WHERE Key_name="uniq_order_customization"')) {
        $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'fabricssamples_order` ADD UNIQUE KEY `uniq_order_customization` (`id_order`,`id_customization`)');
    }

    foreach (['actionValidateOrder', 'actionOrderStatusPostUpdate', 'displayCustomerAccount', 'displayMyAccountBlock'] as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }

    try {
        if (method_exists($module, 'rebuildHistoryAndCoupons')) {
            $module->rebuildHistoryAndCoupons((int) Context::getContext()->shop->id);
        }
    } catch (Throwable $exception) {
        PrestaShopLogger::addLog('fabricssamples 2.6.1: reparación diferida: ' . $exception->getMessage(), 2);
    }

    return true;
}
