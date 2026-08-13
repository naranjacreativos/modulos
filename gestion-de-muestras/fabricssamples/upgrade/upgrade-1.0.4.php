<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_4($module)
{
    // Version without Cart override to prevent conflicts with themes or other modules.
    return true;
}
