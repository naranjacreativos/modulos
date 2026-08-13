<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;

function upgrade_module_2_15_1($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $manager = new MigrationManager(
        $module,
        new SchemaInspector($module->getLocalPath() . 'sql/install.sql')
    );

    return $manager->migrate(
        '2.15.1',
        static fn (): bool => (new ModuleConfiguration())->repairMissingDefaults(),
        __FILE__
    );
}
