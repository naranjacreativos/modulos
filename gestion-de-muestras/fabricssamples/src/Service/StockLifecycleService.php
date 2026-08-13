<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Domain\StockReconciliationPolicy;
use NaranjaCreativos\FabricSamples\Repository\OrderSampleRepository;
use NaranjaCreativos\FabricSamples\Repository\SampleProductRepository;
use NaranjaCreativos\FabricSamples\Repository\StockMovementRepository;

final class StockLifecycleService
{
    public function __construct(
        private ModuleConfiguration $configuration,
        private OrderSampleRepository $orderRepository,
        private SampleProductRepository $productRepository,
        private StockMovementRepository $movementRepository,
        private StockReconciliationPolicy $policy
    ) {
    }

    public function reconcileOrderById(int $idOrder, ?\OrderState $state = null, string $source = 'manual'): int
    {
        if ($idOrder <= 0) {
            return 0;
        }
        $order = new \Order($idOrder);
        if (!\Validate::isLoadedObject($order)) {
            return 0;
        }

        return $this->reconcileOrder($order, $state, $source);
    }

    public function reconcileOrder(\Order $order, ?\OrderState $state = null, string $source = 'order'): int
    {
        if (!\Validate::isLoadedObject($order)) {
            return 0;
        }

        // During PaymentModule::validateOrder, OrderDetail and initial order-state hooks
        // can fire before the checkout has completed all native stock work. Never record
        // compensation from those early callbacks, otherwise a later native update can
        // overwrite the +quantity while the compensation ledger already says it happened.
        // actionValidateOrder is the authoritative initial reconciliation point.
        if ($source === 'actionObjectOrderDetailAddAfter') {
            return 0;
        }
        if (in_array($source, [
            'actionOrderStatusUpdate',
            'actionOrderStatusPostUpdate',
            'actionObjectOrderHistoryAddAfter',
        ], true)) {
            $contextCart = \Context::getContext()->cart ?? null;
            if ($contextCart instanceof \Cart
                && \Validate::isLoadedObject($contextCart)
                && (int) $contextCart->id > 0
                && (int) $order->id_cart === (int) $contextCart->id) {
                return 0;
            }
        }

        $terminal = $this->isTerminalOrder($order, $state);
        $nativeStockRestoredByStatus = $this->isNativeStockRestoredByStatus($order, $state);
        $adjusted = 0;
        foreach ($this->orderRepository->findForCouponByOrder((int) $order->id) as $history) {
            $idHistory = (int) ($history['id_fabricssamples_order'] ?? 0);
            $idOrderDetail = (int) ($history['id_order_detail'] ?? 0);
            $idProduct = (int) ($history['id_product'] ?? 0);
            $idProductAttribute = max(0, (int) ($history['id_product_attribute'] ?? 0));
            $idShop = (int) ($history['id_shop'] ?? $order->id_shop);
            $ordered = max(0, (int) ($history['quantity'] ?? 0));

            // The native order detail is the source of truth for the exact stock target.
            // PrestaShop has already decremented this exact product/combination before
            // actionObjectOrderDetailAddAfter fires. Historical sample data may contain
            // an older/default attribute, so using it here can compensate the wrong row.
            $nativeDetail = $this->nativeOrderDetailStockTarget($idOrderDetail, $order);
            $nativeProduct = (int) ($nativeDetail['id_product'] ?? $idProduct);
            $nativeProductAttribute = max(0, (int) ($nativeDetail['id_product_attribute'] ?? $idProductAttribute));
            $nativeShop = (int) ($nativeDetail['id_shop'] ?? $idShop);
            $nativeOrdered = max(0, (int) ($nativeDetail['quantity'] ?? $ordered));

            if ($idProduct <= 0 || $idShop <= 0 || $nativeProduct <= 0 || $nativeShop <= 0) {
                continue;
            }

            $detailCounters = $this->orderDetailCounters($idOrderDetail);

            // A sample is still a native customized order line, so PrestaShop reduces
            // StockAvailable during OrderDetail creation. Compensate the exact native
            // line immediately and keep that compensation separate from sample stock.
            $nativeTarget = $this->policy->targetNativeCompensationQuantity(
                $nativeOrdered,
                $detailCounters['reinjected'],
                $nativeStockRestoredByStatus
            );
            if ($this->reconcileNativeProductStock(
                $nativeProduct,
                $nativeProductAttribute,
                $nativeShop,
                (int) $order->id,
                $idOrderDetail,
                $idHistory,
                $nativeTarget,
                $source
            )) {
                ++$adjusted;
            }

            // Independent sample stock is keyed by the native order_detail first.
            // The richer module history may be persisted later; stock must not wait for it.
            if ($idOrderDetail <= 0) {
                continue;
            }

            $config = $this->productRepository->findAny($nativeProduct, $nativeShop);
            if ($config === [] || (string) ($config['stock_mode'] ?? '') !== 'independent') {
                continue;
            }

            $targetConsumed = $this->policy->targetConsumedQuantity(
                $nativeOrdered,
                $detailCounters['refunded'],
                $detailCounters['returned'],
                $terminal
            );
            $desiredNet = $this->policy->desiredNetDelta($targetConsumed);
            $currentNet = $idHistory > 0
                ? $this->movementRepository->netDeltaForHistory($idHistory)
                : $this->movementRepository->netDeltaForOrderDetail($idOrderDetail);
            $delta = $this->policy->adjustment($currentNet, $desiredNet);
            if ($delta === 0) {
                continue;
            }

            $reference = sprintf(
                'reconcile:%s:%d:%d:%d:%s',
                $idHistory > 0 ? 'history-' . $idHistory : 'detail-' . $idOrderDetail,
                $desiredNet,
                $detailCounters['refunded'],
                $detailCounters['returned'],
                substr(sha1($source), 0, 12)
            );
            $type = $delta < 0 ? 'order_consumption' : ($terminal ? 'order_release' : 'refund_restore');
            if ($this->applyAtomicAdjustment(
                $nativeProduct,
                $nativeShop,
                (int) $order->id,
                $idOrderDetail,
                $idHistory,
                $delta,
                $type,
                $reference,
                $source
            )) {
                ++$adjusted;
            }
        }

        return $adjusted;
    }

