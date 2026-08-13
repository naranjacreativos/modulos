<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Domain;

final class CouponReactivationPolicy
{
    public function canReactivate(array $coupon, int $usageCount): bool
    {
        return empty($coupon['deleted_permanently'])
            && $usageCount > max(0, (int) ($coupon['reactivation_count'] ?? 0));
    }

    public function isConsumed(int $usageCount, int $reactivationCount): bool
    {
        return $usageCount > max(0, $reactivationCount);
    }
}
