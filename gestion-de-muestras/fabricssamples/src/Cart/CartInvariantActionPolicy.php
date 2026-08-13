<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Cart;

final class CartInvariantActionPolicy
{
    public const NONE = 'none';
    public const REMOVE_MODULE_ROW = 'remove_module_row';
    public const RECREATE_CUSTOMIZATION = 'recreate_customization';
    public const RECREATE_CART_PRODUCT = 'recreate_cart_product';
    public const NORMALIZE = 'normalize';

    public function decide(CartLineInvariant $state): string
    {
        if (!$state->cartProductExists && !$state->customizationExists) {
            return self::REMOVE_MODULE_ROW;
        }
        if (!$state->customizationExists) {
            return self::RECREATE_CUSTOMIZATION;
        }
        if (!$state->cartProductExists) {
            return self::RECREATE_CART_PRODUCT;
        }

        return $state->isConsistent() ? self::NONE : self::NORMALIZE;
    }
}
