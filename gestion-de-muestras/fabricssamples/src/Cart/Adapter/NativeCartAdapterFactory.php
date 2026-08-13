<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Cart\Adapter;

final class NativeCartAdapterFactory
{
    public static function forCurrentPlatform(): NativeCartAdapterInterface
    {
        return self::forVersion(defined('_PS_VERSION_') ? (string) _PS_VERSION_ : '8.1.0');
    }

    public static function forVersion(string $version): NativeCartAdapterInterface
    {
        return version_compare($version, '9.0.0', '>=')
            ? new PrestaShop9CartAdapter()
            : new PrestaShop8CartAdapter();
    }
}
