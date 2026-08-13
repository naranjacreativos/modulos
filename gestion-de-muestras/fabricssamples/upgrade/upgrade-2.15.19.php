<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_15_19($module)
{
    return Configuration::updateValue('FABRICS_SAMPLES_SCHEMA_VERSION', '2.15.19');
}
