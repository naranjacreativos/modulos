<?php

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;

function upgrade_module_2_5_0($module)
{
    $newKeys = [
        'LIMITS_ENABLED',
        'MAX_CUSTOMER_TOTAL_PERIOD',
        'MAX_CUSTOMER_PRODUCT_PERIOD',
        'CUSTOMER_PERIOD_DAYS',
        'LIMIT_GUESTS',
        'LIMIT_EXEMPT_GROUPS',
        'LIMIT_ERROR_TOTAL',
        'LIMIT_ERROR_PRODUCT',
        'LIMIT_ERROR_CUSTOMER_TOTAL',
        'LIMIT_ERROR_CUSTOMER_PRODUCT',
        'LIMIT_ERROR_STOCK',
        'PAGE_AJAX_FILTERS',
        'PAGE_SHOW_CART_SUMMARY',
        'PAGE_SHOW_RESULT_COUNT',
        'PAGE_SHOW_IN_CART_STATUS',
        'PAGE_ALLOW_REMOVE',
        'PAGE_VIEW_TOGGLE',
        'PAGE_DEFAULT_VIEW',
        'PAGE_PER_PAGE_OPTIONS',
        'CART_SUMMARY_TEXT',
        'RESULT_COUNT_TEXT',
        'IN_CART_TEXT',
        'REMOVE_SAMPLE_TEXT',
        'LIMIT_REACHED_TEXT',
        'VIEW_GRID_TEXT',
        'VIEW_LIST_TEXT',
        'PER_PAGE_TEXT',
        'FILTER_ORDER_NEWEST',
        'FILTER_ORDER_POPULAR',
    ];

    $defaults = ModuleConfiguration::defaults();
    foreach ($newKeys as $key) {
        $name = ModuleConfiguration::PREFIX . $key;
        if (Configuration::get($name) === false) {
            if (!Configuration::updateValue($name, $defaults[$key] ?? '', true)) {
                return false;
            }
        }
    }

    return true;
}