    public function releaseOrder(int $idOrder, string $source = 'order_deleted'): int
    {
        $adjusted = 0;
        foreach ($this->orderRepository->findByOrder($idOrder) as $history) {
            $idHistory = (int) ($history['id_fabricssamples_order'] ?? 0);
            if ($idHistory <= 0) {
                continue;
            }
            $currentNet = $this->movementRepository->netDeltaForHistory($idHistory);
            if ($currentNet >= 0) {
                continue;
            }
            $idProduct = (int) ($history['id_product'] ?? 0);
            $idShop = (int) ($history['id_shop'] ?? 0);
            $config = $this->productRepository->findAny($idProduct, $idShop);
            if ($config === [] || (string) ($config['stock_mode'] ?? '') !== 'independent') {
                continue;
            }
            if ($this->applyAtomicAdjustment(
                $idProduct,
                $idShop,
                $idOrder,
                (int) ($history['id_order_detail'] ?? 0),
                $idHistory,
                -$currentNet,
                'order_deleted_release',
                'delete:' . $idHistory . ':' . abs($currentNet),
                $source
            )) {
                ++$adjusted;
            }
        }

        // Deleting a historical order does not itself represent a native stock movement.
        // Native compensation is therefore deliberately left untouched here; cancellation,
        // error and reinjection flows reconcile it before an order is removed.
        return $adjusted;
    }

