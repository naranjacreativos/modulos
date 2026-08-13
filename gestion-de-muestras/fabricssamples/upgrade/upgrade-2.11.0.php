<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;

function upgrade_module_2_11_0($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $manager = new MigrationManager(
        $module,
        new SchemaInspector($module->getLocalPath() . 'sql/install.sql')
    );

    return $manager->migrate(
        '2.11.0',
        static function () use ($module): bool {
            foreach ($module->getRequiredHooks() as $hookName) {
                $hookRows = Db::getInstance()->executeS(
                    'SELECT id_hook FROM `' . _DB_PREFIX_ . 'hook` WHERE name=\'' . pSQL($hookName) . '\''
                );
                $idHook = (int) ($hookRows[0]['id_hook'] ?? 0);
                if ($idHook <= 0) {
                    continue;
                }
                $registrationRows = Db::getInstance()->executeS(
                    'SELECT id_hook_module FROM `' . _DB_PREFIX_ . 'hook_module`'
                    . ' WHERE id_module=' . (int) $module->id . ' AND id_hook=' . $idHook
                );
                if ($registrationRows === [] && !$module->registerHook($hookName)) {
                    throw new RuntimeException('No se pudo registrar el hook ' . $hookName . '.');
                }
            }

            return true;
        },
        __FILE__
    );
}
