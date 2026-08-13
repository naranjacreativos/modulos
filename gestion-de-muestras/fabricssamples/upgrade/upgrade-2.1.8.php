<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_1_8($module)
{
    // The new asset name intentionally invalidates browser and CCC caches.
    return $module instanceof Fabricssamples;
}
