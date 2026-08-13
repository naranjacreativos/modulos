<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\LegacyCleanupService;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;

function upgrade_module_2_11_2($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $manager = new MigrationManager(
        $module,
        new SchemaInspector($module->getLocalPath() . 'sql/install.sql')
    );

    return $manager->migrate(
        '2.11.2',
        static function () use ($module): bool {
            // The catalog is grid-only from this version. Remove every legacy
            // setting related to the former grid/list selector from all shops.
            $cleanup = new LegacyCleanupService($module);
            $cleanup->cleanup();

            return true;
        },
        __FILE__
    );
}
