<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_15_16($module): bool
{
    if (!$module instanceof Fabricssamples) {
        return false;
    }

    return Configuration::updateValue('FABRICS_SAMPLES_SCHEMA_VERSION', '2.15.16');
}
