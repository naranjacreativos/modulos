<?php
if (!defined('_PS_VERSION_')) { exit; }
function upgrade_module_2_2_0($module)
{
    $defaults = [
        'SHOW_CARD_LABEL'=>1,'SHOW_CARD_IMAGE'=>1,'SHOW_CARD_NAME'=>1,'SHOW_CARD_REFERENCE'=>1,
        'SHOW_CARD_CATEGORY'=>1,'SHOW_CARD_MANUFACTURER'=>1,'SHOW_CARD_FORMAT'=>1,
        'SHOW_CARD_EXPLAINER'=>1,'SHOW_CARD_PRICE'=>1,'SHOW_CARD_PRODUCT_LINK'=>1,
    ];
    foreach ($defaults as $key => $value) {
        $name = 'FABRICS_SAMPLES_' . $key;
        if (Configuration::get($name) === false) {
            Configuration::updateValue($name, $value);
        }
    }
    return true;
}
