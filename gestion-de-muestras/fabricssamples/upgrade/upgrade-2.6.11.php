<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_11($module)
{
    foreach (['actionOrderStatusUpdate', 'actionOrderStatusPostUpdate', 'actionObjectOrderHistoryAddAfter'] as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }

    return true;
}
