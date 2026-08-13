<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_2($module)
{
    Configuration::updateValue('FABRICS_SAMPLES_ENABLE_CATALOG', 1);
    if (class_exists('Tools')) {
        Tools::clearAllCache();
    }
    return true;
}
