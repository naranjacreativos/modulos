<?php
function upgrade_module_2_2_4($module)
{
    Configuration::updateValue('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_RADIUS', Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_RADIUS') !== false ? Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_RADIUS') : 0);
    Configuration::updateValue('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_BORDER_WIDTH', Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_BORDER_WIDTH') !== false ? Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_BORDER_WIDTH') : 1);
    Configuration::updateValue('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_FONT_SIZE', Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_FONT_SIZE') !== false ? Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_FONT_SIZE') : 16);
    Configuration::updateValue('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_GAP', Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_GAP') !== false ? Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_GAP') : 8);
    return true;
}
