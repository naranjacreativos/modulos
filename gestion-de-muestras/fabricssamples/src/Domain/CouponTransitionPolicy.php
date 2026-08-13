<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Domain;

final class CouponTransitionPolicy
{
    public function stateForExisting(
        bool $suppressed,
        bool $used,
        bool $expired,
        bool $orderTerminal,
        bool $active
    ): string {
        if ($suppressed) {
            return CouponState::DELETED_PERMANENTLY;
        }
        if ($used) {
            return CouponState::USED;
        }
        if ($orderTerminal) {
            return CouponState::CANCELLED;
        }
        if ($expired) {
            return CouponState::EXPIRED;
        }

        return $active ? CouponState::AVAILABLE : CouponState::INACTIVE;
    }

    public function shouldGenerate(bool $enabled, bool $eligible, bool $suppressed, bool $hasSamples): bool
    {
        return $enabled && $eligible && !$suppressed && $hasSamples;
    }

    public function shouldReactivate(
        string $currentState,
        bool $eligible,
        bool $suppressed,
        bool $used,
        bool $expired
    ): bool {
        return $eligible
            && !$suppressed
            && !$used
            && !$expired
            && $currentState === CouponState::CANCELLED;
    }
}
