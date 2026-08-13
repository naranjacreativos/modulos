<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;

function upgrade_module_2_13_0($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $inspector = new SchemaInspector($module->getLocalPath() . 'sql/install.sql');
    $manager = new MigrationManager($module, $inspector);

    return $manager->migrate(
        '2.13.0',
        static function () use ($inspector): bool {
            $repair = $inspector->repair();
            if ($repair['errors'] !== []) {
                PrestaShopLogger::addLog(
                    'fabricssamples 2.13.0: no se pudieron crear los índices de rendimiento: '
                    . implode(' | ', $repair['errors']),
                    3
                );
                return false;
            }

            return (new ModuleConfiguration())->repairMissingDefaults();
        },
        __FILE__
    );
}
