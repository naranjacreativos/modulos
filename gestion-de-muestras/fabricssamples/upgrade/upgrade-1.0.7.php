<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_7($module)
{
    // Bump the asset version so browsers and reverse proxies fetch the new JS.
    return true;
}
