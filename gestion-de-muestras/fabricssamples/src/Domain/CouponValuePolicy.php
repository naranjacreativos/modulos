<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Domain;

final class CouponValuePolicy
{
    public function calculate(
        float $sampleTotal,
        string $mode,
        float $percentage,
        float $fixedAmount,
        float $cheapestUnit = 0.0,
        float $mostExpensiveUnit = 0.0
    ): float {
        $sampleTotal = max(0.0, $sampleTotal);
        $value = match ($mode) {
            'cheapest' => max(0.0, $cheapestUnit),
            'most_expensive' => max(0.0, $mostExpensiveUnit),
            'percentage' => $sampleTotal * min(100.0, max(0.0, $percentage)) / 100,
            'fixed' => max(0.0, $fixedAmount),
            default => $sampleTotal,
        };

        return min($sampleTotal, max(0.0, $value));
    }
}
