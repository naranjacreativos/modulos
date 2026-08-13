<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Domain;

/**
 * Validates and calculates a manual stock adjustment without depending on PrestaShop.
 */
final class StockAdjustmentPolicy
{
    public function targetStock(int $currentStock, int $delta): int
    {
        if ($currentStock < 0) {
            throw new \InvalidArgumentException('El stock actual no puede ser negativo.');
        }
        if ($delta === 0) {
            throw new \InvalidArgumentException('El ajuste no puede ser cero.');
        }

        $target = $currentStock + $delta;
        if ($target < 0) {
            throw new \InvalidArgumentException('El ajuste dejaría el stock en negativo.');
        }

        return $target;
    }
}