    /** @param array<string,mixed> $params */
    public function handleProductCancel(array $params): int
    {
        $order = $params['order'] ?? null;
        $idOrder = $order instanceof \Order && \Validate::isLoadedObject($order)
            ? (int) $order->id
            : (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            return 0;
        }

        // actionProductCancel is fired by cancellation and refund handlers. The cumulative
        // quantities in order_detail make the reconciliation idempotent across repeated hooks.
        $source = 'actionProductCancel:' . (string) ($params['action'] ?? 'unknown');
        $adjusted = $this->reconcileOrderById($idOrder, null, $source);

        // Compatibility fallback for cores that fire the hook before persisting the cumulative
        // refunded/returned counters. The unique event fingerprint prevents duplicate restores.
        $idOrderDetail = (int) ($params['id_order_detail'] ?? 0);
        $cancelQuantity = max(0, (int) ($params['cancel_quantity'] ?? 0));
        if ($adjusted === 0 && $idOrderDetail > 0 && $cancelQuantity > 0) {
            $history = $this->orderRepository->findByOrderDetail($idOrderDetail);
            $idHistory = (int) ($history['id_fabricssamples_order'] ?? 0);
            if ($idHistory > 0) {
                $currentNet = $this->movementRepository->netDeltaForHistory($idHistory);
                $restore = min($cancelQuantity, max(0, -$currentNet));
                if ($restore > 0) {
                    $counters = $this->orderDetailCounters($idOrderDetail);
                    $reference = sprintf(
                        'cancel-hook:%d:%s:%d:%d:%d',
                        $idOrderDetail,
                        substr(sha1((string) ($params['action'] ?? 'unknown')), 0, 10),
                        $counters['refunded'],
                        $counters['returned'],
                        $cancelQuantity
                    );
                    if ($this->applyAtomicAdjustment(
                        (int) ($history['id_product'] ?? 0),
                        (int) ($history['id_shop'] ?? 0),
                        $idOrder,
                        $idOrderDetail,
                        $idHistory,
                        $restore,
                        'refund_restore',
                        $reference,
                        $source
                    )) {
                        ++$adjusted;
                    }
                }
            }
        }

        return $adjusted;
    }

    private function reconcileNativeProductStock(
        int $idProduct,
        int $idProductAttribute,
        int $idShop,
        int $idOrder,
        int $idOrderDetail,
        int $idHistory,
        int $desiredCompensation,
        string $source
    ): bool {
        if ($idProduct <= 0 || $idShop <= 0 || $idOrderDetail <= 0) {
            return false;
        }

        $desiredCompensation = max(0, $desiredCompensation);
        $db = \Db::getInstance();
        $db->execute('START TRANSACTION');
        try {
            // Serialize against the native order detail itself. Native stock isolation
            // must not depend on the auxiliary fabricssamples_order row already existing.
            $lockedRows = $db->executeS(
                'SELECT id_order_detail FROM `' . _DB_PREFIX_ . 'order_detail`'
                . ' WHERE id_order_detail=' . $idOrderDetail
                . ' AND id_order=' . max(0, $idOrder) . ' FOR UPDATE',
                true,
                false
            );
            if (!is_array($lockedRows) || !isset($lockedRows[0])) {
                $db->execute('ROLLBACK');
                return false;
            }

            $currentCompensation = $this->movementRepository->nativeCompensationForOrderDetail($idOrderDetail);
            $delta = $desiredCompensation - $currentCompensation;
            if ($delta === 0) {
                $db->execute('COMMIT');
                return false;
            }

            $before = (int) \StockAvailable::getQuantityAvailableByProduct(
                $idProduct,
                $idProductAttribute,
                $idShop
            );
            \StockAvailable::updateQuantity($idProduct, $idProductAttribute, $delta, $idShop);
            $after = (int) \StockAvailable::getQuantityAvailableByProduct(
                $idProduct,
                $idProductAttribute,
                $idShop
            );
            if ($after !== $before + $delta) {
                throw new \RuntimeException(sprintf(
                    'No se pudo aislar el stock nativo del producto %d: antes %d, ajuste %d, después %d.',
                    $idProduct,
                    $before,
                    $delta,
                    $after
                ));
            }

            $reference = sprintf(
                'native-compensation:detail:%d:%d:%s',
                $idOrderDetail,
                $desiredCompensation,
                bin2hex(random_bytes(6))
            );
            if (!$this->movementRepository->insert([
                'id_product' => $idProduct,
                'id_shop' => $idShop,
                'id_order' => max(0, $idOrder),
                'id_order_detail' => max(0, $idOrderDetail),
                'id_fabricssamples_order' => max(0, $idHistory),
                'movement_type' => 'native_stock_compensation',
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'movement_reference' => pSQL($reference),
                'id_employee' => (int) (\Context::getContext()->employee->id ?? 0),
                'note' => pSQL('Aislamiento del stock del producto original: ' . $source, true),
                'date_add' => date('Y-m-d H:i:s'),
            ])) {
                throw new \RuntimeException('No se pudo registrar la compensación del stock nativo.');
            }

            $db->execute('COMMIT');
            return true;
        } catch (\Throwable $exception) {
            $db->execute('ROLLBACK');
            \PrestaShopLogger::addLog(
                sprintf('fabricssamples native stock isolation: %s', $exception->getMessage()),
                3,
                null,
                'Product',
                $idProduct,
                true
            );
            return false;
        }
    }

