<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Cart\Adapter\NativeCartAdapterInterface;
use NaranjaCreativos\FabricSamples\Cart\CartIntegrityReport;
use NaranjaCreativos\FabricSamples\Cart\CartInvariantActionPolicy;
use NaranjaCreativos\FabricSamples\Repository\CartSampleRepository;

final class CartIntegrityService
{
    private static bool $running = false;
    private static int $suspensionDepth = 0;

    private CartInvariantActionPolicy $policy;

    public function __construct(
        private \Module $module,
        private CartSampleRepository $repository,
        private NativeCartAdapterInterface $adapter,
        private ProductAttributeResolver $attributeResolver,
        ?CartInvariantActionPolicy $policy = null
    ) {
        $this->policy = $policy ?? new CartInvariantActionPolicy();
    }


    /**
     * Executes a module-controlled cart mutation without allowing actionCartSave
     * to repair an intentionally half-written state. The caller must run a
     * strict repair immediately after the mutation finishes.
     *
     * @template T
     * @param callable():T $operation
     * @return T
     */
    public function withoutAutomaticRepair(callable $operation)
    {
        ++self::$suspensionDepth;
        try {
            return $operation();
        } finally {
            --self::$suspensionDepth;
        }
    }

    public function repairCart(\Cart $cart, bool $strict = false): CartIntegrityReport
    {
        $report = new CartIntegrityReport();
        if (self::$running || self::$suspensionDepth > 0 || !\Validate::isLoadedObject($cart)) {
            return $report;
        }

        self::$running = true;
        try {
            foreach ($this->repository->findByCart((int) $cart->id) as $line) {
                $this->repairLine($cart, $line, $report);
            }
        } finally {
            self::$running = false;
        }

        if ($strict && $report->hasErrors()) {
            throw new \PrestaShopException(
                $this->module->l('El carrito contiene una muestra dañada. Vuelva al carrito y añádala de nuevo.')
            );
        }

        return $report;
    }

    private function repairLine(\Cart $cart, array $line, CartIntegrityReport $report): void
    {
        $rowId = (int) $line['id_fabricssamples_cart'];
        $quantity = max(1, (int) $line['quantity']);

        try {
            $state = $this->adapter->readInvariant($line);

            $initialAction = $this->policy->decide($state);
            // If both native rows are gone, PrestaShop or the theme intentionally
            // removed the line. Deleting the module row prevents the integrity
            // repair from resurrecting a product the customer removed.
            if ($initialAction === CartInvariantActionPolicy::REMOVE_MODULE_ROW) {
                $this->repository->deleteById($rowId);
                $report->addRemoval();
                return;
            }

            if ($initialAction === CartInvariantActionPolicy::RECREATE_CUSTOMIZATION) {
                $newCustomizationId = $this->adapter->createCustomization(
                    $cart,
                    (int) $line['id_product'],
                    (int) $line['id_product_attribute']
                );

                if ($state->cartProductExists && !$this->adapter->replaceCustomizationId($line, $newCustomizationId)) {
                    throw new \RuntimeException('Could not rebind missing customization.');
                }

                if (!$this->repository->updateCustomizationId($rowId, $newCustomizationId)) {
                    throw new \RuntimeException('Could not update module customization identifier.');
                }
                $line['id_customization'] = $newCustomizationId;
                $report->addRepair();
                $state = $this->adapter->readInvariant($line);
            }

            if ($this->policy->decide($state) === CartInvariantActionPolicy::RECREATE_CART_PRODUCT) {
                if (!$this->adapter->addQuantity($cart, $line, $quantity)) {
                    throw new \RuntimeException('Could not recreate native cart row.');
                }
                $report->addRepair();
                $state = $this->adapter->readInvariant($line);
            }

            $resolvedAttribute = $this->attributeResolver->resolve(
                (int) $line['id_product'],
                (int) $line['id_product_attribute'],
                (int) $line['id_shop']
            );
            if ($resolvedAttribute !== (int) $line['id_product_attribute']) {
                \Db::getInstance()->execute('START TRANSACTION');
                try {
                    if (!$this->adapter->replaceProductAttribute($line, $resolvedAttribute)) {
                        throw new \RuntimeException('Could not repair the native product combination.');
                    }
                    if (!$this->repository->updateProductAttribute($rowId, $resolvedAttribute)) {
                        throw new \RuntimeException('Could not repair the module product combination.');
                    }
                    \Db::getInstance()->execute('COMMIT');
                } catch (\Throwable $exception) {
                    \Db::getInstance()->execute('ROLLBACK');
                    throw $exception;
                }
                $line['id_product_attribute'] = $resolvedAttribute;
                $report->addRepair();
                $state = $this->adapter->readInvariant($line);
            }

            $targetQuantity = $state->canonicalQuantity();
            if ($targetQuantity <= 0) {
                $this->adapter->removeLine($cart, $line);
                $this->repository->deleteById($rowId);
                $report->addRemoval();
                return;
            }

            if ((int) $line['quantity'] !== $targetQuantity) {
                $this->repository->updateQuantity($rowId, $targetQuantity);
                $line['quantity'] = $targetQuantity;
                $report->addRepair();
            }

            if (!$this->adapter->forceExactNativeState($line, $targetQuantity)) {
                throw new \RuntimeException('Could not normalize native quantities.');
            }

            $verified = $this->adapter->readInvariant($line);
            if (!$verified->isConsistent()) {
                throw new \RuntimeException('Invariant mismatch: ' . implode(',', $verified->issues()));
            }
        } catch (\Throwable $exception) {
            $message = sprintf(
                'fabricssamples cart integrity: cart=%d row=%d customization=%d platform=%s error=%s',
                (int) $cart->id,
                $rowId,
                (int) ($line['id_customization'] ?? 0),
                $this->adapter->platformName(),
                $exception->getMessage()
            );
            $report->addError($message);
            \PrestaShopLogger::addLog($message, 3, null, 'Cart', (int) $cart->id, true);
        }
    }
}
