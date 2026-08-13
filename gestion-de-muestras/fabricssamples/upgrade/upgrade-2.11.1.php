<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;

function upgrade_module_2_11_1($module)
{
    require_once $module->getLocalPath() . 'config/autoload.php';

    $manager = new MigrationManager(
        $module,
        new SchemaInspector($module->getLocalPath() . 'sql/install.sql')
    );

    return $manager->migrate(
        '2.11.1',
        static function (): bool {
            $configuration = new ModuleConfiguration();
            $requiredKeys = array_values(array_unique(array_merge(
                ModuleConfiguration::catalogExampleTextKeys(),
                ModuleConfiguration::catalogCriticalKeys()
            )));

            if (!$configuration->repairMissingDefaults($requiredKeys)) {
                throw new RuntimeException('No se pudieron restaurar los valores predeterminados de la página de muestras.');
            }

            // Versions with empty multilingual fields could save every numeric design
            // control at its minimum value. Detect that exact collapsed-layout signature
            // and restore only the affected catalog presentation defaults.
            $collapsedLayout = $configuration->getInt('PRODUCTS_PER_PAGE', 24) <= 1
                && $configuration->getInt('PAGE_COLUMNS_DESKTOP', 4) <= 1
                && $configuration->getInt('PAGE_COLUMNS_TABLET', 3) <= 1
                && $configuration->getInt('FILTER_BUTTON_FONT_SIZE', 16) <= 8
                && $configuration->getInt('FILTER_BUTTON_PADDING_Y', 10) === 0
                && $configuration->getInt('ADD_BUTTON_FONT_SIZE', 16) <= 8
                && $configuration->getInt('ADD_BUTTON_PADDING_Y', 12) === 0;
            if ($collapsedLayout) {
                $defaults = ModuleConfiguration::defaults();
                foreach (ModuleConfiguration::catalogCriticalKeys() as $key) {
                    if (!array_key_exists($key, $defaults)) {
                        continue;
                    }
                    if (!Configuration::updateValue('FABRICS_SAMPLES_' . $key, $defaults[$key])) {
                        throw new RuntimeException('No se pudo restaurar la configuración visual ' . $key . '.');
                    }
                }
            }

            $rawOptions = $configuration->getString('PAGE_PER_PAGE_OPTIONS', null, '12,24,36,48,72');
            $options = array_values(array_unique(array_filter(array_map(
                'intval',
                preg_split('/[^0-9]+/', $rawOptions) ?: []
            ))));
            $options = array_values(array_filter(
                $options,
                static fn (int $value): bool => $value >= 1 && $value <= 100
            ));
            sort($options);
            if ($options === []) {
                $options = [12, 24, 36, 48, 72];
                if (!Configuration::updateValue('FABRICS_SAMPLES_PAGE_PER_PAGE_OPTIONS', implode(',', $options))) {
                    throw new RuntimeException('No se pudieron reparar las opciones de productos por página.');
                }
            }

            $defaultPerPage = $configuration->getInt('PRODUCTS_PER_PAGE', 24);
            if (!in_array($defaultPerPage, $options, true)
                && !Configuration::updateValue('FABRICS_SAMPLES_PRODUCTS_PER_PAGE', (string) $options[0])) {
                throw new RuntimeException('No se pudo normalizar la cantidad predeterminada de productos por página.');
            }

            return true;
        },
        __FILE__
    );
}
