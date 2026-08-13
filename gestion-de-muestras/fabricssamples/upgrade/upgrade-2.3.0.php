<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_3_0($module)
{
    // displayHeader was an empty compatibility hook and is no longer required.
    $module->unregisterHook('displayHeader');

    foreach ($module->getRequiredHooks() as $hookName) {
        if (!$module->registerHook($hookName)) {
            return false;
        }
    }

    // Remove assets left by the incremental 2.1.x/2.2.x development cycle.
    foreach ([
        'views/css/front-2.2.5.css',
        'views/css/admin-tabs-2.2.0.css',
        'views/js/catalog-2.1.8.js',
        'views/js/catalog-2.1.9.js',
        'views/js/admin-tabs-2.2.0.js',
    ] as $relativePath) {
        $path = $module->getLocalPath() . $relativePath;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    if (method_exists('Tools', 'clearAllCache')) {
        Tools::clearAllCache();
    }

    return true;
}
