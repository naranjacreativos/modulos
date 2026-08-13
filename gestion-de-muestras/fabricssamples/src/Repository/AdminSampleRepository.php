<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class AdminSampleRepository
{
    /** @return array{active:int,sold:int,revenue:float,orders:int} */
    public function dashboardMetrics(int $idShop): array
    {
        $sales = (new OrderSampleRepository())->aggregateDashboardMetrics($idShop);

        return [
            'active' => (int) \Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'fabricssamples_product` WHERE id_shop=' . $idShop . ' AND active=1'
            ),
            'sold' => (int) $sales['sold'],
            'revenue' => (float) $sales['revenue'],
            'orders' => (int) $sales['orders'],
        ];
    }

    /** @return array{total:int,rows:list<array<string,mixed>>} */
    public function searchProducts(int $idShop, int $idLang, string $search, int $offset, int $limit): array
    {
        $where = ' WHERE ps.id_shop=' . $idShop;
        if ($search !== '') {
            $escaped = pSQL($search);
            $where .= " AND (pl.name LIKE '%" . $escaped . "%' OR p.reference LIKE '%" . $escaped . "%' OR p.id_product=" . (int) $search . ')';
        }

        $from = ' FROM `' . _DB_PREFIX_ . 'product` p'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product=p.id_product'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product=p.id_product AND pl.id_shop=ps.id_shop AND pl.id_lang=' . $idLang;

        $total = (int) \Db::getInstance()->getValue('SELECT COUNT(*)' . $from . $where);
        $rows = \Db::getInstance()->executeS(
            'SELECT p.id_product,p.reference,pl.name,ps.active product_active,'
            . ' fs.active sample_active,fs.available,fs.sample_price,fs.use_default_price,fs.sample_stock'
            . $from
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_product` fs ON fs.id_product=p.id_product AND fs.id_shop=ps.id_shop'
            . $where
            . ' ORDER BY p.id_product DESC LIMIT ' . max(0, $offset) . ',' . max(1, $limit)
        );

        return ['total' => $total, 'rows' => is_array($rows) ? $rows : []];
    }

    public function findProductConfiguration(int $idProduct, int $idShop): array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
            . ' WHERE id_product=' . $idProduct . ' AND id_shop=' . $idShop
        );

        return is_array($row) ? $row : [];
    }

    /** @return array<int,string> */
    public function findProductExplainers(int $idProduct, int $idShop): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT fsl.id_lang, fsl.card_explainer_html'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_product_lang` fsl'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'fabricssamples_product` fs'
            . ' ON fs.id_fabricssamples_product=fsl.id_fabricssamples_product'
            . ' WHERE fs.id_product=' . $idProduct . ' AND fs.id_shop=' . $idShop
        );
        $values = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $values[(int) $row['id_lang']] = (string) $row['card_explainer_html'];
        }

        return $values;
    }

    /** @param array<int,string> $values */
    public function upsertProductExplainers(int $idProduct, int $idShop, array $values): bool
    {
        $idRow = (int) \Db::getInstance()->getValue(
            'SELECT id_fabricssamples_product FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
            . ' WHERE id_product=' . $idProduct . ' AND id_shop=' . $idShop
        );
        if ($idRow <= 0) {
            return false;
        }

        $ok = true;
        foreach ($values as $idLang => $html) {
            $idLang = (int) $idLang;
            if ($idLang <= 0) {
                continue;
            }
            $exists = (bool) \Db::getInstance()->getValue(
                'SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_product_lang`'
                . ' WHERE id_fabricssamples_product=' . $idRow . ' AND id_lang=' . $idLang
            );
            $data = [
                'id_fabricssamples_product' => $idRow,
                'id_lang' => $idLang,
                'card_explainer_html' => (string) $html,
            ];
            if ($exists) {
                $ok = \Db::getInstance()->update(
                    'fabricssamples_product_lang',
                    ['card_explainer_html' => (string) $html],
                    'id_fabricssamples_product=' . $idRow . ' AND id_lang=' . $idLang,
                    0,
                    true
                ) && $ok;
            } else {
                $ok = \Db::getInstance()->insert('fabricssamples_product_lang', $data, false, true) && $ok;
            }
        }

        return $ok;
    }

    public function upsertProduct(int $idProduct, int $idShop, array $data, array $defaults): bool
    {
        $idRow = (int) \Db::getInstance()->getValue(
            'SELECT id_fabricssamples_product FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
            . ' WHERE id_product=' . $idProduct . ' AND id_shop=' . $idShop
        );

        $now = date('Y-m-d H:i:s');
        $base = array_merge([
            'id_product' => $idProduct,
            'id_shop' => $idShop,
            'active' => 0,
            'use_default_price' => 1,
            'sample_price' => (float) ($defaults['sample_price'] ?? 0.0),
            'size_text' => '',
            'info_text' => '',
            'available' => 1,
            'stock_mode' => 'availability',
            'sample_stock' => 0,
            'max_per_order' => (int) ($defaults['max_per_order'] ?? 1),
            'max_per_customer' => 0,
            'sample_weight' => (float) ($defaults['sample_weight'] ?? 0.02),
            'tax_mode' => 'inherit',
            'id_tax_rules_group' => 0,
            'internal_notes' => '',
            'date_add' => $now,
            'date_upd' => $now,
        ], $data);
        $base['date_upd'] = $now;

        if ($idRow > 0) {
            unset($base['id_product'], $base['id_shop'], $base['date_add']);
            return \Db::getInstance()->update('fabricssamples_product', $base, 'id_fabricssamples_product=' . $idRow);
        }

        return \Db::getInstance()->insert('fabricssamples_product', $base);
    }
}
