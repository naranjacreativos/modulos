<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Cart\Adapter;

use NaranjaCreativos\FabricSamples\Cart\CartLineInvariant;

abstract class AbstractNativeCartAdapter implements NativeCartAdapterInterface
{
    abstract protected function deliveryAddressForCart(\Cart $cart): int;

    abstract protected function updateQtyAddressArgument(\Cart $cart): int;

    public function createCustomization(\Cart $cart, int $idProduct, int $idProductAttribute): int
    {
        $customization = new \Customization();
        $customization->id_cart = (int) $cart->id;
        $customization->id_product = $idProduct;
        $customization->id_product_attribute = $idProductAttribute;
        $customization->id_address_delivery = $this->deliveryAddressForCart($cart);
        // Quantity starts at zero. PrestaShop 8 may increment it in updateQty(), while
        // PrestaShop 9 does not. forceExactNativeState() normalizes both behaviours.
        $customization->quantity = 0;
        $customization->quantity_refunded = 0;
        $customization->quantity_returned = 0;
        $customization->in_cart = 0;

        if (!$customization->add()) {
            throw new \PrestaShopException('Unable to create sample customization.');
        }

        return (int) $customization->id;
    }

    public function addQuantity(\Cart $cart, array $line, int $quantity): bool
    {
        return (bool) $cart->updateQty(
            $quantity,
            (int) $line['id_product'],
            (int) $line['id_product_attribute'],
            (int) $line['id_customization'],
            'up',
            $this->updateQtyAddressArgument($cart)
        );
    }

    public function changeQuantity(\Cart $cart, array $line, int $quantity, string $direction): bool
    {
        return (bool) $cart->updateQty(
            $quantity,
            (int) $line['id_product'],
            (int) $line['id_product_attribute'],
            (int) $line['id_customization'],
            $direction,
            $this->updateQtyAddressArgument($cart)
        );
    }

    public function removeLine(\Cart $cart, array $line): bool
    {
        $idCustomization = (int) $line['id_customization'];
        $result = (bool) $cart->deleteProduct(
            (int) $line['id_product'],
            (int) $line['id_product_attribute'],
            $idCustomization,
            $this->updateQtyAddressArgument($cart)
        );

        // Keep deletion idempotent across themes and PrestaShop minor versions.
        $db = \Db::getInstance();
        $db->delete(
            'cart_product',
            'id_cart=' . (int) $line['id_cart']
            . ' AND id_product=' . (int) $line['id_product']
            . ' AND id_product_attribute=' . (int) $line['id_product_attribute']
            . ' AND id_customization=' . $idCustomization
        );
        $db->delete('customized_data', 'id_customization=' . $idCustomization);
        $db->delete('customization', 'id_customization=' . $idCustomization);

        return $result || !$this->nativeLineExists($line);
    }

    public function readInvariant(array $line): CartLineInvariant
    {
        $cartRow = \Db::getInstance()->getRow(
            'SELECT quantity,id_address_delivery,id_customization FROM `' . _DB_PREFIX_ . 'cart_product`'
            . ' WHERE id_cart=' . (int) $line['id_cart']
            . ' AND id_product=' . (int) $line['id_product']
            . ' AND id_product_attribute=' . (int) $line['id_product_attribute']
            . ' AND id_customization=' . (int) $line['id_customization']
        );
        $customizationRow = \Db::getInstance()->getRow(
            'SELECT quantity,id_address_delivery FROM `' . _DB_PREFIX_ . 'customization`'
            . ' WHERE id_customization=' . (int) $line['id_customization']
            . ' AND id_cart=' . (int) $line['id_cart']
        );

        return new CartLineInvariant(
            max(0, (int) ($line['quantity'] ?? 0)),
            max(0, (int) ($cartRow['quantity'] ?? 0)),
            max(0, (int) ($customizationRow['quantity'] ?? 0)),
            is_array($cartRow) && $cartRow !== [],
            is_array($customizationRow) && $customizationRow !== [],
            (int) ($line['id_customization'] ?? 0),
            (int) ($cartRow['id_address_delivery'] ?? 0),
            (int) ($customizationRow['id_address_delivery'] ?? 0)
        );
    }

