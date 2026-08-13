<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_1_1($module)
{
    // Corrige instalaciones actualizadas desde 1.x/2.0.x donde la página quedó
    // desactivada y el controlador redirigía al 404 del tema.
    Configuration::updateValue('FABRICS_SAMPLES_ENABLE_CATALOG', 1);

    if ($module->isRegisteredInHook('moduleRoutes')) {
        $module->unregisterHook('moduleRoutes');
    }

    if (method_exists('Tools', 'clearAllCache')) {
        Tools::clearAllCache();
    }

    return true;
}
