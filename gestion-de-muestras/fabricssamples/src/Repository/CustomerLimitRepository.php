<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class CustomerLimitRepository
{
    /**
     * @param list<int> $orderStateIds
     */
    public function quantitySince(
        int $idCustomer,
        int $idShop,
        string $dateFrom,
        ?int $idProduct = null,
        array $orderStateIds = [],
        ?string $resetDate = null
    ): int {
        if ($idCustomer <= 0) {
            return 0;
        }

        $effectiveDate = $dateFrom;
        if ($resetDate !== null && $resetDate > $effectiveDate) {
            $effectiveDate = $resetDate;
        }

        $where = 'fso.id_customer=' . $idCustomer
            . ' AND fso.id_shop=' . $idShop
            . " AND fso.date_add>='" . pSQL($effectiveDate) . "'";
        if ($idProduct !== null && $idProduct > 0) {
            $where .= ' AND fso.id_product=' . $idProduct;
        }

        $orderStateIds = array_values(array_unique(array_filter(array_map('intval', $orderStateIds))));
        if ($orderStateIds !== []) {
            $where .= ' AND o.current_state IN (' . implode(',', $orderStateIds) . ')';
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(fso.quantity),0)'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
            . ' WHERE ' . $where
        );
    }
    /**
     * @param list<int> $orderStateIds
     * @return list<array<string,mixed>>
     */
    public function productBreakdownSince(
        int $idCustomer,
        int $idShop,
        string $dateFrom,
        array $orderStateIds = [],
        ?string $resetDate = null,
        int $limit = 100
    ): array {
        if ($idCustomer <= 0) {
            return [];
        }

        $effectiveDate = $dateFrom;
        if ($resetDate !== null && $resetDate > $effectiveDate) {
            $effectiveDate = $resetDate;
        }

        $where = 'fso.id_customer=' . $idCustomer
            . ' AND fso.id_shop=' . $idShop
            . " AND fso.date_add>='" . pSQL($effectiveDate) . "'";

        $orderStateIds = array_values(array_unique(array_filter(array_map('intval', $orderStateIds))));
        if ($orderStateIds !== []) {
            $where .= ' AND o.current_state IN (' . implode(',', $orderStateIds) . ')';
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT fso.id_product,MAX(fso.product_name) product_name,MAX(fso.product_reference) product_reference,'
            . ' SUM(fso.quantity) quantity,COUNT(DISTINCT fso.id_order) order_count,MAX(fso.date_add) last_order_date'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
            . ' WHERE ' . $where
            . ' GROUP BY fso.id_product'
            . ' ORDER BY quantity DESC,last_order_date DESC LIMIT ' . max(1, min(1000, $limit))
        );

        return is_array($rows) ? array_values($rows) : [];
    }

}
