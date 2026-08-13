<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 2.6.13 only changed presentation/back-office navigation, but PrestaShop still
 * expects a callable upgrade function when this historical upgrade file exists.
 */
function upgrade_module_2_6_13($module)
{
    unset($module);

    return true;
}
