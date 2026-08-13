<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Diagnostic\RepairService;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;

function upgrade_module_2_12_0($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $inspector = new SchemaInspector($module->getLocalPath() . 'sql/install.sql');
    $manager = new MigrationManager($module, $inspector);

    return $manager->migrate(
        '2.12.0',
        static function () use ($module, $inspector): bool {
            $configuration = new ModuleConfiguration();
            if (!$configuration->repairMissingDefaults()) {
                return false;
            }

            $configuredStates = trim((string) Configuration::get('FABRICS_SAMPLES_LIMIT_ORDER_STATES'));
            if ($configuredStates === '') {
                $states = array_values(array_unique(array_filter(array_map('intval', [
                    Configuration::get('PS_OS_PAYMENT'),
                    Configuration::get('PS_OS_PREPARATION'),
                    Configuration::get('PS_OS_SHIPPING'),
                    Configuration::get('PS_OS_DELIVERED'),
                ]))));
                Configuration::updateValue('FABRICS_SAMPLES_LIMIT_ORDER_STATES', implode(',', $states));
            }

            $tabs = (new RepairService($module, $inspector))->execute('tabs', (int) Shop::getContextShopID());
            return (bool) $tabs['success'];
        },
        __FILE__
    );
}
