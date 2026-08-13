<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_1($module)
{
    $defaults = [
        'FABRICS_SAMPLES_ENABLE_CATALOG' => 1,
        'FABRICS_SAMPLES_FRIENDLY_URL' => 'solicitar-muestras',
        'FABRICS_SAMPLES_PRODUCTS_PER_PAGE' => 24,
        'FABRICS_SAMPLES_DEFAULT_PRICE' => '2.50',
        'FABRICS_SAMPLES_DEFAULT_SIZE' => 'Muestra aproximada de 10 × 10 cm',
    ];

    foreach ($defaults as $key => $value) {
        $current = Configuration::get($key);
        if ($current === false || $current === null || $current === '') {
            Configuration::updateValue($key, $value, true);
        }
    }

    $ok = $module->registerHook('moduleRoutes');
    if (method_exists($module, 'clearCache')) {
        $module->clearCache();
    }

    return $ok;
}
