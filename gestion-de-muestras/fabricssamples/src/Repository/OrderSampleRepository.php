<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class OrderSampleRepository
{
    /** @return list<array<string, mixed>> */
    public function findByOrder(int $idOrder): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
            . ' WHERE id_order=' . $idOrder
            . ' ORDER BY id_fabricssamples_order'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns the best available sample rows for business rules such as coupon generation.
     * Historical rows are preferred, but native order_detail rows are always used as a fallback.
     *
     * @return list<array<string,mixed>>
     */
    public function findForCouponByOrder(int $idOrder): array
    {
        return $this->mergeRows($this->findNativeByOrder($idOrder), $this->findByOrder($idOrder));
    }

    /** @return array<string,mixed> */
    public function findByOrderCustomization(int $idOrder, int $idCustomization): array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
            . ' WHERE id_order=' . $idOrder . ' AND id_customization=' . $idCustomization
        );

        return is_array($row) ? $row : [];
    }

    /** @return list<array<string, mixed>> */
    public function findByCustomer(int $idCustomer, int $idShop): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT fso.*, o.reference order_reference,'
            . ' EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_conversion` fscv'
            . ' WHERE fscv.id_sample_order=fso.id_order AND fscv.id_product=fso.id_product) AS converted'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
            . ' WHERE o.id_customer=' . $idCustomer . ' AND o.id_shop=' . $idShop
            . ' ORDER BY fso.date_add DESC, fso.id_fabricssamples_order DESC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Rows for the customer account. Native order details are merged so the account remains
     * correct even when a historical insert failed or an older module version did not create it.
     *
     * @return list<array<string,mixed>>
     */
    public function findDisplayByCustomer(int $idCustomer, int $idShop): array
    {
        $rows = $this->mergeRows(
            $this->findNativeByCustomer($idCustomer, $idShop),
            $this->findByCustomer($idCustomer, $idShop)
        );
        usort($rows, static function (array $a, array $b): int {
            $dateComparison = strcmp((string) ($b['date_add'] ?? ''), (string) ($a['date_add'] ?? ''));
            if ($dateComparison !== 0) {
                return $dateComparison;
            }
            return (int) ($b['id_order_detail'] ?? 0) <=> (int) ($a['id_order_detail'] ?? 0);
        });

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public function findNativeByCustomer(int $idCustomer, int $idShop): array
    {
        if ($idCustomer <= 0 || $idShop <= 0) {
            return [];
        }

        $rows = \Db::getInstance()->executeS(
            $this->nativeSelect()
            . ' WHERE o.id_customer=' . $idCustomer . ' AND o.id_shop=' . $idShop
            . ' AND ' . $this->nativeSampleCondition()
            . ' ORDER BY o.date_add DESC, od.id_order_detail DESC'
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string,mixed>> */
    public function findNativeByOrder(int $idOrder): array
    {
        if ($idOrder <= 0) {
            return [];
        }

        $rows = \Db::getInstance()->executeS(
            $this->nativeSelect()
            . ' WHERE o.id_order=' . $idOrder
            . ' AND ' . $this->nativeSampleCondition()
            . ' ORDER BY od.id_order_detail ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<int> */
    public function orderIdsByCustomer(int $idCustomer, int $idShop): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT DISTINCT o.id_order FROM `' . _DB_PREFIX_ . 'orders` o'
            . ' WHERE o.id_customer=' . $idCustomer . ' AND o.id_shop=' . $idShop
            . ' AND ('
            . ' EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso WHERE fso.id_order=o.id_order)'
            . ' OR EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'order_detail` od'
            . ' WHERE od.id_order=o.id_order AND ' . $this->nativeSampleCondition('o', 'od') . ')'
            . ') ORDER BY o.id_order DESC'
        );

        return array_values(array_filter(array_map('intval', array_column(is_array($rows) ? $rows : [], 'id_order'))));
    }

    /** @return array<string,mixed> */
    public function findByOrderDetail(int $idOrderDetail): array
    {
        if ($idOrderDetail <= 0) {
            return [];
        }
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
            . ' WHERE id_order_detail=' . $idOrderDetail
        );

        return is_array($row) ? $row : [];
    }

    /**
     * Finds an old historical row that belongs to the native order detail but was
     * created before id_order_detail was stored reliably.
     *
     * @param array<string,mixed> $detail
     * @return array<string,mixed>
     */
    public function findLegacyCandidateForOrderDetail(int $idOrder, array $detail): array
    {
        if ($idOrder <= 0) {
            return [];
        }

        $idCustomization = (int) ($detail['id_customization'] ?? 0);
        $idProduct = (int) ($detail['product_id'] ?? 0);
        $idAttribute = (int) ($detail['product_attribute_id'] ?? 0);
        $unitPrice = (float) ($detail['unit_price_tax_incl'] ?? 0);

        $conditions = [];
        if ($idCustomization > 0) {
            $conditions[] = 'id_customization=' . $idCustomization;
        }
        if ($idProduct > 0) {
            $conditions[] = '(id_product=' . $idProduct
                . ' AND id_product_attribute=' . $idAttribute . ')';
        }
        if ($conditions === []) {
            return [];
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
            . ' WHERE id_order=' . $idOrder
            . ' AND id_order_detail=0'
            . ' AND (' . implode(' OR ', $conditions) . ')'
            . ' ORDER BY '
            . ($idCustomization > 0 ? '(id_customization=' . $idCustomization . ') DESC,' : '')
            . ' ABS(unit_price_tax_incl-' . $unitPrice . ') ASC,'
            . ' id_fabricssamples_order ASC'
        );

        return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];
    }

    public function countMissingNativeHistory(int $idShop = 0): int
    {
        $shopFilter = $idShop > 0 ? ' AND o.id_shop=' . $idShop : '';
        $exclusionJoin = '';
        $exclusionFilter = '';
        if ($this->historyExclusionTableExists()) {
            $exclusionJoin = ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_history_exclusion` fshe'
                . ' ON fshe.id_order_detail=od.id_order_detail';
            $exclusionFilter = ' AND fshe.id_order_detail IS NULL';
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_detail` od'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=od.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' ON fso.id_order_detail=od.id_order_detail'
            . $exclusionJoin
            . " WHERE LOWER(TRIM(od.product_name)) LIKE 'muestra%'"
            . ' AND fso.id_fabricssamples_order IS NULL'
            . $exclusionFilter
            . $shopFilter
        );
    }

    public function countIgnoredNativeHistory(int $idShop = 0): int
    {
        if (!$this->historyExclusionTableExists()) {
            return 0;
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'fabricssamples_history_exclusion` fshe'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order_detail=fshe.id_order_detail'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=od.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_order` fso ON fso.id_order_detail=od.id_order_detail'
            . ' WHERE fso.id_fabricssamples_order IS NULL'
            . ($idShop > 0 ? ' AND o.id_shop=' . $idShop : '')
        );
    }

    /** @return list<array<string,mixed>> */
    public function findMissingNativeHistory(int $idShop = 0, int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));
        $exclusionJoin = '';
        $exclusionFilter = '';
        if ($this->historyExclusionTableExists()) {
            $exclusionJoin = ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_history_exclusion` fshe'
                . ' ON fshe.id_order_detail=od.id_order_detail';
            $exclusionFilter = ' AND fshe.id_order_detail IS NULL';
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT od.id_order_detail,od.id_order,od.product_id,od.product_attribute_id,od.id_customization,'
            . ' od.product_name,od.product_reference,od.product_quantity,od.unit_price_tax_incl,'
            . ' o.reference AS order_reference,o.id_shop,o.id_customer,o.date_add,'
            . ' EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
            . ' WHERE fsc.id_cart=o.id_cart AND ('
            . ' (od.id_customization>0 AND fsc.id_customization=od.id_customization)'
            . ' OR (fsc.id_product=od.product_id AND fsc.id_product_attribute=od.product_attribute_id'
            . ' AND ABS(fsc.unit_price_tax_incl-od.unit_price_tax_incl)<=0.05))) AS has_cart_match,'
            . ' EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_order` legacy'
            . ' WHERE legacy.id_order=od.id_order AND legacy.id_order_detail=0 AND ('
            . ' (od.id_customization>0 AND legacy.id_customization=od.id_customization)'
            . ' OR (legacy.id_product=od.product_id AND legacy.id_product_attribute=od.product_attribute_id))) AS has_legacy_history'
            . ' FROM `' . _DB_PREFIX_ . 'order_detail` od'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=od.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_order` fso ON fso.id_order_detail=od.id_order_detail'
            . $exclusionJoin
            . " WHERE LOWER(TRIM(od.product_name)) LIKE 'muestra%'"
            . ' AND fso.id_fabricssamples_order IS NULL'
            . $exclusionFilter
            . ($idShop > 0 ? ' AND o.id_shop=' . $idShop : '')
            . ' ORDER BY o.id_order DESC,od.id_order_detail ASC LIMIT ' . $limit
        );

        if (!is_array($rows)) {
            return [];
        }
        foreach ($rows as &$row) {
            $hasCart = !empty($row['has_cart_match']);
            $hasLegacy = !empty($row['has_legacy_history']);
            $name = trim((string) ($row['product_name'] ?? ''));
            $canonical = (bool) preg_match('/^muestra\s*[-–—:]/iu', $name);
            $row['classification'] = $hasLegacy
                ? 'Histórico antiguo sin enlace'
                : ($hasCart ? 'Coincidencia con carrito de muestras' : ($canonical ? 'Nombre de muestra sin datos auxiliares' : 'Posible falso positivo'));
            $row['repairable'] = $hasLegacy || $hasCart || $canonical;
        }
        unset($row);

        return $rows;
    }

    /** @return array{ignored:int,error:string} */
    public function ignoreMissingNativeHistory(int $idShop, int $idEmployee): array
    {
        if (!$this->historyExclusionTableExists()) {
            return ['ignored' => 0, 'error' => 'No existe la tabla de exclusiones históricas. Ejecute Reparar esquema.'];
        }

        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'fabricssamples_history_exclusion`'
            . ' (`id_order_detail`,`id_order`,`id_shop`,`reason`,`note`,`id_employee`,`date_add`)'
            . ' SELECT od.id_order_detail,od.id_order,o.id_shop,\'manual_ignore\','
            . '\'Descartado desde Diagnóstico; no se modifica el pedido nativo.\',' . max(0, $idEmployee) . ',\'' . pSQL($now) . '\''
            . ' FROM `' . _DB_PREFIX_ . 'order_detail` od'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=od.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_order` fso ON fso.id_order_detail=od.id_order_detail'
            . " WHERE LOWER(TRIM(od.product_name)) LIKE 'muestra%'"
            . ' AND fso.id_fabricssamples_order IS NULL'
            . ($idShop > 0 ? ' AND o.id_shop=' . $idShop : '')
            . ' ON DUPLICATE KEY UPDATE id_order=VALUES(id_order),id_shop=VALUES(id_shop),'
            . ' reason=VALUES(reason),note=VALUES(note),id_employee=VALUES(id_employee),date_add=VALUES(date_add)';
        $before = $this->countIgnoredNativeHistory($idShop);
        if (!\Db::getInstance()->execute($sql)) {
            return ['ignored' => 0, 'error' => \Db::getInstance()->getMsgError()];
        }

        return ['ignored' => max(0, $this->countIgnoredNativeHistory($idShop) - $before), 'error' => ''];
    }

    public function clearHistoryExclusions(int $idShop = 0): int
    {
        if (!$this->historyExclusionTableExists()) {
            return 0;
        }
        $before = $this->countIgnoredNativeHistory($idShop);
        if ($idShop > 0) {
            \Db::getInstance()->execute(
                'DELETE fshe FROM `' . _DB_PREFIX_ . 'fabricssamples_history_exclusion` fshe'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fshe.id_order'
                . ' WHERE o.id_shop=' . $idShop
            );
        } else {
            \Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'fabricssamples_history_exclusion`');
        }

        return $before;
    }

    /** @return list<string> */
    public function dropObsoleteUniqueHistoryIndexes(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SHOW INDEX FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
        );
        if (!is_array($rows)) {
            return [];
        }
        $obsolete = [];
        foreach ($rows as $row) {
            $name = (string) ($row['Key_name'] ?? '');
            if ($name !== '' && $name !== 'PRIMARY' && (int) ($row['Non_unique'] ?? 1) === 0) {
                $obsolete[$name] = true;
            }
        }
        $dropped = [];
        foreach (array_keys($obsolete) as $name) {
            if (\Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'fabricssamples_order` DROP INDEX `' . bqSQL($name) . '`'
            )) {
                $dropped[] = $name;
            }
        }

        return $dropped;
    }

    public function updateLegacyFromNativeDetail(int $idHistory, int $idOrderDetail): bool
    {
        if ($idHistory <= 0 || $idOrderDetail <= 0) {
            return false;
        }
        return (bool) \Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order_detail=' . $idOrderDetail
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=od.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'currency` cur ON cur.id_currency=o.id_currency'
            . ' SET fso.id_order=od.id_order,fso.id_order_detail=od.id_order_detail,fso.id_shop=o.id_shop,'
            . ' fso.id_customer=o.id_customer,fso.id_product=od.product_id,'
            . ' fso.id_product_attribute=od.product_attribute_id,fso.id_customization=od.id_customization,'
            . ' fso.id_currency=o.id_currency,fso.id_lang=o.id_lang,'
            . ' fso.product_name=LEFT(od.product_name,255),'
            . ' fso.product_reference=LEFT(COALESCE(od.product_reference,\'\'),64),'
            . ' fso.currency_iso_code=LEFT(COALESCE(cur.iso_code,\'\'),8),'
            . ' fso.quantity=GREATEST(1,COALESCE(od.product_quantity,1)),'
            . ' fso.unit_price_tax_excl=COALESCE(od.unit_price_tax_excl,0),'
            . ' fso.unit_price_tax_incl=COALESCE(od.unit_price_tax_incl,0),'
            . ' fso.tax_rate=CASE WHEN COALESCE(od.unit_price_tax_excl,0)>0'
            . ' THEN ((od.unit_price_tax_incl/od.unit_price_tax_excl)-1)*100 ELSE 0 END,'
            . ' fso.total_price_tax_excl=COALESCE(od.total_price_tax_excl,0),'
            . ' fso.total_price_tax_incl=COALESCE(od.total_price_tax_incl,0),fso.date_upd=NOW()'
            . ' WHERE fso.id_fabricssamples_order=' . $idHistory
        );
    }

    public function insertMinimalFromNativeDetail(int $idOrderDetail): bool
    {
        if ($idOrderDetail <= 0) {
            return false;
        }
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'fabricssamples_order`'
            . ' (`id_order`,`id_order_detail`,`id_shop`,`id_customer`,`id_product`,`id_product_attribute`,'
            . '`id_customization`,`id_image`,`id_currency`,`id_lang`,`product_name`,`product_reference`,'
            . '`size_text`,`image_snapshot`,`product_url`,`currency_iso_code`,`quantity`,`unit_price_tax_excl`,'
            . '`unit_price_tax_incl`,`tax_rate`,`total_price_tax_excl`,`total_price_tax_incl`,`snapshot_json`,'
            . '`preparation_status`,`notes`,`date_add`,`date_upd`)'
            . ' SELECT od.id_order,od.id_order_detail,o.id_shop,o.id_customer,od.product_id,'
            . ' od.product_attribute_id,od.id_customization,0,o.id_currency,o.id_lang,'
            . ' LEFT(od.product_name,255),LEFT(COALESCE(od.product_reference,\'\'),64),\'\',\'\',\'\','
            . ' LEFT(COALESCE(cur.iso_code,\'\'),8),GREATEST(1,COALESCE(od.product_quantity,1)),'
            . ' COALESCE(od.unit_price_tax_excl,0),COALESCE(od.unit_price_tax_incl,0),'
            . ' CASE WHEN COALESCE(od.unit_price_tax_excl,0)>0'
            . ' THEN ((od.unit_price_tax_incl/od.unit_price_tax_excl)-1)*100 ELSE 0 END,'
            . ' COALESCE(od.total_price_tax_excl,0),COALESCE(od.total_price_tax_incl,0),'
            . ' \'{"recovered_directly":true}\',\'pending\',\'\',COALESCE(o.date_add,NOW()),NOW()'
            . ' FROM `' . _DB_PREFIX_ . 'order_detail` od'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=od.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'currency` cur ON cur.id_currency=o.id_currency'
            . ' WHERE od.id_order_detail=' . $idOrderDetail
            . ' AND NOT EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' WHERE fso.id_order_detail=od.id_order_detail)';

        return (bool) \Db::getInstance()->execute($sql);
    }

    private function historyExclusionTableExists(): bool
    {
        $table = _DB_PREFIX_ . 'fabricssamples_history_exclusion';
        $pattern = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $table);
        $rows = \Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($pattern) . "'");

        return is_array($rows) && $rows !== [];
    }

    public function existsByOrderDetail(int $idOrderDetail): bool
    {
        return $this->findByOrderDetail($idOrderDetail) !== [];
    }

    public function existsByOrderCustomization(int $idOrder, int $idCustomization): bool
    {
        return $this->findByOrderCustomization($idOrder, $idCustomization) !== [];
    }

    /** @return array{sold:int,revenue:float,orders:int} */
    public function aggregateDashboardMetrics(int $idShop): array
    {
        if ($idShop <= 0) {
            return ['sold' => 0, 'revenue' => 0.0, 'orders' => 0];
        }

        $exclusionJoin = '';
        $exclusionFilter = '';
        if ($this->historyExclusionTableExists()) {
            $exclusionJoin = ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_history_exclusion` fshe'
                . ' ON fshe.id_order_detail=od.id_order_detail';
            $exclusionFilter = ' AND fshe.id_order_detail IS NULL';
        }

        // Prefer native order_detail rows whenever they can be recognized as samples.
        // This makes the dashboard resilient when an auxiliary fabricssamples_order insert
        // failed during checkout or when an older module version did not persist history.
        $nativeRows = 'SELECT o.id_order,'
            . ' GREATEST(0,COALESCE(od.product_quantity,0)) AS quantity,'
            . ' COALESCE(od.total_price_tax_incl,'
            . ' COALESCE(od.unit_price_tax_incl,0)*GREATEST(0,COALESCE(od.product_quantity,0)),0) AS total_price_tax_incl'
            . ' FROM `' . _DB_PREFIX_ . 'orders` o'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order=o.id_order'
            . $exclusionJoin
            . ' WHERE o.id_shop=' . $idShop
            . ' AND ' . $this->nativeSampleCondition('o', 'od')
            . $exclusionFilter;

        // Keep historical rows only when no equivalent native sample detail can be found.
        // This preserves legacy data without double-counting orders that have both sources.
        $historyRows = 'SELECT fso.id_order,'
            . ' GREATEST(0,COALESCE(fso.quantity,0)) AS quantity,'
            . ' COALESCE(fso.total_price_tax_incl,'
            . ' COALESCE(fso.unit_price_tax_incl,0)*GREATEST(0,COALESCE(fso.quantity,0)),0) AS total_price_tax_incl'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` oh ON oh.id_order=fso.id_order'
            . ' WHERE oh.id_shop=' . $idShop
            . ' AND NOT EXISTS('
            . ' SELECT 1 FROM `' . _DB_PREFIX_ . 'order_detail` od2'
            . ' WHERE od2.id_order=fso.id_order'
            . ' AND ' . $this->nativeSampleCondition('oh', 'od2')
            . ' AND ('
            . ' (fso.id_order_detail>0 AND od2.id_order_detail=fso.id_order_detail)'
            . ' OR (fso.id_order_detail=0 AND ('
            . ' (fso.id_customization>0 AND od2.id_customization=fso.id_customization)'
            . ' OR (fso.id_product=od2.product_id'
            . ' AND fso.id_product_attribute=od2.product_attribute_id'
            . ' AND ABS(fso.unit_price_tax_incl-od2.unit_price_tax_incl)<=0.05)'
            . ' ))'
            . ' ))';

        $row = \Db::getInstance()->getRow(
            'SELECT COALESCE(SUM(metrics.quantity),0) AS sold,'
            . ' COALESCE(SUM(metrics.total_price_tax_incl),0) AS revenue,'
            . ' COUNT(DISTINCT metrics.id_order) AS orders'
            . ' FROM (' . $nativeRows . ' UNION ALL ' . $historyRows . ') metrics'
        );

        return [
            'sold' => max(0, (int) ($row['sold'] ?? 0)),
            'revenue' => max(0.0, (float) ($row['revenue'] ?? 0.0)),
            'orders' => max(0, (int) ($row['orders'] ?? 0)),
        ];
    }

    public function countByOrder(int $idOrder): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'fabricssamples_order` WHERE id_order=' . $idOrder
        );
    }

    public function insert(array $data): bool
    {
        return \Db::getInstance()->insert('fabricssamples_order', $data, true);
    }

    public function updateById(int $idRow, array $data): bool
    {
        if ($idRow <= 0 || $data === []) {
            return false;
        }

        return \Db::getInstance()->update(
            'fabricssamples_order',
            $data,
            'id_fabricssamples_order=' . $idRow
        );
    }

    public function deleteByOrder(int $idOrder): bool
    {
        if ($idOrder <= 0) {
            return false;
        }
        return \Db::getInstance()->delete('fabricssamples_order', 'id_order=' . $idOrder);
    }

    public function purgeOrphans(): bool
    {
        return \Db::getInstance()->execute(
            'DELETE fso FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
            . ' WHERE o.id_order IS NULL'
        );
    }

    private function nativeSelect(): string
    {
        return 'SELECT 0 AS id_fabricssamples_order, o.id_order, od.id_order_detail, o.id_shop, o.id_customer,'
            . ' od.product_id AS id_product, od.product_attribute_id AS id_product_attribute,'
            . ' od.id_customization, 0 AS id_image, o.id_currency, o.id_lang,'
            . ' od.product_name, od.product_reference, COALESCE(('
            . ' SELECT fsc.size_text FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
            . ' WHERE fsc.id_cart=o.id_cart AND ('
            . ' (od.id_customization>0 AND fsc.id_customization=od.id_customization)'
            . ' OR (fsc.id_product=od.product_id AND fsc.id_product_attribute=od.product_attribute_id'
            . ' AND ABS(fsc.unit_price_tax_incl-od.unit_price_tax_incl)<=0.05))'
            . ' ORDER BY (fsc.id_customization=od.id_customization) DESC LIMIT 1), \'\') AS size_text,'
            . ' \'\' AS image_snapshot, \'\' AS product_url, COALESCE(cur.iso_code, \'\') AS currency_iso_code,'
            . ' od.product_quantity AS quantity, od.unit_price_tax_excl, od.unit_price_tax_incl,'
            . ' CASE WHEN od.unit_price_tax_excl>0 THEN ((od.unit_price_tax_incl/od.unit_price_tax_excl)-1)*100 ELSE 0 END AS tax_rate,'
            . ' od.total_price_tax_excl, od.total_price_tax_incl, \'{}\' AS snapshot_json,'
            . ' \'pending\' AS preparation_status, \'\' AS notes, o.date_add, o.date_upd, o.reference AS order_reference,'
            . ' EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_conversion` fscv'
            . ' WHERE fscv.id_sample_order=o.id_order AND fscv.id_product=od.product_id) AS converted'
            . ' FROM `' . _DB_PREFIX_ . 'orders` o'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order=o.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'currency` cur ON cur.id_currency=o.id_currency';
    }

    private function nativeSampleCondition(string $orderAlias = 'o', string $detailAlias = 'od'): string
    {
        return '('
            . ' LOWER(TRIM(' . $detailAlias . ".product_name)) LIKE 'muestra%'"
            . ' OR EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc_match'
            . ' WHERE fsc_match.id_cart=' . $orderAlias . '.id_cart AND ('
            . ' (' . $detailAlias . '.id_customization>0 AND fsc_match.id_customization=' . $detailAlias . '.id_customization)'
            . ' OR (fsc_match.id_product=' . $detailAlias . '.product_id'
            . ' AND fsc_match.id_product_attribute=' . $detailAlias . '.product_attribute_id'
            . ' AND ABS(fsc_match.unit_price_tax_incl-' . $detailAlias . '.unit_price_tax_incl)<=0.05)'
            . ' ))'
            . ')';
    }

    /**
     * @param list<array<string,mixed>> $base
     * @param list<array<string,mixed>> $preferred
     * @return list<array<string,mixed>>
     */
    private function mergeRows(array $base, array $preferred): array
    {
        $merged = [];
        foreach ($base as $row) {
            $merged[$this->rowKey($row)] = $row;
        }
        foreach ($preferred as $row) {
            $key = $this->rowKey($row);
            $merged[$key] = isset($merged[$key]) ? array_replace($merged[$key], $row) : $row;
        }

        return array_values($merged);
    }

    /** @param array<string,mixed> $row */
    private function rowKey(array $row): string
    {
        $idOrderDetail = (int) ($row['id_order_detail'] ?? 0);
        if ($idOrderDetail > 0) {
            return 'd:' . $idOrderDetail;
        }

        return 'o:' . (int) ($row['id_order'] ?? 0)
            . ':p:' . (int) ($row['id_product'] ?? 0)
            . ':a:' . (int) ($row['id_product_attribute'] ?? 0)
            . ':c:' . (int) ($row['id_customization'] ?? 0);
    }
}
