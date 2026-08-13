<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_5_1($module)
{
    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'fabricssamples_product_lang` (
        `id_fabricssamples_product` INT UNSIGNED NOT NULL,
        `id_lang` INT UNSIGNED NOT NULL,
        `card_explainer_html` MEDIUMTEXT NULL,
        PRIMARY KEY (`id_fabricssamples_product`,`id_lang`),
        KEY `idx_lang` (`id_lang`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    if (!Db::getInstance()->execute($sql)) {
        return false;
    }

    $products = Db::getInstance()->executeS(
        'SELECT id_fabricssamples_product, info_text FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
    );
    foreach (is_array($products) ? $products : [] as $product) {
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $existing = (bool) Db::getInstance()->getValue(
                'SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_product_lang`'
                . ' WHERE id_fabricssamples_product=' . (int) $product['id_fabricssamples_product']
                . ' AND id_lang=' . $idLang
            );
            if ($existing) {
                continue;
            }
            $html = trim((string) $product['info_text']);
            if ($html === '') {
                $html = (string) Configuration::get('FABRICS_SAMPLES_CARD_EXPLAINER_HTML', $idLang);
            }
            if (!Db::getInstance()->insert('fabricssamples_product_lang', [
                'id_fabricssamples_product' => (int) $product['id_fabricssamples_product'],
                'id_lang' => $idLang,
                'card_explainer_html' => $html,
            ], false, true)) {
                return false;
            }
        }
    }

    Db::getInstance()->update('fabricssamples_product', ['info_text' => '']);
    Configuration::deleteByName('FABRICS_SAMPLES_CARD_EXPLAINER_HTML');

    return true;
}
