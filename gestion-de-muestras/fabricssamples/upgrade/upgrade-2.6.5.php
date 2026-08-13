<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_5($module)
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'fabricssamples_order';

    $hasUnique = (bool) $db->getValue(
        "SELECT COUNT(*) FROM information_schema.statistics"
        . " WHERE table_schema='" . pSQL(_DB_NAME_) . "'"
        . " AND table_name='" . pSQL($table) . "'"
        . " AND index_name='uniq_order_customization'"
    );
    if ($hasUnique && !$db->execute('ALTER TABLE `' . bqSQL($table) . '` DROP INDEX `uniq_order_customization`')) {
        return false;
    }

    $hasIndex = (bool) $db->getValue(
        "SELECT COUNT(*) FROM information_schema.statistics"
        . " WHERE table_schema='" . pSQL(_DB_NAME_) . "'"
        . " AND table_name='" . pSQL($table) . "'"
        . " AND index_name='idx_order_customization'"
    );
    if (!$hasIndex) {
        $db->execute('ALTER TABLE `' . bqSQL($table) . '` ADD INDEX `idx_order_customization` (`id_order`,`id_customization`)');
    }

    // Correct historical ownership using the native orders table.
    $db->execute(
        'UPDATE `' . bqSQL($table) . '` fso'
        . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
        . ' SET fso.id_customer=o.id_customer, fso.id_shop=o.id_shop'
    );

    foreach (['actionObjectOrderDetailAddAfter', 'actionValidateOrder', 'displayCustomerAccount', 'displayMyAccountBlock'] as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }

    try {
        if (method_exists($module, 'rebuildHistoryAndCoupons')) {
            $module->rebuildHistoryAndCoupons((int) Context::getContext()->shop->id);
        }
    } catch (Throwable $exception) {
        PrestaShopLogger::addLog('fabricssamples 2.6.5: reparación diferida: ' . $exception->getMessage(), 2);
    }

    return true;
}
