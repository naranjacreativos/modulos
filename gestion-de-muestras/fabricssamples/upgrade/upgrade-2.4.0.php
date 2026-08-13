<?php

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;

function upgrade_module_2_4_0($module)
{
    $marginTopExisted = Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_MARGIN_TOP') !== false;
    $legacyGap = (int) Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_GAP');

    foreach (ModuleConfiguration::defaults() as $key => $value) {
        $name = ModuleConfiguration::PREFIX . $key;
        if (Configuration::get($name) === false) {
            Configuration::updateValue($name, $value, true);
        }
    }

    if (!$marginTopExisted && $legacyGap >= 0) {
        Configuration::updateValue('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_MARGIN_TOP', $legacyGap);
    }

    return true;
}
