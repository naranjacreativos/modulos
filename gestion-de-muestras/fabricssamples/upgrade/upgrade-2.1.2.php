<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_1_2($module)
{
    Configuration::updateValue('FABRICS_SAMPLES_ENABLE_CATALOG', 1);
    return true;
}
