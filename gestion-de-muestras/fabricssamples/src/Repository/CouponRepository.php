<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class CouponRepository
{
    public function isSuppressed(int $idOrder): bool
    {
        if ($idOrder <= 0) {
            return false;
        }
        $this->ensureSuppressionTable();
        $suppressed = (bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_suppression` WHERE id_order=' . $idOrder
        );
        if ($suppressed) {
            return true;
        }
        if (!$this->tableExists() || !$this->columnExists('deleted_permanently')) {
            return false;
        }
        return (bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon`'
            . ' WHERE id_order=' . $idOrder . ' AND deleted_permanently=1'
        );
    }

    public function suppressOrder(int $idOrder, int $idShop, int $idCustomer, string $reason = 'manual_delete'): bool
    {
        if ($idOrder <= 0 || !$this->ensureSuppressionTable()) {
            return false;
        }
        return \Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'fabricssamples_coupon_suppression`'
            . ' (`id_order`,`id_shop`,`id_customer`,`reason`,`date_add`) VALUES ('
            . $idOrder . ',' . max(0, $idShop) . ',' . max(0, $idCustomer) . ',\'' . pSQL($reason) . '\',NOW())'
            . ' ON DUPLICATE KEY UPDATE id_shop=VALUES(id_shop),id_customer=VALUES(id_customer),reason=VALUES(reason),date_add=VALUES(date_add)'
        );
    }

    public function suppressionTableExists(): bool
    {
        return (bool) \Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='" . pSQL(_DB_NAME_) . "' AND table_name='" . pSQL(_DB_PREFIX_ . 'fabricssamples_coupon_suppression') . "'"
        );
    }

    public function ensureSuppressionTable(): bool
    {
        if ($this->suppressionTableExists()) {
            return true;
        }
        return \Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'fabricssamples_coupon_suppression` ('
            . '`id_order` INT UNSIGNED NOT NULL,'
            . '`id_shop` INT UNSIGNED NOT NULL DEFAULT 0,'
            . '`id_customer` INT UNSIGNED NOT NULL DEFAULT 0,'
            . "`reason` VARCHAR(64) NOT NULL DEFAULT 'manual_delete',"
            . '`date_add` DATETIME NOT NULL,'
            . 'PRIMARY KEY (`id_order`),'
            . 'KEY `idx_suppression_shop` (`id_shop`,`date_add`),'
            . 'KEY `idx_suppression_customer` (`id_customer`,`date_add`)'
            . ') ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function findByOrder(int $idOrder): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` WHERE id_order=' . $idOrder
        );
        return is_array($row) ? $row : [];
    }

    /** @return list<array<string,mixed>> */
    public function findByCustomer(int $idCustomer, int $idShop): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $rows = \Db::getInstance()->executeS(
            'SELECT fsc.*, o.reference order_reference, cr.active cart_rule_active,'
            . ' ((SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr WHERE ocr.id_cart_rule=fsc.id_cart_rule AND ocr.deleted=0)'
            . ' > COALESCE(fsc.reactivation_count,0)) AS used'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fsc.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.id_cart_rule=fsc.id_cart_rule'
            . ' WHERE fsc.id_customer=' . $idCustomer . ' AND fsc.id_shop=' . $idShop
            . ($this->columnExists('deleted_permanently') ? ' AND fsc.deleted_permanently=0' : '')
            . ' ORDER BY fsc.date_add DESC'
        );
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string,mixed>> */
    public function findForAdmin(int $idShop, int $limit = 250): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $conditions = [];
        if ($idShop > 0) {
            $conditions[] = 'fsc.id_shop=' . $idShop;
        }
        if ($this->columnExists('deleted_permanently')) {
            $conditions[] = 'fsc.deleted_permanently=0';
        }
        $shopFilter = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $rows = \Db::getInstance()->executeS(
            'SELECT fsc.*, o.reference order_reference, c.firstname, c.lastname, c.email,'
            . ' s.name shop_name, cr.active cart_rule_active,'
            . ' ((SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr WHERE ocr.id_cart_rule=fsc.id_cart_rule AND ocr.deleted=0)'
            . ' > COALESCE(fsc.reactivation_count,0)) AS used'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fsc.id_order'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer=fsc.id_customer'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.id_shop=fsc.id_shop'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.id_cart_rule=fsc.id_cart_rule'
            . $shopFilter
            . ' ORDER BY used ASC, (fsc.date_to < NOW()) ASC, fsc.date_add DESC'
            . ' LIMIT ' . max(1, min(1000, $limit))
        );
        return is_array($rows) ? $rows : [];
    }

    /** @return array{pending:int,used:int,expired:int,total:int} */
    public function stats(int $idShop): array
    {
        if (!$this->tableExists()) {
            return ['pending' => 0, 'used' => 0, 'expired' => 0, 'total' => 0];
        }
        $conditions = [];
        if ($idShop > 0) {
            $conditions[] = 'fsc.id_shop=' . $idShop;
        }
        if ($this->columnExists('deleted_permanently')) {
            $conditions[] = 'fsc.deleted_permanently=0';
        }
        $shopFilter = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $row = \Db::getInstance()->getRow(
            'SELECT COUNT(*) total,'
            . ' SUM(CASE WHEN fsc.date_to>=NOW() AND COALESCE(cr.active,0)=1'
            . ' AND (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr WHERE ocr.id_cart_rule=fsc.id_cart_rule AND ocr.deleted=0)'
            . ' <= COALESCE(fsc.reactivation_count,0) THEN 1 ELSE 0 END) pending,'
            . ' SUM(CASE WHEN (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr WHERE ocr.id_cart_rule=fsc.id_cart_rule AND ocr.deleted=0)'
            . ' > COALESCE(fsc.reactivation_count,0) THEN 1 ELSE 0 END) used,'
            . ' SUM(CASE WHEN fsc.date_to<NOW() THEN 1 ELSE 0 END) expired'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.id_cart_rule=fsc.id_cart_rule'
            . $shopFilter
        );
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'used' => (int) ($row['used'] ?? 0),
            'expired' => (int) ($row['expired'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    public function tableExists(): bool
    {
        return (bool) \Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='" . pSQL(_DB_NAME_) . "' AND table_name='" . pSQL(_DB_PREFIX_ . 'fabricssamples_coupon') . "'"
        );
    }

    /** @return list<int> */
    public function cartRuleIds(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $rows = \Db::getInstance()->executeS(
            'SELECT id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` WHERE id_cart_rule > 0'
            . ($this->columnExists('deleted_permanently') ? ' AND deleted_permanently=0' : '')
        );
        return array_values(array_filter(array_map('intval', array_column(is_array($rows) ? $rows : [], 'id_cart_rule'))));
    }


    public function findById(int $idCoupon): array
    {
        if (!$this->tableExists() || $idCoupon <= 0) {
            return [];
        }
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon`'
            . ' WHERE id_fabricssamples_coupon=' . $idCoupon
        );
        return is_array($row) ? $row : [];
    }

    public function deleteById(int $idCoupon): bool
    {
        if (!$this->tableExists() || $idCoupon <= 0) {
            return false;
        }
        return \Db::getInstance()->delete(
            'fabricssamples_coupon',
            'id_fabricssamples_coupon=' . $idCoupon
        );
    }

    public function insert(array $data): bool
    {
        return \Db::getInstance()->insert('fabricssamples_coupon', $data, true);
    }

    public function deleteByOrder(int $idOrder): bool
    {
        return !$this->tableExists() || \Db::getInstance()->delete('fabricssamples_coupon', 'id_order=' . $idOrder);
    }

    public function updateById(int $idCoupon, array $data): bool
    {
        if (!$this->tableExists() || $idCoupon <= 0 || $data === []) {
            return false;
        }
        return \Db::getInstance()->update(
            'fabricssamples_coupon',
            $data,
            'id_fabricssamples_coupon=' . $idCoupon
        );
    }

    public function usageCount(int $idCartRule): int
    {
        return $idCartRule > 0 ? (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` WHERE deleted=0 AND id_cart_rule=' . $idCartRule
        ) : 0;
    }

    public function markDeletedPermanently(int $idCoupon, int $idEmployee = 0): bool
    {
        if (!$this->tableExists() || $idCoupon <= 0) {
            return false;
        }
        if (!$this->columnExists('deleted_permanently')) {
            return false;
        }
        return \Db::getInstance()->update(
            'fabricssamples_coupon',
            [
                'deleted_permanently' => 1,
                'state' => 'deleted_permanently',
                'state_reason' => 'manual_delete',
                'date_state' => date('Y-m-d H:i:s'),
                'date_deleted' => date('Y-m-d H:i:s'),
                'deleted_by_employee' => max(0, $idEmployee),
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            'id_fabricssamples_coupon=' . $idCoupon
        );
    }

    private function columnExists(string $column): bool
    {
        return (bool) \Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='" . pSQL(_DB_NAME_)
            . "' AND table_name='" . pSQL(_DB_PREFIX_ . 'fabricssamples_coupon')
            . "' AND column_name='" . pSQL($column) . "'"
        );
    }

    /** @return list<array<string,mixed>> */
    public function findOrphaned(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $rows = \Db::getInstance()->executeS(
            'SELECT fsc.* FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fsc.id_order'
            . ' WHERE o.id_order IS NULL'
        );
        return is_array($rows) ? $rows : [];
    }

    public function markEmailSent(int $idOrder): bool
    {
        return \Db::getInstance()->update(
            'fabricssamples_coupon',
            ['email_sent' => 1, 'date_upd' => date('Y-m-d H:i:s')],
            'id_order=' . $idOrder
        );
    }
}
