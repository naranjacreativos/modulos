<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class CouponReissueRepository
{
    public function tableExists(): bool
    {
        return (bool) \Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='" . pSQL(_DB_NAME_)
            . "' AND table_name='" . pSQL(_DB_PREFIX_ . 'fabricssamples_coupon_reissue') . "'"
        );
    }

    public function insert(array $data): bool
    {
        return $this->tableExists()
            && \Db::getInstance()->insert('fabricssamples_coupon_reissue', $data, true);
    }

    public function findById(int $idReissue): array
    {
        if (!$this->tableExists() || $idReissue <= 0) {
            return [];
        }
        $row = \Db::getInstance()->getRow(
            'SELECT r.*, fsc.discount_mode, fsc.discount_value, fsc.minimum_order,'
            . ' cr.active cart_rule_active, cr.date_to cart_rule_date_to,'
            . ' (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr'
            . ' WHERE ocr.id_cart_rule=r.id_cart_rule AND ocr.deleted=0) usage_count,'
            . ' (SELECT MIN(o.date_add) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=ocr.id_order'
            . ' WHERE ocr.id_cart_rule=r.id_cart_rule AND ocr.deleted=0) native_date_used'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` r'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' ON fsc.id_fabricssamples_coupon=r.id_fabricssamples_coupon'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.id_cart_rule=r.id_cart_rule'
            . ' WHERE r.id_fabricssamples_coupon_reissue=' . $idReissue
        );

        return is_array($row) ? $row : [];
    }

    /** @return list<array<string,mixed>> */
    public function findByCoupon(int $idCoupon): array
    {
        if (!$this->tableExists() || $idCoupon <= 0) {
            return [];
        }
        $rows = \Db::getInstance()->executeS(
            'SELECT r.*, fsc.discount_mode, fsc.discount_value, fsc.minimum_order,'
            . ' cr.active cart_rule_active, cr.date_to cart_rule_date_to,'
            . ' (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr'
            . ' WHERE ocr.id_cart_rule=r.id_cart_rule AND ocr.deleted=0) usage_count,'
            . ' (SELECT MIN(o.date_add) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=ocr.id_order'
            . ' WHERE ocr.id_cart_rule=r.id_cart_rule AND ocr.deleted=0) native_date_used'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` r'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' ON fsc.id_fabricssamples_coupon=r.id_fabricssamples_coupon'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.id_cart_rule=r.id_cart_rule'
            . ' WHERE r.id_fabricssamples_coupon=' . $idCoupon
            . ' ORDER BY r.reissue_number ASC'
        );

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @return list<array<string,mixed>> */
    public function findByCustomer(int $idCustomer, int $idShop): array
    {
        if (!$this->tableExists() || $idCustomer <= 0 || $idShop <= 0) {
            return [];
        }
        $rows = \Db::getInstance()->executeS(
            'SELECT r.id_fabricssamples_coupon_reissue,r.id_fabricssamples_coupon,r.id_order,'
            . ' r.id_customer,r.id_shop,r.id_cart_rule,r.code,r.state,r.reissue_number,'
            . ' r.date_from,r.date_to,r.date_add,r.date_upd,'
            . ' fsc.discount_mode,fsc.discount_value,fsc.minimum_order,'
            . ' o.reference order_reference,cr.active cart_rule_active,'
            . ' ((SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr'
            . ' WHERE ocr.id_cart_rule=r.id_cart_rule AND ocr.deleted=0)>0) used,'
            . ' 1 is_reissue'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` r'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' ON fsc.id_fabricssamples_coupon=r.id_fabricssamples_coupon'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=r.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.id_cart_rule=r.id_cart_rule'
            . ' WHERE r.id_customer=' . $idCustomer . ' AND r.id_shop=' . $idShop
            . " AND r.state<>'deleted_permanently'"
            . ' ORDER BY r.date_add DESC'
        );

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @param list<int> $couponIds
     *  @return array<int,list<array<string,mixed>>>
     */
    public function findGroupedByCoupons(array $couponIds): array
    {
        $couponIds = array_values(array_unique(array_filter(array_map('intval', $couponIds))));
        if (!$this->tableExists() || $couponIds === []) {
            return [];
        }
        $rows = \Db::getInstance()->executeS(
            'SELECT r.*, fsc.discount_mode, fsc.discount_value, fsc.minimum_order,'
            . ' cr.active cart_rule_active, cr.date_to cart_rule_date_to,'
            . ' (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr'
            . ' WHERE ocr.id_cart_rule=r.id_cart_rule AND ocr.deleted=0) usage_count,'
            . ' (SELECT MIN(o.date_add) FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=ocr.id_order'
            . ' WHERE ocr.id_cart_rule=r.id_cart_rule AND ocr.deleted=0) native_date_used'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` r'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' ON fsc.id_fabricssamples_coupon=r.id_fabricssamples_coupon'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.id_cart_rule=r.id_cart_rule'
            . ' WHERE r.id_fabricssamples_coupon IN (' . implode(',', $couponIds) . ')'
            . ' ORDER BY r.id_fabricssamples_coupon ASC, r.reissue_number ASC'
        );
        $grouped = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $grouped[(int) $row['id_fabricssamples_coupon']][] = $row;
        }

        return $grouped;
    }

    public function nextNumber(int $idCoupon): int
    {
        if (!$this->tableExists() || $idCoupon <= 0) {
            return 1;
        }

        return 1 + (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(MAX(reissue_number),0) FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue`'
            . ' WHERE id_fabricssamples_coupon=' . $idCoupon
        );
    }

    public function updateState(int $idReissue, string $state, ?string $dateUsed, bool $pending): bool
    {
        if (!$this->tableExists() || $idReissue <= 0) {
            return false;
        }

        $dateUsedSql = $dateUsed !== null && $dateUsed !== '' ? "'" . pSQL($dateUsed) . "'" : 'NULL';

        return \Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` SET'
            . " state='" . pSQL($state) . "',"
            . ' pending_guard=' . ($pending ? 'id_fabricssamples_coupon' : 'NULL') . ','
            . ' date_used=' . $dateUsedSql . ','
            . " date_upd='" . pSQL(date('Y-m-d H:i:s')) . "'"
            . ' WHERE id_fabricssamples_coupon_reissue=' . $idReissue
        );
    }

    public function markEmailSent(int $idReissue): bool
    {
        return $this->tableExists() && $idReissue > 0 && \Db::getInstance()->update(
            'fabricssamples_coupon_reissue',
            ['email_sent' => 1, 'date_email_sent' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')],
            'id_fabricssamples_coupon_reissue=' . $idReissue
        );
    }

    /** @return list<int> */
    public function cartRuleIds(int $idCoupon = 0): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $rows = \Db::getInstance()->executeS(
            'SELECT id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` WHERE id_cart_rule>0'
            . ($idCoupon > 0 ? ' AND id_fabricssamples_coupon=' . $idCoupon : '')
        );

        return array_values(array_unique(array_filter(array_map(
            'intval',
            array_column(is_array($rows) ? $rows : [], 'id_cart_rule')
        ))));
    }

    public function markDeletedByCoupon(int $idCoupon): bool
    {
        return !$this->tableExists() || $idCoupon <= 0 || \Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . "fabricssamples_coupon_reissue` SET state='deleted_permanently',"
            . " pending_guard=NULL,date_upd='" . pSQL(date('Y-m-d H:i:s')) . "'"
            . ' WHERE id_fabricssamples_coupon=' . $idCoupon
        );
    }

    public function deleteByCoupon(int $idCoupon): bool
    {
        return !$this->tableExists() || $idCoupon <= 0 || \Db::getInstance()->delete(
            'fabricssamples_coupon_reissue',
            'id_fabricssamples_coupon=' . $idCoupon
        );
    }
}
