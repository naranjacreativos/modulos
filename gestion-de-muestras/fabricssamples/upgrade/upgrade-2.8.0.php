<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_8_0($module)
{
    $db = Db::getInstance();
    $ok = true;

    $ok = $db->execute(
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'fabricssamples_stock_movement` ('
        . '`id_fabricssamples_stock_movement` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . '`id_product` INT UNSIGNED NOT NULL,'
        . '`id_shop` INT UNSIGNED NOT NULL,'
        . '`id_order` INT UNSIGNED NOT NULL DEFAULT 0,'
        . '`id_order_detail` INT UNSIGNED NOT NULL DEFAULT 0,'
        . '`id_fabricssamples_order` BIGINT UNSIGNED NOT NULL DEFAULT 0,'
        . '`movement_type` VARCHAR(48) NOT NULL,'
        . '`quantity_delta` INT NOT NULL,'
        . '`quantity_before` INT NOT NULL DEFAULT 0,'
        . '`quantity_after` INT NOT NULL DEFAULT 0,'
        . '`movement_reference` VARCHAR(190) NOT NULL,'
        . '`id_employee` INT UNSIGNED NOT NULL DEFAULT 0,'
        . '`note` TEXT NULL,'
        . '`date_add` DATETIME NOT NULL,'
        . 'PRIMARY KEY (`id_fabricssamples_stock_movement`),'
        . 'UNIQUE KEY `uniq_stock_reference` (`movement_reference`),'
        . 'KEY `idx_stock_product_shop` (`id_product`,`id_shop`,`date_add`),'
        . 'KEY `idx_stock_order` (`id_order`,`id_order_detail`),'
        . 'KEY `idx_stock_history` (`id_fabricssamples_order`)'
        . ') ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    ) && $ok;

    $columns = [
        'state' => "VARCHAR(32) NOT NULL DEFAULT 'available'",
        'state_reason' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'date_state' => 'DATETIME NULL',
        'last_order_state' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    ];
    foreach ($columns as $column => $definition) {
        $columnRows = $db->executeS(
            "SHOW COLUMNS FROM `" . _DB_PREFIX_ . "fabricssamples_coupon` LIKE '" . pSQL($column) . "'"
        );
        $exists = is_array($columnRows) && $columnRows !== [];
        if (!$exists) {
            $ok = $db->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'fabricssamples_coupon` ADD `' . bqSQL($column) . '` ' . $definition
            ) && $ok;
        }
    }

    $indexRows = $db->executeS(
        "SHOW INDEX FROM `" . _DB_PREFIX_ . "fabricssamples_coupon` WHERE Key_name='idx_coupon_state'"
    );
    $indexExists = is_array($indexRows) && $indexRows !== [];
    if (!$indexExists) {
        $ok = $db->execute(
            'ALTER TABLE `' . _DB_PREFIX_ . 'fabricssamples_coupon` ADD KEY `idx_coupon_state` (`state`,`date_to`)'
        ) && $ok;
    }

    // Existing versions already reduced stock when the immutable history was inserted.
    // Baseline movements record that fact without changing the current stock again.
    $legacyRows = $db->executeS(
        'SELECT fso.*,fsp.sample_stock current_sample_stock,fsp.stock_mode current_stock_mode'
        . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
        . ' INNER JOIN `' . _DB_PREFIX_ . 'fabricssamples_product` fsp'
        . ' ON fsp.id_product=fso.id_product AND fsp.id_shop=fso.id_shop'
    );
    foreach (is_array($legacyRows) ? $legacyRows : [] as $legacyRow) {
        $snapshot = json_decode((string) ($legacyRow['snapshot_json'] ?? ''), true);
        $snapshotMode = is_array($snapshot)
            ? (string) ($snapshot['sample_configuration']['stock_mode'] ?? '')
            : '';
        $wasIndependent = $snapshotMode !== ''
            ? $snapshotMode === 'independent'
            : (string) ($legacyRow['current_stock_mode'] ?? '') === 'independent';
        if (!$wasIndependent) {
            continue;
        }
        $quantity = max(0, (int) ($legacyRow['quantity'] ?? 0));
        if ($quantity <= 0) {
            continue;
        }
        $ok = $db->insert('fabricssamples_stock_movement', [
            'id_product' => (int) $legacyRow['id_product'],
            'id_shop' => (int) $legacyRow['id_shop'],
            'id_order' => (int) $legacyRow['id_order'],
            'id_order_detail' => (int) ($legacyRow['id_order_detail'] ?? 0),
            'id_fabricssamples_order' => (int) $legacyRow['id_fabricssamples_order'],
            'movement_type' => 'legacy_consumption',
            'quantity_delta' => -$quantity,
            'quantity_before' => (int) ($legacyRow['current_sample_stock'] ?? 0),
            'quantity_after' => (int) ($legacyRow['current_sample_stock'] ?? 0),
            'movement_reference' => 'legacy:' . (int) $legacyRow['id_fabricssamples_order'],
            'id_employee' => 0,
            'note' => 'Migrated from pre-2.8 stock accounting',
            'date_add' => date('Y-m-d H:i:s'),
        ], true, true, Db::INSERT_IGNORE) && $ok;
    }

    $ok = $db->execute(
        'UPDATE `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
        . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.id_cart_rule=fsc.id_cart_rule'
        . ' SET fsc.state=CASE'
        . ' WHEN fsc.deleted_permanently=1 THEN \'deleted_permanently\''
        . ' WHEN EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr WHERE ocr.id_cart_rule=fsc.id_cart_rule) THEN \'used\''
        . ' WHEN fsc.date_to<NOW() THEN \'expired\''
        . ' WHEN COALESCE(cr.active,0)=1 THEN \'available\''
        . ' ELSE \'inactive\' END,'
        . " fsc.state_reason='migration_2_8_0',fsc.date_state=NOW(),fsc.date_upd=NOW()"
    ) && $ok;

    Configuration::updateValue('FABRICS_SAMPLES_LOW_STOCK_THRESHOLD', (int) (Configuration::get('FABRICS_SAMPLES_LOW_STOCK_THRESHOLD') ?: 5));

    foreach (['actionProductCancel', 'actionOrderSlipAdd'] as $hookName) {
        if ((int) Hook::getIdByName($hookName) > 0 && !$module->isRegisteredInHook($hookName)) {
            $ok = $module->registerHook($hookName) && $ok;
        }
    }

    return $ok;
}
