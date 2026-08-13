<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_15_15($module): bool
{
    if (!$module instanceof Fabricssamples) {
        return false;
    }

    foreach ($module->getRequiredHooks() as $hookName) {
        if (!$module->isRegisteredInHook($hookName) && !$module->registerHook($hookName)) {
            return false;
        }
    }

    // Optional on older cores, but required when available to validate and serialize
    // the live checkout before native order/status hooks begin firing.
    if ((int) Hook::getIdByName('actionValidateOrderBefore') > 0
        && !$module->isRegisteredInHook('actionValidateOrderBefore')
        && !$module->registerHook('actionValidateOrderBefore')) {
        return false;
    }

    return Configuration::updateValue('FABRICS_SAMPLES_SCHEMA_VERSION', '2.15.15');
}
