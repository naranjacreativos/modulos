<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class StockAdminRepository
{
    /** @return array{total:int,rows:list<array<string,mixed>>} */
    public function search(int $idShop, int $idLang, string $search, string $filter, int $offset, int $limit, int $lowThreshold): array
    {
        $where = 'fsp.id_shop=' . $idShop . " AND fsp.stock_mode='independent'";
        if ($search !== '') {
            $escaped = pSQL($search);
            $where .= " AND (fsp.id_product=" . (int) $search . " OR pl.name LIKE '%" . $escaped . "%' OR p.reference LIKE '%" . $escaped . "%')";
        }
        if ($filter === 'low') {
            $where .= ' AND fsp.sample_stock<=' . max(0, $lowThreshold);
        } elseif ($filter === 'out') {
            $where .= ' AND fsp.sample_stock<=0';
        }
        $from = ' FROM `' . _DB_PREFIX_ . 'fabricssamples_product` fsp'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product=fsp.id_product'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product=fsp.id_product'
            . ' AND pl.id_shop=fsp.id_shop AND pl.id_lang=' . $idLang;
        $total = (int) \Db::getInstance()->getValue('SELECT COUNT(*)' . $from . ' WHERE ' . $where);
        $rows = \Db::getInstance()->executeS(
            'SELECT fsp.id_fabricssamples_product,fsp.id_product,fsp.sample_stock,fsp.active,fsp.available,'
            . ' p.reference,pl.name,'
            . ' COALESCE(cart.reserved,0) reserved,'
            . ' GREATEST(fsp.sample_stock-COALESCE(cart.reserved,0),0) available_after_reservations,'
            . ' COALESCE(mov.consumed,0) consumed,COALESCE(mov.restored,0) restored,'
            . ' GREATEST(COALESCE(mov.consumed,0)-COALESCE(mov.restored,0),0) net_consumed'
            . $from
            // Only active carts are reservations. Once a cart has generated an order it
            // becomes immutable history and must not keep reducing the displayed available stock.
            . ' LEFT JOIN (SELECT fsc.id_product,fsc.id_shop,SUM(fsc.quantity) reserved'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_cart=fsc.id_cart'
            . ' WHERE o.id_order IS NULL GROUP BY fsc.id_product,fsc.id_shop) cart'
            . ' ON cart.id_product=fsp.id_product AND cart.id_shop=fsp.id_shop'
            . ' LEFT JOIN (SELECT id_product,id_shop,SUM(CASE WHEN quantity_delta<0 THEN -quantity_delta ELSE 0 END) consumed,'
            . ' SUM(CASE WHEN quantity_delta>0 THEN quantity_delta ELSE 0 END) restored'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement`'
            // Native product compensation is a separate accounting system and must not
            // appear as restored sample stock in the Samples stock screen.
            . " WHERE movement_type<>'native_stock_compensation'"
            . ' GROUP BY id_product,id_shop) mov'
            . ' ON mov.id_product=fsp.id_product AND mov.id_shop=fsp.id_shop'
            . ' WHERE ' . $where
            . ' ORDER BY fsp.sample_stock ASC,pl.name ASC LIMIT ' . max(0, $offset) . ',' . max(1, $limit)
        );
        return ['total' => $total, 'rows' => is_array($rows) ? array_values($rows) : []];
    }

    /** @return list<array<string,mixed>> */
    public function allStock(int $idShop, int $idLang, int $lowThreshold): array
    {
        return $this->search($idShop, $idLang, '', '', 0, 50000, $lowThreshold)['rows'];
    }

    public function findProduct(int $idProduct, int $idShop): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT fsp.*,pl.name,p.reference FROM `' . _DB_PREFIX_ . 'fabricssamples_product` fsp'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product=fsp.id_product'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product=fsp.id_product AND pl.id_shop=fsp.id_shop'
            . ' AND pl.id_lang=' . (int) \Context::getContext()->language->id
            . ' WHERE fsp.id_product=' . $idProduct . ' AND fsp.id_shop=' . $idShop
        );
        return is_array($rows) && isset($rows[0]) ? $rows[0] : [];
    }

    /** @return list<array<string,mixed>> */
    public function movements(int $idShop, int $limit = 200): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT sm.*,pl.name product_name,p.reference,e.firstname employee_firstname,e.lastname employee_lastname'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement` sm'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product=sm.id_product'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product=sm.id_product AND pl.id_shop=sm.id_shop'
            . ' AND pl.id_lang=' . (int) \Context::getContext()->language->id
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.id_employee=sm.id_employee'
            . ' WHERE sm.id_shop=' . $idShop
            . ' ORDER BY sm.id_fabricssamples_stock_movement DESC LIMIT ' . max(1, min(5000, $limit))
        );
        return is_array($rows) ? array_values($rows) : [];
    }
}
