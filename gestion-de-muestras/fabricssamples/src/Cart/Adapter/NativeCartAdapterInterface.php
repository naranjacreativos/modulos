<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Cart\Adapter;

use NaranjaCreativos\FabricSamples\Cart\CartLineInvariant;

interface NativeCartAdapterInterface
{
    public function platformName(): string;

    public function createCustomization(\Cart $cart, int $idProduct, int $idProductAttribute): int;

    public function addQuantity(\Cart $cart, array $line, int $quantity): bool;

    public function changeQuantity(\Cart $cart, array $line, int $quantity, string $direction): bool;

    public function removeLine(\Cart $cart, array $line): bool;

    public function readInvariant(array $line): CartLineInvariant;

    public function forceExactNativeState(array $line, int $quantity): bool;

    public function replaceCustomizationId(array $line, int $newCustomizationId): bool;

    public function replaceProductAttribute(array $line, int $newProductAttributeId): bool;

    /** @param list<int> $customizationIds */
    public function purgeNativeRows(array $customizationIds): bool;
}