    public function forceExactNativeState(array $line, int $quantity): bool
    {
        $quantity = max(1, $quantity);
        $db = \Db::getInstance();
        $cartRow = $db->getRow(
            'SELECT id_address_delivery FROM `' . _DB_PREFIX_ . 'cart_product`'
            . ' WHERE id_cart=' . (int) $line['id_cart']
            . ' AND id_product=' . (int) $line['id_product']
            . ' AND id_product_attribute=' . (int) $line['id_product_attribute']
            . ' AND id_customization=' . (int) $line['id_customization']
        );
        if (!is_array($cartRow) || $cartRow === []) {
            return false;
        }

        $address = (int) ($cartRow['id_address_delivery'] ?? 0);
        $ok = $db->update(
            'cart_product',
            ['quantity' => $quantity],
            'id_cart=' . (int) $line['id_cart']
            . ' AND id_product=' . (int) $line['id_product']
            . ' AND id_product_attribute=' . (int) $line['id_product_attribute']
            . ' AND id_customization=' . (int) $line['id_customization']
        );
        $ok = $db->update(
            'customization',
            [
                'quantity' => $quantity,
                'id_product_attribute' => (int) $line['id_product_attribute'],
                'id_address_delivery' => $address,
                'in_cart' => 1,
            ],
            'id_customization=' . (int) $line['id_customization']
            . ' AND id_cart=' . (int) $line['id_cart']
        ) && $ok;

        return $ok;
    }

    public function replaceCustomizationId(array $line, int $newCustomizationId): bool
    {
        $oldCustomizationId = (int) $line['id_customization'];
        $ok = \Db::getInstance()->update(
            'cart_product',
            ['id_customization' => $newCustomizationId],
            'id_cart=' . (int) $line['id_cart']
            . ' AND id_product=' . (int) $line['id_product']
            . ' AND id_product_attribute=' . (int) $line['id_product_attribute']
            . ' AND id_customization=' . $oldCustomizationId
        );
        if ($oldCustomizationId > 0) {
            \Db::getInstance()->delete('customized_data', 'id_customization=' . $oldCustomizationId);
            \Db::getInstance()->delete('customization', 'id_customization=' . $oldCustomizationId);
        }

        return $ok;
    }

    public function replaceProductAttribute(array $line, int $newProductAttributeId): bool
    {
        $oldProductAttributeId = (int) $line['id_product_attribute'];
        $newProductAttributeId = max(0, $newProductAttributeId);
        if ($oldProductAttributeId === $newProductAttributeId) {
            return true;
        }

        $db = \Db::getInstance();
        $ok = $db->update(
            'cart_product',
            ['id_product_attribute' => $newProductAttributeId],
            'id_cart=' . (int) $line['id_cart']
            . ' AND id_product=' . (int) $line['id_product']
            . ' AND id_product_attribute=' . $oldProductAttributeId
            . ' AND id_customization=' . (int) $line['id_customization']
        );
        $ok = $db->update(
            'customization',
            ['id_product_attribute' => $newProductAttributeId],
            'id_customization=' . (int) $line['id_customization']
            . ' AND id_cart=' . (int) $line['id_cart']
        ) && $ok;

        return $ok;
    }

    public function purgeNativeRows(array $customizationIds): bool
    {
        $customizationIds = array_values(array_unique(array_filter(array_map('intval', $customizationIds))));
        if ($customizationIds === []) {
            return true;
        }

        $in = implode(',', $customizationIds);
        $db = \Db::getInstance();
        $ok = $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'cart_product` WHERE id_customization IN (' . $in . ')');
        $ok = $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'customized_data` WHERE id_customization IN (' . $in . ')') && $ok;
        $ok = $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'customization` WHERE id_customization IN (' . $in . ')') && $ok;

        return $ok;
    }

    private function nativeLineExists(array $line): bool
    {
        return (bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'cart_product`'
            . ' WHERE id_cart=' . (int) $line['id_cart']
            . ' AND id_product=' . (int) $line['id_product']
            . ' AND id_product_attribute=' . (int) $line['id_product_attribute']
            . ' AND id_customization=' . (int) $line['id_customization']
        );
    }
}
