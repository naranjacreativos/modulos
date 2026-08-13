<?php
function upgrade_module_2_2_1($module)
{
    Configuration::updateValue('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_BG', Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_BG') ?: '#58a178');
    Configuration::updateValue('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_TEXT_COLOR', Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_BUTTON_TEXT_COLOR') ?: '#ffffff');
    return true;
}
