<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_7_0($module)
{
    foreach (['actionFrontControllerInitAfter', 'actionValidateOrderBefore'] as $hookName) {
        if ((int) Hook::getIdByName($hookName) <= 0) {
            continue;
        }
        if (!$module->isRegisteredInHook($hookName) && !$module->registerHook($hookName)) {
            return false;
        }
    }

    // Normalize existing open sample carts. cart_product is the native source of
    // truth; the module and customization quantities are aligned to it.
    $db = Db::getInstance();
    $ok = $db->execute(
        'UPDATE `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
        . ' INNER JOIN `' . _DB_PREFIX_ . 'cart_product` cp'
        . ' ON cp.id_cart=fsc.id_cart'
        . ' AND cp.id_product=fsc.id_product'
        . ' AND cp.id_product_attribute=fsc.id_product_attribute'
        . ' AND cp.id_customization=fsc.id_customization'
        . ' SET fsc.quantity=cp.quantity,fsc.date_upd=NOW()'
    );
    $ok = $db->execute(
        'UPDATE `' . _DB_PREFIX_ . 'customization` c'
        . ' INNER JOIN `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc ON fsc.id_customization=c.id_customization'
        . ' INNER JOIN `' . _DB_PREFIX_ . 'cart_product` cp'
        . ' ON cp.id_cart=fsc.id_cart'
        . ' AND cp.id_product=fsc.id_product'
        . ' AND cp.id_product_attribute=fsc.id_product_attribute'
        . ' AND cp.id_customization=fsc.id_customization'
        . ' SET c.quantity=cp.quantity,c.id_address_delivery=cp.id_address_delivery,c.in_cart=1'
    ) && $ok;

    return $ok;
}
