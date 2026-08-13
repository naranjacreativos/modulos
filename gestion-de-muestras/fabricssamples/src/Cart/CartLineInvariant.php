<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Cart;

final class CartLineInvariant
{
    public function __construct(
        public readonly int $moduleQuantity,
        public readonly int $cartQuantity,
        public readonly int $customizationQuantity,
        public readonly bool $cartProductExists,
        public readonly bool $customizationExists,
        public readonly int $idCustomization,
        public readonly int $cartAddressId,
        public readonly int $customizationAddressId
    ) {
    }

    public function canonicalQuantity(): int
    {
        if ($this->cartProductExists && $this->cartQuantity > 0) {
            return $this->cartQuantity;
        }

        if ($this->moduleQuantity > 0) {
            return $this->moduleQuantity;
        }

        return max(0, $this->customizationQuantity);
    }

    public function isConsistent(): bool
    {
        if (!$this->cartProductExists || !$this->customizationExists) {
            return false;
        }

        $quantity = $this->canonicalQuantity();
        if ($quantity <= 0) {
            return false;
        }

        return $this->moduleQuantity === $quantity
            && $this->cartQuantity === $quantity
            && $this->customizationQuantity === $quantity
            && $this->cartAddressId === $this->customizationAddressId;
    }

    /** @return list<string> */
    public function issues(): array
    {
        $issues = [];
        if (!$this->cartProductExists) {
            $issues[] = 'missing_cart_product';
        }
        if (!$this->customizationExists) {
            $issues[] = 'missing_customization';
        }
        if ($this->cartProductExists && $this->moduleQuantity !== $this->cartQuantity) {
            $issues[] = 'module_cart_quantity_mismatch';
        }
        if ($this->customizationExists && $this->cartQuantity !== $this->customizationQuantity) {
            $issues[] = 'cart_customization_quantity_mismatch';
        }
        if (
            $this->cartProductExists
            && $this->customizationExists
            && $this->cartAddressId !== $this->customizationAddressId
        ) {
            $issues[] = 'delivery_address_mismatch';
        }

        return $issues;
    }
}
