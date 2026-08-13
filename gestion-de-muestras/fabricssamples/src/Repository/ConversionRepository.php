<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class ConversionRepository
{
    /** @return list<array<string,mixed>> */
    public function sampledProductsBeforeOrder(int $idCustomer, int $idShop, int $idOrder): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT DISTINCT id_order AS id_sample_order, id_product'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
            . ' WHERE id_customer=' . $idCustomer
            . ' AND id_shop=' . $idShop
            . ' AND id_order<>' . $idOrder
            . ' AND date_add <= (SELECT date_add FROM `' . _DB_PREFIX_ . 'orders` WHERE id_order=' . $idOrder . ')'
        );

        return is_array($rows) ? $rows : [];
    }

    public function insert(array $data): bool
    {
        return \Db::getInstance()->insert('fabricssamples_conversion', $data, false, true);
    }
}
