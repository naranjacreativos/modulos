<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_6($module)
{
    // Version bump invalidates the front-office JavaScript asset URL.
    return true;
}
