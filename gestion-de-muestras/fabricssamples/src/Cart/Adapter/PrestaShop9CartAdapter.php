<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Cart\Adapter;

final class PrestaShop9CartAdapter extends AbstractNativeCartAdapter
{
    public function platformName(): string
    {
        return 'PrestaShop 9';
    }

    protected function deliveryAddressForCart(\Cart $cart): int
    {
        return 0;
    }

    protected function updateQtyAddressArgument(\Cart $cart): int
    {
        return 0;
    }
}
