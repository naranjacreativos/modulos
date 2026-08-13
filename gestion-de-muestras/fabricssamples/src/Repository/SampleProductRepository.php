<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class SampleProductRepository
{
    public function findActive(int $idProduct, int $idShop): array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
            . ' WHERE id_product=' . $idProduct
            . ' AND id_shop=' . $idShop
            . ' AND active=1'
        );

        return is_array($row) ? $row : [];
    }


    public function findAny(int $idProduct, int $idShop): array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
            . ' WHERE id_product=' . $idProduct
            . ' AND id_shop=' . $idShop
        );

        return is_array($row) ? $row : [];
    }

    public function deleteByProduct(int $idProduct): bool
    {
        return \Db::getInstance()->delete('fabricssamples_product', 'id_product=' . $idProduct);
    }

}
