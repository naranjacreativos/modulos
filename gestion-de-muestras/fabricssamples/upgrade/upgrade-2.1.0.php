<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_1_0($module)
{
    // The former friendly route was unreliable on some themes/installations.
    // Remove it and force the new technical front controller.
    if ($module->isRegisteredInHook('moduleRoutes')) {
        $module->unregisterHook('moduleRoutes');
    }

    if (method_exists('Tools', 'clearAllCache')) {
        Tools::clearAllCache();
    }

    return true;
}
