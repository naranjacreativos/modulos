<?php
function upgrade_module_2_2_6($module)
{
    if (method_exists('Product', 'flushPriceCache')) {
        Product::flushPriceCache();
    }
    if (method_exists('Cart', 'resetStaticCache')) {
        Cart::resetStaticCache();
    }

    return true;
}
