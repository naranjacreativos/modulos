<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class CatalogSampleRepository
{
    /**
     * @return array{total:int, rows:list<array<string,mixed>>}
     */
    public function search(
        int $idShop,
        int $idLang,
        string $search,
        int $idCategory,
        string $order,
        int $offset,
        int $limit,
        float $defaultPrice
    ): array {
        $where = [
            'fs.id_shop=' . $idShop,
            'fs.active=1',
            'COALESCE(fs.available,1)=1',
            'ps.active=1',
        ];

        if ($search !== '') {
            $escaped = pSQL($search);
            $where[] = "(pl.name LIKE '%" . $escaped . "%' OR p.reference LIKE '%" . $escaped . "%')";
        }
        if ($idCategory > 0) {
            $where[] = 'cp.id_category=' . $idCategory;
        }
        $orders = [
            'name_asc' => 'pl.name ASC',
            'name_desc' => 'pl.name DESC',
            'price_asc' => 'effective_price ASC',
            'price_desc' => 'effective_price DESC',
            'newest' => 'p.date_add DESC, p.id_product DESC',
            'popular' => 'sample_popularity DESC, pl.name ASC',
            'id_desc' => 'p.id_product DESC',
        ];
        $orderSql = $orders[$order] ?? $orders['name_asc'];

        $base = ' FROM `' . _DB_PREFIX_ . 'fabricssamples_product` fs'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product=fs.id_product'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop=' . $idShop
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product=p.id_product AND pl.id_shop=' . $idShop . ' AND pl.id_lang=' . $idLang
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'category_product` cp ON cp.id_product=p.id_product AND cp.id_category=p.id_category_default'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category=p.id_category_default AND cl.id_shop=' . $idShop . ' AND cl.id_lang=' . $idLang
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_product_lang` fsl ON fsl.id_fabricssamples_product=fs.id_fabricssamples_product AND fsl.id_lang=' . $idLang
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` ims ON ims.id_product=p.id_product AND ims.id_shop=' . $idShop . ' AND ims.cover=1'
            . ' LEFT JOIN (SELECT id_product, SUM(quantity) sample_popularity FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
            . ' WHERE id_shop=' . $idShop . ' GROUP BY id_product) fpop ON fpop.id_product=p.id_product'
            . ' WHERE ' . implode(' AND ', $where);

        $total = (int) \Db::getInstance()->getValue('SELECT COUNT(DISTINCT p.id_product)' . $base);
        $rows = \Db::getInstance()->executeS(
            'SELECT DISTINCT p.id_product, p.reference, pl.name, pl.link_rewrite, ims.id_image,'
            . ' cl.name category_name,'
            . ' IF(fs.use_default_price=1,' . (float) $defaultPrice . ',fs.sample_price) effective_price,'
            . ' COALESCE(fsl.card_explainer_html, fs.info_text, \'\') card_explainer_html,'
            . ' fs.use_default_price, fs.sample_price, fs.available, fs.sample_stock, fs.stock_mode,'
            . ' fs.max_per_order, fs.max_per_customer, fs.tax_mode, fs.id_tax_rules_group,'
            . ' ps.id_tax_rules_group inherited_tax_rules_group,'
            . ' COALESCE(fpop.sample_popularity,0) sample_popularity'
            . $base
            . ' ORDER BY ' . $orderSql
            . ' LIMIT ' . max(0, $offset) . ',' . max(1, $limit)
        );

        return [
            'total' => $total,
            'rows' => is_array($rows) ? $rows : [],
        ];
    }

    public function countConfigured(int $idShop): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
            . ' WHERE id_shop=' . $idShop . ' AND active=1'
        );
    }

    /** @return list<array{id_category:int,name:string}> */
    public function categories(int $idShop, int $idLang): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT DISTINCT c.id_category,cl.name'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_product` fs'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product=fs.id_product'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop=' . $idShop
            . ' INNER JOIN `' . _DB_PREFIX_ . 'category` c ON c.id_category=p.id_category_default AND c.active=1'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.id_category=c.id_category AND cs.id_shop=' . $idShop
            . ' INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category=c.id_category AND cl.id_shop=' . $idShop . ' AND cl.id_lang=' . $idLang
            . ' WHERE fs.id_shop=' . $idShop . ' AND fs.active=1 AND COALESCE(fs.available,1)=1 AND ps.active=1'
            . ' ORDER BY cl.name ASC'
        );

        return is_array($rows) ? array_values($rows) : [];
    }


}
