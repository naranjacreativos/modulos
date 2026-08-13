<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_15_17($module): bool
{
    unset($module);

    // The manufacturer selector was removed from the public catalog in 2.15.17.
    // Remove the obsolete multilingual configuration left by previous versions.
    Configuration::deleteByName('FABRICS_SAMPLES_FILTER_ALL_MANUFACTURERS');

    return Configuration::updateValue('FABRICS_SAMPLES_SCHEMA_VERSION', '2.15.17');
}
