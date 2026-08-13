<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Domain;

final class CouponState
{
    public const PENDING = 'pending';
    public const ELIGIBLE = 'eligible';
    public const GENERATED = 'generated';
    public const AVAILABLE = 'available';
    public const USED = 'used';
    public const EXPIRED = 'expired';
    public const INACTIVE = 'inactive';
    public const CANCELLED = 'cancelled';
    public const DELETED_PERMANENTLY = 'deleted_permanently';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::ELIGIBLE,
            self::GENERATED,
            self::AVAILABLE,
            self::USED,
            self::EXPIRED,
            self::INACTIVE,
            self::CANCELLED,
            self::DELETED_PERMANENTLY,
        ];
    }
}
