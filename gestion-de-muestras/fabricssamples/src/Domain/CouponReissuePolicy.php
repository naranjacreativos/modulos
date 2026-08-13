<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Domain;

final class CouponReissuePolicy
{
    public function canIssue(array $coupon, int $usageCount, bool $hasPendingReissue): bool
    {
        return !$hasPendingReissue
            && empty($coupon['deleted_permanently'])
            && $usageCount > max(0, (int) ($coupon['reactivation_count'] ?? 0));
    }

    public function status(bool $ruleExists, int $usageCount, bool $active, string $dateTo): string
    {
        if ($usageCount > 0) {
            return CouponState::USED;
        }
        if (!$ruleExists) {
            return 'missing';
        }
        $expiresAt = strtotime($dateTo);
        if ($expiresAt !== false && $expiresAt < time()) {
            return CouponState::EXPIRED;
        }
        if (!$active) {
            return CouponState::INACTIVE;
        }

        return CouponState::AVAILABLE;
    }

    public function isPending(string $status): bool
    {
        return $status === CouponState::AVAILABLE;
    }
}
