<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Domain;

final class StockReconciliationPolicy
{
    public function targetConsumedQuantity(
        int $orderedQuantity,
        int $refundedQuantity,
        int $returnedQuantity,
        bool $terminal
    ): int {
        $orderedQuantity = max(0, $orderedQuantity);
        if ($terminal || $orderedQuantity === 0) {
            return 0;
        }

        // PrestaShop can expose the same physical units as refunded and returned.
        // Using the greatest cumulative counter avoids restoring the same unit twice.
        $cancelledQuantity = max(0, min($orderedQuantity, max($refundedQuantity, $returnedQuantity)));

        return max(0, $orderedQuantity - $cancelledQuantity);
    }

    public function targetNativeCompensationQuantity(
        int $orderedQuantity,
        int $reinjectedQuantity,
        bool $nativeStockRestoredByStatus
    ): int {
        $orderedQuantity = max(0, $orderedQuantity);
        if ($orderedQuantity === 0 || $nativeStockRestoredByStatus) {
            return 0;
        }

        // A sample is represented by a native customized product line, so PrestaShop
        // decrements the original product stock. The module keeps an equal positive
        // compensation while that native decrement is effective. Only quantities that
        // PrestaShop has actually reinjected reduce the compensation.
        $reinjectedQuantity = max(0, min($orderedQuantity, $reinjectedQuantity));

        return max(0, $orderedQuantity - $reinjectedQuantity);
    }

    public function desiredNetDelta(int $targetConsumedQuantity): int
    {
        return -max(0, $targetConsumedQuantity);
    }

    public function adjustment(int $currentNetDelta, int $desiredNetDelta): int
    {
        return $desiredNetDelta - $currentNetDelta;
    }
}
