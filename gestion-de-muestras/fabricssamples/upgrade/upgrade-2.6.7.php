<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_7($module)
{
    $parentId = (int) Tab::getIdFromClassName('AdminFabricSamplesParent');
    if ($parentId <= 0) {
        $parent = new Tab();
        $parent->active = 1;
        $parent->class_name = 'AdminFabricSamplesParent';
        $parent->module = $module->name;
        $parent->id_parent = 0;
        foreach (Language::getLanguages(false) as $lang) {
            $parent->name[(int) $lang['id_lang']] = 'Muestras de tejidos';
        }
        if (!$parent->add()) {
            return false;
        }
        $parentId = (int) $parent->id;
    }

    $idTab = (int) Tab::getIdFromClassName('AdminFabricSamplesCoupons');
    $tab = $idTab > 0 ? new Tab($idTab) : new Tab();
    $tab->active = 1;
    $tab->class_name = 'AdminFabricSamplesCoupons';
    $tab->module = $module->name;
    $tab->id_parent = $parentId;
    foreach (Language::getLanguages(false) as $lang) {
        $tab->name[(int) $lang['id_lang']] = 'Cupones de muestras';
    }
    if ($idTab > 0 ? !$tab->update() : !$tab->add()) {
        return false;
    }

    return true;
}