    private function applyAtomicAdjustment(
        int $idProduct,
        int $idShop,
        int $idOrder,
        int $idOrderDetail,
        int $idHistory,
        int $delta,
        string $type,
        string $reference,
        string $note
    ): bool {
        if ($delta === 0 || $idProduct <= 0 || $idShop <= 0 || ($idHistory <= 0 && $idOrderDetail <= 0)) {
            return false;
        }
        if ($this->movementRepository->existsByReference($reference)) {
            return false;
        }

        $db = \Db::getInstance();
        $db->execute('START TRANSACTION');
        try {
            if ($this->movementRepository->existsByReference($reference)) {
                $db->execute('COMMIT');
                return false;
            }

            $lockedRows = $db->executeS(
                'SELECT id_fabricssamples_product, sample_stock, stock_mode'
                . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
                . ' WHERE id_product=' . $idProduct . ' AND id_shop=' . $idShop
                . ' FOR UPDATE',
                true,
                false
            );
            $row = is_array($lockedRows) && isset($lockedRows[0]) ? $lockedRows[0] : [];
            if (!is_array($row) || (string) ($row['stock_mode'] ?? '') !== 'independent') {
                $db->execute('COMMIT');
                return false;
            }

            $before = (int) ($row['sample_stock'] ?? 0);
            $after = $before + $delta;
            if ($after < 0) {
                throw new \RuntimeException(sprintf(
                    'Stock insuficiente para la muestra %d: disponible %d, ajuste %d.',
                    $idProduct,
                    $before,
                    $delta
                ));
            }

            if (!$db->update(
                'fabricssamples_product',
                ['sample_stock' => $after, 'date_upd' => date('Y-m-d H:i:s')],
                'id_fabricssamples_product=' . (int) $row['id_fabricssamples_product']
            )) {
                throw new \RuntimeException('No se pudo actualizar el stock independiente de muestras.');
            }

            if (!$this->movementRepository->insert([
                'id_product' => $idProduct,
                'id_shop' => $idShop,
                'id_order' => max(0, $idOrder),
                'id_order_detail' => max(0, $idOrderDetail),
                'id_fabricssamples_order' => max(0, $idHistory),
                'movement_type' => pSQL($type),
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'movement_reference' => pSQL($reference),
                'id_employee' => (int) (\Context::getContext()->employee->id ?? 0),
                'note' => pSQL($note, true),
                'date_add' => date('Y-m-d H:i:s'),
            ])) {
                throw new \RuntimeException('No se pudo registrar el movimiento de stock.');
            }

            $db->execute('COMMIT');
            $this->logLowStock($idProduct, $idShop, $after);
            return true;
        } catch (\Throwable $exception) {
            $db->execute('ROLLBACK');
            \PrestaShopLogger::addLog(
                sprintf('fabricssamples stock: %s', $exception->getMessage()),
                3,
                null,
                'Product',
                $idProduct,
                true
            );
            return false;
        }
    }

