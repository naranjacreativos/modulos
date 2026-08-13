<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_9_0($module)
{
    Configuration::updateValue('FABRICS_SAMPLES_SCHEMA_VERSION', '2.9.0');

    foreach ($module->getRequiredHooks() as $hookName) {
        $hookRows = Db::getInstance()->executeS(
            'SELECT id_hook FROM `' . _DB_PREFIX_ . 'hook` WHERE name=\'' . pSQL($hookName) . '\''
        );
        if ((int) ($hookRows[0]['id_hook'] ?? 0) > 0 && !$module->isRegisteredInHook($hookName)) {
            $module->registerHook($hookName);
        }
    }

    $db = Db::getInstance();
    $parentRows = $db->executeS(
        'SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name=\'AdminFabricSamplesParent\''
    );
    $idParent = (int) ($parentRows[0]['id_tab'] ?? 0);
    if ($idParent <= 0) {
        $parent = new Tab();
        $parent->active = 1;
        $parent->class_name = 'AdminFabricSamplesParent';
        $parent->module = $module->name;
        $parent->id_parent = 0;
        foreach (Language::getLanguages(false) as $language) {
            $parent->name[(int) $language['id_lang']] = 'Muestras de tejidos';
        }
        if (!$parent->add()) {
            return false;
        }
        $idParent = (int) $parent->id;
    }

    $diagnosticRows = $db->executeS(
        'SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name=\'AdminFabricSamplesDiagnostics\''
    );
    $idDiagnostic = (int) ($diagnosticRows[0]['id_tab'] ?? 0);
    if ($idDiagnostic <= 0) {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminFabricSamplesDiagnostics';
        $tab->module = $module->name;
        $tab->id_parent = $idParent;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'Diagnóstico';
        }
        if (!$tab->add()) {
            return false;
        }
    } else {
        $db->update('tab', [
            'id_parent' => $idParent,
            'module' => pSQL($module->name),
            'active' => 1,
        ], 'id_tab=' . $idDiagnostic);
    }

    return true;
}
