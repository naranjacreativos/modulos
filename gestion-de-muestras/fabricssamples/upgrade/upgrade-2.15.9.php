<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;

function upgrade_module_2_15_9($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $inspector = new SchemaInspector($module->getLocalPath() . 'sql/install.sql');
    $manager = new MigrationManager($module, $inspector);

    return $manager->migrate(
        '2.15.9',
        static function () use ($inspector): bool {
            $repair = $inspector->repair();

            return ($repair['errors'] ?? []) === []
                && (new ModuleConfiguration())->repairMissingDefaults();
        },
        __FILE__
    );
}
