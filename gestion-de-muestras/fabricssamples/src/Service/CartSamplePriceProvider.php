<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Repository\CartSampleRepository;

final class CartSamplePriceProvider
{
    public function __construct(private CartSampleRepository $repository)
    {
    }

    public function apply(array &$params): void
    {
        $idCustomization = (int) ($params['id_customization'] ?? 0);
        if ($idCustomization <= 0) {
            return;
        }

        $line = $this->repository->findByCustomization(
            $idCustomization,
            (int) ($params['id_cart'] ?? 0)
        );
        if ($line === []) {
            return;
        }

        $params['price'] = self::selectPrice($line, !empty($params['use_tax']));
        if (array_key_exists('specific_price_reduction', $params)) {
            $params['specific_price_reduction'] = 0.0;
        }
        if (isset($params['specific_price']) && is_array($params['specific_price'])) {
            $params['specific_price']['reduction'] = 0.0;
            $params['specific_price']['reduction_tax'] = 0;
            $params['specific_price']['reduction_type'] = 'amount';
        }
    }

    public static function selectPrice(array $line, bool $taxIncluded): float
    {
        $value = $taxIncluded
            ? (float) ($line['unit_price_tax_incl'] ?? 0.0)
            : (float) ($line['unit_price_tax_excl'] ?? 0.0);

        return is_finite($value) && $value >= 0.0 ? $value : 0.0;
    }
}
