<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Cart\Adapter;

final class PrestaShop8CartAdapter extends AbstractNativeCartAdapter
{
    public function platformName(): string
    {
        return 'PrestaShop 8';
    }

    protected function deliveryAddressForCart(\Cart $cart): int
    {
        return (int) $cart->id_address_delivery;
    }

    protected function updateQtyAddressArgument(\Cart $cart): int
    {
        return (int) $cart->id_address_delivery;
    }
}