    /** @return array{id_product:int,id_product_attribute:int,id_shop:int,quantity:int}|array{} */
    private function nativeOrderDetailStockTarget(int $idOrderDetail, \Order $order): array
    {
        if ($idOrderDetail <= 0 || !\Validate::isLoadedObject($order)) {
            return [];
        }

        $row = \Db::getInstance()->getRow(
            'SELECT product_id, product_attribute_id, product_quantity, id_shop'
            . ' FROM `' . _DB_PREFIX_ . 'order_detail`'
            . ' WHERE id_order_detail=' . $idOrderDetail
            . ' AND id_order=' . (int) $order->id
        );
        if (!is_array($row)) {
            return [];
        }

        $idProduct = (int) ($row['product_id'] ?? 0);
        $idShop = (int) ($row['id_shop'] ?? $order->id_shop);
        if ($idProduct <= 0 || $idShop <= 0) {
            return [];
        }

        return [
            'id_product' => $idProduct,
            'id_product_attribute' => max(0, (int) ($row['product_attribute_id'] ?? 0)),
            'id_shop' => $idShop,
            'quantity' => max(0, (int) ($row['product_quantity'] ?? 0)),
        ];
    }

    /** @return array{refunded:int,returned:int,reinjected:int} */
    private function orderDetailCounters(int $idOrderDetail): array
    {
        if ($idOrderDetail <= 0) {
            return ['refunded' => 0, 'returned' => 0, 'reinjected' => 0];
        }
        $row = \Db::getInstance()->getRow(
            'SELECT product_quantity_refunded, product_quantity_return, product_quantity_reinjected'
            . ' FROM `' . _DB_PREFIX_ . 'order_detail` WHERE id_order_detail=' . $idOrderDetail
        );

        return [
            'refunded' => max(0, (int) ($row['product_quantity_refunded'] ?? 0)),
            'returned' => max(0, (int) ($row['product_quantity_return'] ?? 0)),
            'reinjected' => max(0, (int) ($row['product_quantity_reinjected'] ?? 0)),
        ];
    }

    private function isTerminalOrder(\Order $order, ?\OrderState $state): bool
    {
        $idState = $this->resolvedOrderStateId($order, $state);
        $terminalIds = array_values(array_filter(array_map('intval', [
            \Configuration::get('PS_OS_CANCELED'),
            \Configuration::get('PS_OS_REFUND'),
            \Configuration::get('PS_OS_ERROR'),
        ])));

        return in_array($idState, $terminalIds, true);
    }

    private function isNativeStockRestoredByStatus(\Order $order, ?\OrderState $state): bool
    {
        $idState = $this->resolvedOrderStateId($order, $state);
        $restockingStateIds = array_values(array_filter(array_map('intval', [
            \Configuration::get('PS_OS_CANCELED'),
            \Configuration::get('PS_OS_ERROR'),
        ])));

        return in_array($idState, $restockingStateIds, true);
    }

    private function resolvedOrderStateId(\Order $order, ?\OrderState $state): int
    {
        return $state instanceof \OrderState && \Validate::isLoadedObject($state)
            ? (int) $state->id
            : (int) $order->current_state;
    }

    private function logLowStock(int $idProduct, int $idShop, int $stock): void
    {
        $threshold = max(0, (int) $this->configuration->get('LOW_STOCK_THRESHOLD'));
        if ($threshold <= 0 || $stock > $threshold) {
            return;
        }
        \PrestaShopLogger::addLog(
            sprintf('fabricssamples: stock bajo de muestras para producto %d (tienda %d): %d unidades.', $idProduct, $idShop, $stock),
            2,
            null,
            'Product',
            $idProduct,
            true
        );
    }
}
