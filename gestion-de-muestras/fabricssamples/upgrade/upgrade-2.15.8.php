<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Cart\Adapter\NativeCartAdapterFactory;
use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;
use NaranjaCreativos\FabricSamples\Repository\CartSampleRepository;
use NaranjaCreativos\FabricSamples\Service\CartIntegrityService;
use NaranjaCreativos\FabricSamples\Service\ProductAttributeResolver;

function upgrade_module_2_15_8($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $inspector = new SchemaInspector($module->getLocalPath() . 'sql/install.sql');
    $manager = new MigrationManager($module, $inspector);

    return $manager->migrate(
        '2.15.8',
        static function () use ($inspector, $module): bool {
            $repair = $inspector->repair();
            if (($repair['errors'] ?? []) !== [] || !(new ModuleConfiguration())->repairMissingDefaults()) {
                return false;
            }

            $rows = Db::getInstance()->executeS(
                'SELECT DISTINCT fsc.id_cart FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.id_product=fsc.id_product'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_cart=fsc.id_cart'
                . ' WHERE fsc.id_product_attribute=0 AND o.id_order IS NULL'
                . ' ORDER BY fsc.id_cart ASC'
            );
            $service = new CartIntegrityService(
                $module,
                new CartSampleRepository(),
                NativeCartAdapterFactory::forCurrentPlatform(),
                new ProductAttributeResolver()
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                $cart = new Cart((int) ($row['id_cart'] ?? 0));
                if (!Validate::isLoadedObject($cart) || $service->repairCart($cart, false)->hasErrors()) {
                    return false;
                }
            }

            return true;
        },
        __FILE__
    );
}
