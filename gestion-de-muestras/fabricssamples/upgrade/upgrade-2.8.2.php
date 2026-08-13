<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_8_2($module)
{
    // Installation and upgrades no longer rely on lookups that may append a
    // duplicate LIMIT clause on customized MariaDB/PrestaShop installations.
    return true;
}
