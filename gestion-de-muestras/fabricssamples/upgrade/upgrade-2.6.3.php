<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_3($module)
{
    if (!$module->isRegisteredInHook('actionObjectOrderDetailAddAfter')
        && !$module->registerHook('actionObjectOrderDetailAddAfter')) {
        return false;
    }

    return true;
}
