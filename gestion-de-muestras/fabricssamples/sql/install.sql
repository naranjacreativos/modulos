CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_product` (
  `id_fabricssamples_product` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_product` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 0,
  `use_default_price` TINYINT(1) NOT NULL DEFAULT 1,
  `sample_price` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `size_text` VARCHAR(255) NOT NULL DEFAULT '',
  `info_text` TEXT NULL,
  `available` TINYINT(1) NOT NULL DEFAULT 1,
  `stock_mode` VARCHAR(32) NOT NULL DEFAULT 'availability',
  `sample_stock` INT NOT NULL DEFAULT 0,
  `max_per_order` INT UNSIGNED NOT NULL DEFAULT 1,
  `max_per_customer` INT UNSIGNED NOT NULL DEFAULT 0,
  `sample_weight` DECIMAL(20,6) NOT NULL DEFAULT 0.020000,
  `tax_mode` VARCHAR(16) NOT NULL DEFAULT 'inherit',
  `id_tax_rules_group` INT UNSIGNED NOT NULL DEFAULT 0,
  `internal_notes` TEXT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_product`),
  UNIQUE KEY `uniq_product_shop` (`id_product`,`id_shop`),
  KEY `idx_active_shop` (`active`,`id_shop`),
  KEY `idx_shop_active_product` (`id_shop`,`active`,`id_product`),
  KEY `idx_available_shop` (`available`,`id_shop`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_product_lang` (
  `id_fabricssamples_product` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `card_explainer_html` MEDIUMTEXT NULL,
  PRIMARY KEY (`id_fabricssamples_product`,`id_lang`),
  KEY `idx_lang` (`id_lang`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_cart` (
  `id_fabricssamples_cart` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cart` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_customization` INT UNSIGNED NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `product_reference` VARCHAR(64) NOT NULL DEFAULT '',
  `size_text` VARCHAR(255) NOT NULL DEFAULT '',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL,
  `unit_price_tax_incl` DECIMAL(20,6) NOT NULL,
  `weight` DECIMAL(20,6) NOT NULL DEFAULT 0.020000,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_cart`),
  UNIQUE KEY `uniq_cart_product_attr` (`id_cart`,`id_product`,`id_product_attribute`),
  UNIQUE KEY `uniq_customization` (`id_customization`),
  KEY `idx_cart` (`id_cart`),
  KEY `idx_product` (`id_product`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_order` (
  `id_fabricssamples_order` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_order` INT UNSIGNED NOT NULL,
  `id_order_detail` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_product` INT UNSIGNED NOT NULL,
  `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_customization` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_image` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_currency` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_lang` INT UNSIGNED NOT NULL DEFAULT 0,
  `product_name` VARCHAR(255) NOT NULL,
  `product_reference` VARCHAR(64) NOT NULL DEFAULT '',
  `size_text` VARCHAR(255) NOT NULL DEFAULT '',
  `image_snapshot` VARCHAR(255) NOT NULL DEFAULT '',
  `product_url` TEXT NULL,
  `currency_iso_code` VARCHAR(8) NOT NULL DEFAULT '',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL,
  `unit_price_tax_incl` DECIMAL(20,6) NOT NULL,
  `tax_rate` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `total_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `total_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `snapshot_json` MEDIUMTEXT NULL,
  `preparation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `notes` TEXT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_order`),
  KEY `idx_order_customization` (`id_order`,`id_customization`),
  KEY `idx_order_detail` (`id_order_detail`),
  KEY `idx_order` (`id_order`),
  KEY `idx_customer_date` (`id_customer`,`date_add`),
  KEY `idx_product_date` (`id_product`,`date_add`),
  KEY `idx_order_shop_product` (`id_shop`,`id_product`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_history_exclusion` (
  `id_order_detail` INT UNSIGNED NOT NULL,
  `id_order` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
  `reason` VARCHAR(64) NOT NULL DEFAULT 'manual_ignore',
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_order_detail`),
  KEY `idx_history_exclusion_order` (`id_order`),
  KEY `idx_history_exclusion_shop` (`id_shop`,`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_coupon` (
  `id_fabricssamples_coupon` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_order` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_cart_rule` INT UNSIGNED NOT NULL,
  `code` VARCHAR(254) NOT NULL,
  `discount_mode` VARCHAR(32) NOT NULL DEFAULT 'full',
  `discount_value` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `sample_total_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `minimum_order` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `limited_to_products` TINYINT(1) NOT NULL DEFAULT 0,
  `product_ids` TEXT NULL,
  `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `state` VARCHAR(32) NOT NULL DEFAULT 'available',
  `state_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `date_state` DATETIME NULL,
  `last_order_state` INT UNSIGNED NOT NULL DEFAULT 0,
  `deleted_permanently` TINYINT(1) NOT NULL DEFAULT 0,
  `date_deleted` DATETIME NULL,
  `deleted_by_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `reactivation_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_reactivated_at` DATETIME NULL,
  `last_reactivated_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `date_from` DATETIME NOT NULL,
  `date_to` DATETIME NOT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_coupon`),
  UNIQUE KEY `uniq_coupon_order` (`id_order`),
  UNIQUE KEY `uniq_coupon_cart_rule` (`id_cart_rule`),
  KEY `idx_coupon_customer` (`id_customer`,`date_add`),
  KEY `idx_coupon_shop` (`id_shop`,`date_add`),
  KEY `idx_coupon_state` (`state`,`date_to`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_coupon_reissue` (
  `id_fabricssamples_coupon_reissue` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_fabricssamples_coupon` BIGINT UNSIGNED NOT NULL,
  `id_order` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_original_cart_rule` INT UNSIGNED NOT NULL,
  `id_source_cart_rule` INT UNSIGNED NOT NULL,
  `id_cart_rule` INT UNSIGNED NOT NULL,
  `reissue_number` INT UNSIGNED NOT NULL,
  `original_code` VARCHAR(254) NOT NULL,
  `code` VARCHAR(254) NOT NULL,
  `state` VARCHAR(32) NOT NULL DEFAULT 'available',
  `pending_guard` BIGINT UNSIGNED NULL,
  `email_requested` TINYINT(1) NOT NULL DEFAULT 0,
  `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `date_email_sent` DATETIME NULL,
  `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `employee_name` VARCHAR(190) NOT NULL DEFAULT '',
  `date_from` DATETIME NOT NULL,
  `date_to` DATETIME NOT NULL,
  `date_used` DATETIME NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_coupon_reissue`),
  UNIQUE KEY `uniq_reissue_number` (`id_fabricssamples_coupon`,`reissue_number`),
  UNIQUE KEY `uniq_reissue_cart_rule` (`id_cart_rule`),
  UNIQUE KEY `uniq_reissue_pending` (`pending_guard`),
  KEY `idx_reissue_coupon` (`id_fabricssamples_coupon`,`date_add`),
  KEY `idx_reissue_customer` (`id_customer`,`id_shop`,`date_add`),
  KEY `idx_reissue_state` (`state`,`date_to`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_rate_limit` (
  `key_hash` CHAR(64) NOT NULL,
  `minute_bucket` INT UNSIGNED NOT NULL DEFAULT 0,
  `minute_hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `burst_bucket` INT UNSIGNED NOT NULL DEFAULT 0,
  `burst_hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`key_hash`),
  KEY `idx_rate_limit_date` (`date_upd`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_coupon_suppression` (
  `id_order` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
  `reason` VARCHAR(64) NOT NULL DEFAULT 'manual_delete',
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_order`),
  KEY `idx_suppression_shop` (`id_shop`,`date_add`),
  KEY `idx_suppression_customer` (`id_customer`,`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_stock_movement` (
  `id_fabricssamples_stock_movement` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_product` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_order_detail` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_fabricssamples_order` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `movement_type` VARCHAR(48) NOT NULL,
  `quantity_delta` INT NOT NULL,
  `quantity_before` INT NOT NULL DEFAULT 0,
  `quantity_after` INT NOT NULL DEFAULT 0,
  `movement_reference` VARCHAR(190) NOT NULL,
  `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `note` TEXT NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_stock_movement`),
  UNIQUE KEY `uniq_stock_reference` (`movement_reference`),
  KEY `idx_stock_product_shop` (`id_product`,`id_shop`,`date_add`),
  KEY `idx_stock_order` (`id_order`,`id_order_detail`),
  KEY `idx_stock_history` (`id_fabricssamples_order`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_conversion` (
  `id_fabricssamples_conversion` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sample_order` INT UNSIGNED NOT NULL,
  `id_purchase_order` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `id_fabricssamples_coupon` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_conversion`),
  UNIQUE KEY `uniq_sample_purchase_product` (`id_sample_order`,`id_purchase_order`,`id_product`),
  KEY `idx_conversion_customer` (`id_customer`,`date_add`),
  KEY `idx_conversion_product` (`id_product`,`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_limit_exception` (
  `id_fabricssamples_limit_exception` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL,
  `target_type` VARCHAR(16) NOT NULL,
  `target_id` INT UNSIGNED NOT NULL,
  `mode` VARCHAR(16) NOT NULL DEFAULT 'custom',
  `max_total_period` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_product_period` INT UNSIGNED NOT NULL DEFAULT 0,
  `period_days` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `note` TEXT NULL,
  `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_limit_exception`),
  UNIQUE KEY `uniq_limit_target` (`id_shop`,`target_type`,`target_id`),
  KEY `idx_limit_exception_active` (`id_shop`,`active`,`target_type`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_limit_reset` (
  `id_fabricssamples_limit_reset` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL,
  `reset_at` DATETIME NOT NULL,
  `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `note` TEXT NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_limit_reset`),
  KEY `idx_limit_reset_customer` (`id_shop`,`id_customer`,`reset_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_limit_event` (
  `id_fabricssamples_limit_event` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_guest` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_cart` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_product` INT UNSIGNED NOT NULL DEFAULT 0,
  `event_type` VARCHAR(32) NOT NULL,
  `limit_code` VARCHAR(32) NOT NULL DEFAULT '',
  `limit_value` INT NOT NULL DEFAULT 0,
  `observed_value` INT NOT NULL DEFAULT 0,
  `source_type` VARCHAR(16) NOT NULL DEFAULT 'default',
  `source_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `message` VARCHAR(255) NOT NULL DEFAULT '',
  `metadata_json` MEDIUMTEXT NULL,
  `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_limit_event`),
  KEY `idx_limit_event_customer` (`id_shop`,`id_customer`,`date_add`),
  KEY `idx_limit_event_product` (`id_shop`,`id_product`,`date_add`),
  KEY `idx_limit_event_type` (`event_type`,`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_audit` (
  `id_fabricssamples_audit` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `employee_name` VARCHAR(190) NOT NULL DEFAULT '',
  `action` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(48) NOT NULL DEFAULT '',
  `entity_id` VARCHAR(64) NOT NULL DEFAULT '',
  `old_value_json` MEDIUMTEXT NULL,
  `new_value_json` MEDIUMTEXT NULL,
  `note` TEXT NULL,
  `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_fabricssamples_audit`),
  KEY `idx_audit_shop_date` (`id_shop`,`date_add`),
  KEY `idx_audit_shop_id` (`id_shop`,`id_fabricssamples_audit`),
  KEY `idx_audit_employee_date` (`id_employee`,`date_add`),
  KEY `idx_audit_action_date` (`action`,`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_fabricssamples_schema_migration` (
  `id_fabricssamples_schema_migration` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration_version` VARCHAR(32) NOT NULL,
  `checksum` CHAR(64) NOT NULL DEFAULT '',
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NULL,
  `error_message` TEXT NULL,
  `details_json` MEDIUMTEXT NULL,
  PRIMARY KEY (`id_fabricssamples_schema_migration`),
  UNIQUE KEY `uniq_migration_version` (`migration_version`),
  KEY `idx_migration_status` (`status`,`started_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
