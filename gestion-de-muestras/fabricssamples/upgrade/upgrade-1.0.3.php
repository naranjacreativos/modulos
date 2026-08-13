<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_3($module)
{
    // Install the narrowly scoped Cart override introduced in 1.0.3.
    if (method_exists($module, 'installOverrides') && !$module->installOverrides()) {
        return false;
    }

    Configuration::updateValue('FABRICS_SAMPLES_KEEP_DATA', 0);

    return true;
}
