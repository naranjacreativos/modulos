<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_15_14($module): bool
{
    if (!$module instanceof Fabricssamples) {
        return false;
    }

    foreach ($module->getRequiredHooks() as $hookName) {
        if (!$module->isRegisteredInHook($hookName) && !$module->registerHook($hookName)) {
            return false;
        }
    }

    return Configuration::updateValue('FABRICS_SAMPLES_SCHEMA_VERSION', '2.15.14');
}
