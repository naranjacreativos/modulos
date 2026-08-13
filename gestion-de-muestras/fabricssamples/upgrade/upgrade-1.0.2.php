<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_2($module)
{
    $hooks = [
        'actionProductPriceCalculation',
        'actionFrontControllerSetMedia',
        'displayProductListReviews',
    ];

    foreach ($hooks as $hookName) {
        if (!$module->isRegisteredInHook($hookName) && !$module->registerHook($hookName)) {
            return false;
        }
    }

    if (method_exists('Product', 'flushPriceCache')) {
        Product::flushPriceCache();
    }

    return true;
}
