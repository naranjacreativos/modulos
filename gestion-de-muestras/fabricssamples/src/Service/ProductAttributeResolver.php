<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

final class ProductAttributeResolver
{
    public function resolve(int $idProduct, int $requestedAttribute, int $idShop): int
    {
        if ($idProduct <= 0 || $idShop <= 0) {
            return 0;
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT pa.id_product_attribute,pas.default_on'
            . ' FROM `' . _DB_PREFIX_ . 'product_attribute` pa'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` pas'
            . ' ON pas.id_product_attribute=pa.id_product_attribute'
            . ' AND pas.id_shop=' . $idShop
            . ' WHERE pa.id_product=' . $idProduct
            . ' ORDER BY pas.default_on DESC,pa.id_product_attribute ASC'
        );

        $candidates = [];
        $defaultAttribute = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $idAttribute = (int) ($row['id_product_attribute'] ?? 0);
            if ($idAttribute <= 0) {
                continue;
            }
            $candidates[] = $idAttribute;
            if ($defaultAttribute === 0 && (int) ($row['default_on'] ?? 0) === 1) {
                $defaultAttribute = $idAttribute;
            }
        }

        return self::choose($requestedAttribute, $defaultAttribute, $candidates);
    }

    /** @param list<int> $candidates */
    public static function choose(int $requestedAttribute, int $defaultAttribute, array $candidates): int
    {
        $candidates = array_values(array_unique(array_filter(
            array_map('intval', $candidates),
            static function (int $id): bool {
                return $id > 0;
            }
        )));

        if ($requestedAttribute > 0 && in_array($requestedAttribute, $candidates, true)) {
            return $requestedAttribute;
        }
        if ($defaultAttribute > 0 && in_array($defaultAttribute, $candidates, true)) {
            return $defaultAttribute;
        }

        return $candidates[0] ?? 0;
    }
}
