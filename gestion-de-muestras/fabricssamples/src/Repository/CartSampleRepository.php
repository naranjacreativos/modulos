<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class CartSampleRepository
{
    public function tableExists(): bool
    {
        return (bool) \Db::getInstance()->getValue(
            'SHOW TABLES LIKE \'' . pSQL(_DB_PREFIX_ . 'fabricssamples_cart') . '\''
        );
    }

    public function findByCustomization(int $idCustomization, int $idCart = 0): array
    {
        $where = 'id_customization=' . $idCustomization;
        if ($idCart > 0) {
            $where .= ' AND id_cart=' . $idCart;
        }

        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` WHERE ' . $where
        );

        return is_array($row) ? $row : [];
    }

    public function lockCart(int $idCart): void
    {
        if ($idCart <= 0) {
            return;
        }
        \Db::getInstance()->executeS(
            'SELECT id_cart FROM `' . _DB_PREFIX_ . 'cart` WHERE id_cart=' . $idCart . ' FOR UPDATE'
        );
        \Db::getInstance()->executeS(
            'SELECT id_fabricssamples_cart FROM `' . _DB_PREFIX_ . 'fabricssamples_cart`'
            . ' WHERE id_cart=' . $idCart . ' FOR UPDATE'
        );
    }

    public function findByCartProduct(int $idCart, int $idProduct, int $idProductAttribute): array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_cart`'
            . ' WHERE id_cart=' . $idCart
            . ' AND id_product=' . $idProduct
            . ' AND id_product_attribute=' . $idProductAttribute
        );

        return is_array($row) ? $row : [];
    }

    /** @return list<array<string, mixed>> */
    public function findByCart(int $idCart): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` WHERE id_cart=' . $idCart
            . ' ORDER BY id_fabricssamples_cart ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<int> */
    public function customizationIdsByCart(int $idCart): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT id_customization FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` WHERE id_cart=' . $idCart
        );

        return array_values(array_filter(array_map('intval', array_column($rows ?: [], 'id_customization'))));
    }

    /** @return list<int> */
    public function allCustomizationIds(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT id_customization FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` WHERE id_customization > 0'
        );

        return array_values(array_filter(array_map('intval', array_column($rows ?: [], 'id_customization'))));
    }

    public function totalQuantity(int $idCart): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(quantity),0) FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` WHERE id_cart=' . $idCart
        );
    }

    public function productQuantity(int $idCart, int $idProduct): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(quantity),0) FROM `' . _DB_PREFIX_ . 'fabricssamples_cart`'
            . ' WHERE id_cart=' . $idCart . ' AND id_product=' . $idProduct
        );
    }

    public function insert(array $data): bool
    {
        return \Db::getInstance()->insert('fabricssamples_cart', $data);
    }

    public function updateQuantity(int $idRow, int $quantity): bool
    {
        return \Db::getInstance()->update(
            'fabricssamples_cart',
            ['quantity' => max(1, $quantity), 'date_upd' => date('Y-m-d H:i:s')],
            'id_fabricssamples_cart=' . $idRow
        );
    }

    public function updateCustomizationId(int $idRow, int $idCustomization): bool
    {
        return \Db::getInstance()->update(
            'fabricssamples_cart',
            ['id_customization' => $idCustomization, 'date_upd' => date('Y-m-d H:i:s')],
            'id_fabricssamples_cart=' . $idRow
        );
    }

    public function updateProductAttribute(int $idRow, int $idProductAttribute): bool
    {
        return \Db::getInstance()->update(
            'fabricssamples_cart',
            ['id_product_attribute' => max(0, $idProductAttribute), 'date_upd' => date('Y-m-d H:i:s')],
            'id_fabricssamples_cart=' . $idRow
        );
    }

    public function deleteById(int $idRow): bool
    {
        return \Db::getInstance()->delete(
            'fabricssamples_cart',
            'id_fabricssamples_cart=' . $idRow
        );
    }

    public function deleteByCustomization(int $idCustomization): bool
    {
        return \Db::getInstance()->delete('fabricssamples_cart', 'id_customization=' . $idCustomization);
    }
}
