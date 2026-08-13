<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;

function upgrade_module_2_12_2($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $inspector = new SchemaInspector($module->getLocalPath() . 'sql/install.sql');
    $manager = new MigrationManager($module, $inspector);

    return $manager->migrate(
        '2.12.2',
        static function () use ($module, $inspector): bool {
            // The exclusion table is required before the historical diagnostic can
            // distinguish unresolved module rows from intentionally ignored lines.
            $repair = $inspector->repair();
            if ($repair['errors'] !== []) {
                PrestaShopLogger::addLog(
                    'fabricssamples 2.12.2: no se pudo preparar el esquema histórico: '
                    . implode(' | ', $repair['errors']),
                    3
                );
                return false;
            }

            try {
                $result = $module->diagnosticRepairOrderHistory(0);
                if (!(bool) ($result['success'] ?? false)) {
                    PrestaShopLogger::addLog(
                        sprintf(
                            'fabricssamples 2.12.2: quedan %d líneas históricas pendientes; se mostrarán en Diagnóstico.',
                            (int) ($result['remaining'] ?? 0)
                        ),
                        2
                    );
                }
            } catch (Throwable $exception) {
                PrestaShopLogger::addLog(
                    'fabricssamples 2.12.2: reconstrucción histórica diferida: ' . $exception->getMessage(),
                    2
                );
            }

            return true;
        },
        __FILE__
    );
}
