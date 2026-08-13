<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;
use NaranjaCreativos\FabricSamples\Security\CssSanitizer;
use NaranjaCreativos\FabricSamples\Security\HtmlSanitizer;

function upgrade_module_2_14_0($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $inspector = new SchemaInspector($module->getLocalPath() . 'sql/install.sql');
    $manager = new MigrationManager($module, $inspector);

    return $manager->migrate(
        '2.14.0',
        static function () use ($module): bool {
            $configuration = new ModuleConfiguration();
            if (!$configuration->repairMissingDefaults()) {
                return false;
            }

            foreach (ModuleConfiguration::htmlKeys() as $key) {
                $values = [];
                foreach (Language::getLanguages(false) as $language) {
                    $idLang = (int) $language['id_lang'];
                    $values[$idLang] = HtmlSanitizer::sanitize($configuration->getString($key, $idLang));
                }
                if (!Configuration::updateValue(ModuleConfiguration::PREFIX . $key, $values, true)) {
                    return false;
                }
            }
            if (!Configuration::updateValue(
                ModuleConfiguration::PREFIX . 'PAGE_CUSTOM_CSS',
                CssSanitizer::sanitize($configuration->getString('PAGE_CUSTOM_CSS'))
            )) {
                return false;
            }

            $rows = Db::getInstance()->executeS(
                'SELECT id_fabricssamples_product,id_lang,card_explainer_html FROM `'
                . _DB_PREFIX_ . 'fabricssamples_product_lang`'
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                if (!Db::getInstance()->update(
                    'fabricssamples_product_lang',
                    ['card_explainer_html' => pSQL(HtmlSanitizer::sanitize((string) ($row['card_explainer_html'] ?? '')), true)],
                    'id_fabricssamples_product=' . (int) $row['id_fabricssamples_product']
                    . ' AND id_lang=' . (int) $row['id_lang']
                )) {
                    return false;
                }
            }

            foreach (['actionExportGDPRData', 'actionDeleteGDPRCustomer'] as $hookName) {
                if ((int) Hook::getIdByName($hookName) > 0
                    && !$module->isRegisteredInHook($hookName)
                    && !$module->registerHook($hookName)) {
                    return false;
                }
            }

            return true;
        },
        __FILE__
    );
}
