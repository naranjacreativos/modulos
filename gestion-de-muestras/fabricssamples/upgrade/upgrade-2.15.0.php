<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;
use NaranjaCreativos\FabricSamples\Service\ImageSnapshotService;

function upgrade_module_2_15_0($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';
    $manager = new MigrationManager(
        $module,
        new SchemaInspector($module->getLocalPath() . 'sql/install.sql')
    );

    return $manager->migrate(
        '2.15.0',
        static function () use ($module): bool {
            if (!(new ModuleConfiguration())->repairMissingDefaults()) {
                return false;
            }

            $service = new ImageSnapshotService($module);
            $directory = $service->storageDirectory();
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                return false;
            }
            @chmod($directory, 0700);
            $rows = Db::getInstance()->executeS(
                'SELECT id_fabricssamples_order,image_snapshot FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
                . " WHERE image_snapshot LIKE 'views/img/orders/%'"
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                $legacyRelative = ltrim((string) ($row['image_snapshot'] ?? ''), '/');
                $source = $module->getLocalPath() . $legacyRelative;
                if (!is_file($source)) {
                    continue;
                }
                $idRow = (int) ($row['id_fabricssamples_order'] ?? 0);
                $filename = 'fs-' . max(1, $idRow) . '-' . bin2hex(random_bytes(12)) . '.jpg';
                $destination = $directory . DIRECTORY_SEPARATOR . $filename;
                if (!@copy($source, $destination)) {
                    return false;
                }
                @chmod($destination, 0600);
                if (!Db::getInstance()->update(
                    'fabricssamples_order',
                    ['image_snapshot' => pSQL('private/orders/' . $filename), 'date_upd' => date('Y-m-d H:i:s')],
                    'id_fabricssamples_order=' . $idRow
                )) {
                    @unlink($destination);
                    return false;
                }
                @unlink($source);
            }

            return true;
        },
        __FILE__
    );
}
