<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;

function upgrade_module_2_12_1($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $inspector = new SchemaInspector($module->getLocalPath() . 'sql/install.sql');
    $manager = new MigrationManager($module, $inspector);

    return $manager->migrate(
        '2.12.1',
        static function () use ($module): bool {
            $db = Db::getInstance();
            $table = _DB_PREFIX_ . 'fabricssamples_order';

            $obsolete = $db->executeS(
                'SHOW INDEX FROM `' . bqSQL($table) . "` WHERE Key_name='uniq_order_customization'"
            );
            if (is_array($obsolete) && $obsolete !== []) {
                if (!$db->execute(
                    'ALTER TABLE `' . bqSQL($table) . '` DROP INDEX `uniq_order_customization`'
                )) {
                    return false;
                }
            }

            $regular = $db->executeS(
                'SHOW INDEX FROM `' . bqSQL($table) . "` WHERE Key_name='idx_order_customization'"
            );
            if (!is_array($regular) || $regular === []) {
                if (!$db->execute(
                    'ALTER TABLE `' . bqSQL($table)
                    . '` ADD INDEX `idx_order_customization` (`id_order`,`id_customization`)'
                )) {
                    return false;
                }
            }

            try {
                $result = $module->diagnosticRepairOrderHistory(0);
                if (!(bool) ($result['success'] ?? false)) {
                    PrestaShopLogger::addLog(
                        sprintf(
                            'fabricssamples 2.12.1: quedan %d líneas de muestra sin histórico enlazado.',
                            (int) ($result['remaining'] ?? 0)
                        ),
                        2
                    );
                }
            } catch (Throwable $exception) {
                PrestaShopLogger::addLog(
                    'fabricssamples 2.12.1: reparación histórica diferida: ' . $exception->getMessage(),
                    2
                );
            }

            return true;
        },
        __FILE__
    );
}
