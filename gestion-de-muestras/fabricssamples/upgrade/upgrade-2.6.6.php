<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_6($module)
{
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
        PrestaShopLogger::addLog('fabricssamples 2.6.6: reparación diferida: ' . $exception->getMessage(), 2);
    }

    return true;
}
