<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Presentation\SampleNameFormatter;
use NaranjaCreativos\FabricSamples\Repository\CartSampleRepository;
use NaranjaCreativos\FabricSamples\Repository\OrderSampleRepository;
use NaranjaCreativos\FabricSamples\Repository\SampleProductRepository;

final class OrderSampleService
{
    public function __construct(
        private CartSampleRepository $cartRepository,
        private OrderSampleRepository $orderRepository,
        private SampleProductRepository $productRepository,
        private SampleNameFormatter $nameFormatter,
        private ImageSnapshotService $imageSnapshotService,
        private \Context $context
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function synchronize(\Order $order, \Cart $cart): array
    {
        if (!\Validate::isLoadedObject($order) || !\Validate::isLoadedObject($cart)) {
            return [];
        }

        $this->bindAllNativeOrderDetails($order, $cart);

        foreach ($this->cartRepository->findByCart((int) $cart->id) as $row) {
            $idCustomization = (int) ($row['id_customization'] ?? 0);
            if ($idCustomization <= 0) {
                continue;
            }

            $existing = $this->orderRepository->findByOrderCustomization((int) $order->id, $idCustomization);
            $detail = $this->findOrderDetail($order, $row, $existing);
            $idOrderDetail = (int) ($detail['id_order_detail'] ?? $existing['id_order_detail'] ?? 0);
            $baseName = (string) ($detail['product_name'] ?? $existing['product_name'] ?? $row['product_name'] ?? '');
            $formattedName = $this->nameFormatter->format($baseName);
            $quantity = max(1, (int) ($detail['product_quantity'] ?? $existing['quantity'] ?? $row['quantity'] ?? 1));
            $unitExcl = (float) ($detail['unit_price_tax_excl'] ?? $existing['unit_price_tax_excl'] ?? $row['unit_price_tax_excl'] ?? 0);
            $unitIncl = (float) ($detail['unit_price_tax_incl'] ?? $existing['unit_price_tax_incl'] ?? $row['unit_price_tax_incl'] ?? 0);
            $totalExcl = (float) ($detail['total_price_tax_excl'] ?? $existing['total_price_tax_excl'] ?? ($unitExcl * $quantity));
            $totalIncl = (float) ($detail['total_price_tax_incl'] ?? $existing['total_price_tax_incl'] ?? ($unitIncl * $quantity));
            $taxRate = $unitExcl > 0 ? (($unitIncl / $unitExcl) - 1) * 100 : 0.0;

            if ($idOrderDetail > 0) {
                $this->bindOrderDetail($idOrderDetail, $formattedName, $idCustomization);
            }

            if ($existing !== []) {
                $this->orderRepository->updateById((int) $existing['id_fabricssamples_order'], [
                    'id_order_detail' => $idOrderDetail,
                    'product_name' => pSQL($formattedName),
                    'product_reference' => pSQL((string) ($detail['product_reference'] ?? $existing['product_reference'] ?? $row['product_reference'] ?? '')),
                    'quantity' => $quantity,
                    'unit_price_tax_excl' => $unitExcl,
                    'unit_price_tax_incl' => $unitIncl,
                    'tax_rate' => $taxRate,
                    'total_price_tax_excl' => $totalExcl,
                    'total_price_tax_incl' => $totalIncl,
                    'date_upd' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            $currency = new \Currency((int) $order->id_currency);
            $image = $this->imageSnapshotService->snapshot((int) $order->id, (int) $row['id_product']);
            $productUrl = $this->buildProductUrl((int) $row['id_product'], (int) $order->id_lang, (int) $order->id_shop);
            $sampleConfig = $this->productRepository->findActive((int) $row['id_product'], (int) $order->id_shop);
            $snapshot = [
                'id_product' => (int) $row['id_product'],
                'id_product_attribute' => (int) $row['id_product_attribute'],
                'id_customization' => $idCustomization,
                'product_name' => $formattedName,
                'product_reference' => (string) $row['product_reference'],
                'size_text' => (string) $row['size_text'],
                'quantity' => $quantity,
                'unit_price_tax_excl' => $unitExcl,
                'unit_price_tax_incl' => $unitIncl,
                'total_price_tax_excl' => $totalExcl,
                'total_price_tax_incl' => $totalIncl,
                'tax_rate' => $taxRate,
                'currency_iso_code' => (string) $currency->iso_code,
                'sample_configuration' => $sampleConfig,
            ];

            $inserted = $this->orderRepository->insert([
                'id_order' => (int) $order->id,
                'id_order_detail' => $idOrderDetail,
                'id_shop' => (int) $order->id_shop,
                'id_customer' => (int) $order->id_customer,
                'id_product' => (int) $row['id_product'],
                'id_product_attribute' => (int) $row['id_product_attribute'],
                'id_customization' => $idCustomization,
                'id_image' => (int) $image['id_image'],
                'id_currency' => (int) $order->id_currency,
                'id_lang' => (int) $order->id_lang,
                'product_name' => pSQL($formattedName),
                'product_reference' => pSQL((string) $row['product_reference']),
                'size_text' => pSQL((string) $row['size_text']),
                'image_snapshot' => pSQL((string) $image['path']),
                'product_url' => pSQL($productUrl, true),
                'currency_iso_code' => pSQL((string) $currency->iso_code),
                'quantity' => $quantity,
                'unit_price_tax_excl' => $unitExcl,
                'unit_price_tax_incl' => $unitIncl,
                'tax_rate' => $taxRate,
                'total_price_tax_excl' => $totalExcl,
                'total_price_tax_incl' => $totalIncl,
                'snapshot_json' => pSQL(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', true),
                'preparation_status' => 'pending',
                'notes' => '',
                'date_add' => (string) ($order->date_add ?: date('Y-m-d H:i:s')),
                'date_upd' => date('Y-m-d H:i:s'),
            ]);

        }

        $this->repairExistingHistoryRows((int) $order->id);

        return $this->orderRepository->findByOrder((int) $order->id);
    }

    /** @return list<array<string,mixed>> */
    public function synchronizeOrderById(int $idOrder): array
    {
        $order = new \Order($idOrder);
        if (!\Validate::isLoadedObject($order)) {
            return [];
        }

        // First use the live cart data when it is still available. A failure here
        // must never prevent recovery from the immutable order_detail rows.
        if ((int) $order->id_cart > 0) {
            try {
                $cart = new \Cart((int) $order->id_cart);
                if (\Validate::isLoadedObject($cart)) {
                    $this->synchronize($order, $cart);
                }
            } catch (\Throwable $exception) {
                \PrestaShopLogger::addLog(
                    sprintf('fabricssamples: no se pudo sincronizar el carrito del pedido %d: %s', $idOrder, $exception->getMessage()),
                    2,
                    null,
                    'Order',
                    $idOrder,
                    true
                );
            }
        }

        try {
            $this->recoverNamedOrderDetails($order);
        } catch (\Throwable $exception) {
            \PrestaShopLogger::addLog(
                sprintf('fabricssamples: no se pudo reconstruir el histórico del pedido %d: %s', $idOrder, $exception->getMessage()),
                3,
                null,
                'Order',
                $idOrder,
                true
            );
        }

        try {
            $this->repairExistingHistoryRows($idOrder);
        } catch (\Throwable $exception) {
            \PrestaShopLogger::addLog(
                sprintf('fabricssamples: no se pudo reparar el histórico del pedido %d: %s', $idOrder, $exception->getMessage()),
                2,
                null,
                'Order',
                $idOrder,
                true
            );
        }

        return $this->orderRepository->findByOrder($idOrder);
    }

    public function synchronizeCustomerOrders(int $idCustomer, int $idShop): int
    {
        if ($idCustomer <= 0) {
            return 0;
        }
        $orderIds = \Db::getInstance()->executeS(
            'SELECT DISTINCT o.id_order FROM `' . _DB_PREFIX_ . 'orders` o'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc ON fsc.id_cart=o.id_cart'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_order` fso ON fso.id_order=o.id_order'
            . ' WHERE o.id_customer=' . $idCustomer . ' AND o.id_shop=' . $idShop
            . ' AND (fsc.id_fabricssamples_cart IS NOT NULL OR fso.id_fabricssamples_order IS NOT NULL'
            . " OR EXISTS(SELECT 1 FROM `" . _DB_PREFIX_ . "order_detail` od WHERE od.id_order=o.id_order AND LOWER(TRIM(od.product_name)) LIKE 'muestra%'))"
            . ' ORDER BY o.id_order DESC'
        );
        $created = 0;
        foreach (is_array($orderIds) ? $orderIds : [] as $row) {
            $idOrder = (int) $row['id_order'];
            $before = $this->orderRepository->countByOrder($idOrder);
            $after = count($this->synchronizeOrderById($idOrder));
            $created += max(0, $after - $before);
        }
        return $created;
    }

    public function synchronizeAllOrders(int $idShop = 0): int
    {
        $where = $idShop > 0 ? ' AND o.id_shop=' . $idShop : '';
        $orderIds = \Db::getInstance()->executeS(
            'SELECT DISTINCT o.id_order FROM `' . _DB_PREFIX_ . 'orders` o'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc ON fsc.id_cart=o.id_cart'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_order` fso ON fso.id_order=o.id_order'
            . ' WHERE (fsc.id_fabricssamples_cart IS NOT NULL OR fso.id_fabricssamples_order IS NOT NULL'
            . " OR EXISTS(SELECT 1 FROM `" . _DB_PREFIX_ . "order_detail` od WHERE od.id_order=o.id_order AND LOWER(TRIM(od.product_name)) LIKE 'muestra%'))"
            . $where . ' ORDER BY o.id_order DESC'
        );
        $created = 0;
        foreach (is_array($orderIds) ? $orderIds : [] as $row) {
            $idOrder = (int) $row['id_order'];
            $before = $this->orderRepository->countByOrder($idOrder);
            $after = count($this->synchronizeOrderById($idOrder));
            $created += max(0, $after - $before);
        }
        return $created;
    }

    /**
     * Performs a resilient historical rebuild without deleting or modifying native order lines.
     *
     * @return array{attempted:int,repaired:int,remaining:int,dropped_indexes:list<string>,failures:list<array<string,mixed>>}
     */
    public function forceSynchronizeMissing(int $idShop = 0): array
    {
        $droppedIndexes = $this->orderRepository->dropObsoleteUniqueHistoryIndexes();

        // First run the complete recovery path so images, URLs and legacy operational data
        // are preserved whenever possible.
        $this->synchronizeAllOrders($idShop);

        $attempted = 0;
        $repaired = 0;
        $failures = [];
        $guard = 0;
        do {
            $rows = $this->orderRepository->findMissingNativeHistory($idShop, 1000);
            if ($rows === []) {
                break;
            }
            $progress = 0;
            foreach ($rows as $row) {
                $idOrderDetail = (int) ($row['id_order_detail'] ?? 0);
                $idOrder = (int) ($row['id_order'] ?? 0);
                if ($idOrderDetail <= 0) {
                    continue;
                }
                ++$attempted;

                $legacy = $this->orderRepository->findLegacyCandidateForOrderDetail($idOrder, $row);
                $ok = false;
                if ($legacy !== []) {
                    $ok = $this->orderRepository->updateLegacyFromNativeDetail(
                        (int) ($legacy['id_fabricssamples_order'] ?? 0),
                        $idOrderDetail
                    );
                }
                if (!$ok) {
                    $ok = $this->orderRepository->insertMinimalFromNativeDetail($idOrderDetail);
                }

                if ($ok && $this->orderRepository->existsByOrderDetail($idOrderDetail)) {
                    ++$repaired;
                    ++$progress;
                    continue;
                }

                $failures[] = [
                    'id_order' => $idOrder,
                    'id_order_detail' => $idOrderDetail,
                    'product_name' => (string) ($row['product_name'] ?? ''),
                    'classification' => (string) ($row['classification'] ?? ''),
                    'error' => (string) \Db::getInstance()->getMsgError(),
                ];
            }
            ++$guard;
        } while ($progress > 0 && $guard < 10);

        return [
            'attempted' => $attempted,
            'repaired' => $repaired,
            'remaining' => $this->orderRepository->countMissingNativeHistory($idShop),
            'dropped_indexes' => $droppedIndexes,
            'failures' => array_slice($failures, 0, 100),
        ];
    }

    public function repairExistingHistoryRows(int $idOrder): int
    {
        $repaired = 0;
        foreach ($this->orderRepository->findByOrder($idOrder) as $history) {
            $formattedName = $this->nameFormatter->format((string) ($history['product_name'] ?? ''));
            $idOrderDetail = (int) ($history['id_order_detail'] ?? 0);
            if ($idOrderDetail <= 0) {
                $order = new \Order($idOrder);
                if (\Validate::isLoadedObject($order)) {
                    $detail = $this->findOrderDetail($order, $history, $history);
                    $idOrderDetail = (int) ($detail['id_order_detail'] ?? 0);
                }
            }
            if ($idOrderDetail > 0) {
                $this->bindOrderDetail($idOrderDetail, $formattedName, (int) ($history['id_customization'] ?? 0));
            }
            $this->orderRepository->updateById((int) $history['id_fabricssamples_order'], [
                'id_order_detail' => $idOrderDetail,
                'product_name' => pSQL($formattedName),
                'date_upd' => date('Y-m-d H:i:s'),
            ]);
            ++$repaired;
        }
        return $repaired;
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $existing */
    private function findOrderDetail(\Order $order, array $row, array $existing = []): array
    {
        if (!empty($existing['id_order_detail'])) {
            $detail = \Db::getInstance()->getRow(
                'SELECT od.id_order_detail, od.product_name, od.product_reference, od.product_quantity,'
                . ' od.unit_price_tax_excl, od.unit_price_tax_incl, od.total_price_tax_excl, od.total_price_tax_incl, od.id_customization'
                . ' FROM `' . _DB_PREFIX_ . 'order_detail` od'
                . ' WHERE od.id_order_detail=' . (int) $existing['id_order_detail']
                . ' AND od.id_order=' . (int) $order->id
            );
            if (is_array($detail) && !empty($detail['id_order_detail'])) {
                return $detail;
            }
        }

        $baseSelect = 'SELECT od.id_order_detail, od.product_name, od.product_reference, od.product_quantity,'
            . ' od.unit_price_tax_excl, od.unit_price_tax_incl, od.total_price_tax_excl, od.total_price_tax_incl, od.id_customization'
            . ' FROM `' . _DB_PREFIX_ . 'order_detail` od'
            . ' WHERE od.id_order=' . (int) $order->id
            . ' AND od.product_id=' . (int) ($row['id_product'] ?? 0)
            . ' AND od.product_attribute_id=' . (int) ($row['id_product_attribute'] ?? 0);

        $idCustomization = (int) ($row['id_customization'] ?? 0);
        if ($idCustomization > 0) {
            $detail = \Db::getInstance()->getRow(
                $baseSelect . ' AND od.id_customization=' . $idCustomization
            );
            if (is_array($detail) && !empty($detail['id_order_detail'])) {
                return $detail;
            }
        }

        $samplePrice = (float) ($row['unit_price_tax_incl'] ?? 0);
        $detail = \Db::getInstance()->getRow(
            $baseSelect
            . ' AND NOT EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' WHERE fso.id_order_detail=od.id_order_detail'
            . (!empty($existing['id_fabricssamples_order']) ? ' AND fso.id_fabricssamples_order<>' . (int) $existing['id_fabricssamples_order'] : '')
            . ')'
            . ' ORDER BY ABS(od.unit_price_tax_incl-' . (float) $samplePrice . ') ASC, od.id_order_detail ASC'
        );

        return is_array($detail) ? $detail : [];
    }

    public function bindNativeOrderDetail(\OrderDetail $detail): bool
    {
        if (!\Validate::isLoadedObject($detail) || (int) $detail->id_order <= 0) {
            return false;
        }

        $order = new \Order((int) $detail->id_order);
        if (!\Validate::isLoadedObject($order) || (int) $order->id_cart <= 0) {
            return false;
        }

        $used = [];
        foreach ($this->orderRepository->findByOrder((int) $order->id) as $history) {
            $idCustomization = (int) ($history['id_customization'] ?? 0);
            if ($idCustomization > 0 && (int) ($history['id_order_detail'] ?? 0) !== (int) $detail->id) {
                $used[$idCustomization] = true;
            }
        }

        $row = $this->matchCartSample(
            $this->cartRepository->findByCart((int) $order->id_cart),
            (int) $detail->product_id,
            (int) $detail->product_attribute_id,
            (int) $detail->id_customization,
            (float) $detail->unit_price_tax_excl,
            $used
        );
        if ($row === []) {
            return false;
        }

        $formattedName = $this->nameFormatter->format((string) ($row['product_name'] ?? $detail->product_name ?? ''));
        $idCustomization = (int) ($row['id_customization'] ?? 0);
        $this->bindOrderDetail((int) $detail->id, $formattedName, $idCustomization);
        $detail->product_name = $formattedName;
        if ($idCustomization > 0) {
            $detail->id_customization = $idCustomization;
        }

        // Persist the immutable history immediately while the cart sample row still exists.
        // Some PrestaShop/payment flows clean cart_product before actionValidateOrder finishes.
        $historyPersisted = $this->persistDetailHistory(
            $order,
            $detail,
            $row,
            $formattedName,
            $idCustomization
        );
        if (!$historyPersisted) {
            \PrestaShopLogger::addLog(
                sprintf(
                    'fabricssamples: no se pudo persistir el histórico de la muestra para order_detail %d: %s',
                    (int) $detail->id,
                    (string) \Db::getInstance()->getMsgError()
                ),
                3,
                null,
                'Order',
                (int) $order->id,
                true
            );
        }

        return true;
    }

    /** @param array<string,mixed> $row */
    private function persistDetailHistory(
        \Order $order,
        \OrderDetail $detail,
        array $row,
        string $formattedName,
        int $idCustomization
    ): bool {
        $existing = $idCustomization > 0
            ? $this->orderRepository->findByOrderCustomization((int) $order->id, $idCustomization)
            : [];

        $quantity = max(1, (int) ($detail->product_quantity ?? $row['quantity'] ?? 1));
        $unitExcl = (float) ($detail->unit_price_tax_excl ?? $row['unit_price_tax_excl'] ?? 0);
        $unitIncl = (float) ($detail->unit_price_tax_incl ?? $row['unit_price_tax_incl'] ?? 0);
        $totalExcl = (float) ($detail->total_price_tax_excl ?? ($unitExcl * $quantity));
        $totalIncl = (float) ($detail->total_price_tax_incl ?? ($unitIncl * $quantity));
        $taxRate = $unitExcl > 0 ? (($unitIncl / $unitExcl) - 1) * 100 : 0.0;
        $reference = (string) ($detail->product_reference ?? $row['product_reference'] ?? '');

        if ($existing !== []) {
            return $this->orderRepository->updateById((int) $existing['id_fabricssamples_order'], [
                'id_order_detail' => (int) $detail->id,
                'id_shop' => (int) $order->id_shop,
                'id_customer' => (int) $order->id_customer,
                'product_name' => pSQL($formattedName),
                'product_reference' => pSQL($reference),
                'quantity' => $quantity,
                'unit_price_tax_excl' => $unitExcl,
                'unit_price_tax_incl' => $unitIncl,
                'tax_rate' => $taxRate,
                'total_price_tax_excl' => $totalExcl,
                'total_price_tax_incl' => $totalIncl,
                'date_upd' => date('Y-m-d H:i:s'),
            ]);
        }

        $currency = new \Currency((int) $order->id_currency);
        $image = $this->imageSnapshotService->snapshot((int) $order->id, (int) $row['id_product']);
        $productUrl = $this->buildProductUrl((int) $row['id_product'], (int) $order->id_lang, (int) $order->id_shop);
        $sampleConfig = $this->productRepository->findActive((int) $row['id_product'], (int) $order->id_shop);
        $snapshot = [
            'id_product' => (int) $row['id_product'],
            'id_product_attribute' => (int) ($row['id_product_attribute'] ?? 0),
            'id_customization' => $idCustomization,
            'product_name' => $formattedName,
            'product_reference' => $reference,
            'size_text' => (string) ($row['size_text'] ?? ''),
            'quantity' => $quantity,
            'unit_price_tax_excl' => $unitExcl,
            'unit_price_tax_incl' => $unitIncl,
            'total_price_tax_excl' => $totalExcl,
            'total_price_tax_incl' => $totalIncl,
            'tax_rate' => $taxRate,
            'currency_iso_code' => (string) $currency->iso_code,
            'sample_configuration' => $sampleConfig,
        ];

        $inserted = $this->orderRepository->insert([
            'id_order' => (int) $order->id,
            'id_order_detail' => (int) $detail->id,
            'id_shop' => (int) $order->id_shop,
            'id_customer' => (int) $order->id_customer,
            'id_product' => (int) $row['id_product'],
            'id_product_attribute' => (int) ($row['id_product_attribute'] ?? 0),
            'id_customization' => $idCustomization,
            'id_image' => (int) ($image['id_image'] ?? 0),
            'id_currency' => (int) $order->id_currency,
            'id_lang' => (int) $order->id_lang,
            'product_name' => pSQL($formattedName),
            'product_reference' => pSQL($reference),
            'size_text' => pSQL((string) ($row['size_text'] ?? '')),
            'image_snapshot' => pSQL((string) ($image['path'] ?? '')),
            'product_url' => pSQL($productUrl, true),
            'currency_iso_code' => pSQL((string) $currency->iso_code),
            'quantity' => $quantity,
            'unit_price_tax_excl' => $unitExcl,
            'unit_price_tax_incl' => $unitIncl,
            'tax_rate' => $taxRate,
            'total_price_tax_excl' => $totalExcl,
            'total_price_tax_incl' => $totalIncl,
            'snapshot_json' => pSQL(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', true),
            'preparation_status' => 'pending',
            'notes' => '',
            'date_add' => (string) ($order->date_add ?: date('Y-m-d H:i:s')),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
        if ($inserted) {
            return true;
        }

        // Keep stock accounting operational even if the rich history insert is rejected
        // by a legacy index/schema. The minimal recovery uses the native order_detail.
        if ($this->orderRepository->insertMinimalFromNativeDetail((int) $detail->id)) {
            return true;
        }

        return $this->orderRepository->existsByOrderDetail((int) $detail->id);
    }

    private function recoverNamedOrderDetails(\Order $order): int
    {
        if (!\Validate::isLoadedObject($order)) {
            return 0;
        }

        $details = \Db::getInstance()->executeS(
            'SELECT od.* FROM `' . _DB_PREFIX_ . 'order_detail` od'
            . ' WHERE od.id_order=' . (int) $order->id
            . " AND LOWER(TRIM(od.product_name)) LIKE 'muestra%'"
            . ' ORDER BY od.id_order_detail ASC'
        );
        if (!is_array($details) || $details === []) {
            return 0;
        }

        $created = 0;
        foreach ($details as $detailRow) {
            $idOrderDetail = (int) ($detailRow['id_order_detail'] ?? 0);
            if ($idOrderDetail <= 0) {
                continue;
            }

            $existing = $this->orderRepository->findByOrderDetail($idOrderDetail);
            if ($existing !== []) {
                // Keep the customer/shop ownership synchronized with the native order.
                $this->orderRepository->updateById((int) $existing['id_fabricssamples_order'], [
                    'id_order' => (int) $order->id,
                    'id_shop' => (int) $order->id_shop,
                    'id_customer' => (int) $order->id_customer,
                    'date_upd' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            try {
                $idCustomization = (int) ($detailRow['id_customization'] ?? 0);
                $quantity = max(1, (int) ($detailRow['product_quantity'] ?? 1));
                $unitExcl = (float) ($detailRow['unit_price_tax_excl'] ?? 0);
                $unitIncl = (float) ($detailRow['unit_price_tax_incl'] ?? 0);
                $totalExcl = (float) ($detailRow['total_price_tax_excl'] ?? ($unitExcl * $quantity));
                $totalIncl = (float) ($detailRow['total_price_tax_incl'] ?? ($unitIncl * $quantity));
                $taxRate = $unitExcl > 0 ? (($unitIncl / $unitExcl) - 1) * 100 : 0.0;
                $idProduct = (int) ($detailRow['product_id'] ?? 0);
                $idAttribute = (int) ($detailRow['product_attribute_id'] ?? 0);
                $formattedName = $this->nameFormatter->format((string) ($detailRow['product_name'] ?? ''));

                $sizeText = '';
                try {
                    $config = $this->productRepository->findActive($idProduct, (int) $order->id_shop);
                    $sizeText = (string) ($config['size_text'] ?? '');
                } catch (\Throwable) {
                    $config = [];
                }

                $image = ['id_image' => 0, 'path' => ''];
                try {
                    $image = $this->imageSnapshotService->snapshot((int) $order->id, $idProduct);
                } catch (\Throwable $exception) {
                    \PrestaShopLogger::addLog(
                        sprintf('fabricssamples: no se pudo copiar la imagen del producto %d: %s', $idProduct, $exception->getMessage()),
                        1
                    );
                }

                $productUrl = '';
                try {
                    $productUrl = $this->buildProductUrl($idProduct, (int) $order->id_lang, (int) $order->id_shop);
                } catch (\Throwable) {
                    $productUrl = '';
                }

                $currencyIso = '';
                try {
                    $currency = new \Currency((int) $order->id_currency);
                    $currencyIso = \Validate::isLoadedObject($currency) ? (string) $currency->iso_code : '';
                } catch (\Throwable) {
                    $currencyIso = '';
                }

                $snapshot = [
                    'recovered_from_order_detail' => true,
                    'id_product' => $idProduct,
                    'id_product_attribute' => $idAttribute,
                    'id_customization' => $idCustomization,
                    'product_name' => $formattedName,
                    'product_reference' => (string) ($detailRow['product_reference'] ?? ''),
                    'size_text' => $sizeText,
                    'quantity' => $quantity,
                    'unit_price_tax_excl' => $unitExcl,
                    'unit_price_tax_incl' => $unitIncl,
                    'total_price_tax_excl' => $totalExcl,
                    'total_price_tax_incl' => $totalIncl,
                    'tax_rate' => $taxRate,
                ];

                $historyData = [
                    'id_order' => (int) $order->id,
                    'id_order_detail' => $idOrderDetail,
                    'id_shop' => (int) $order->id_shop,
                    'id_customer' => (int) $order->id_customer,
                    'id_product' => $idProduct,
                    'id_product_attribute' => $idAttribute,
                    'id_customization' => $idCustomization,
                    'id_image' => (int) ($image['id_image'] ?? 0),
                    'id_currency' => (int) $order->id_currency,
                    'id_lang' => (int) $order->id_lang,
                    'product_name' => pSQL($formattedName),
                    'product_reference' => pSQL((string) ($detailRow['product_reference'] ?? '')),
                    'size_text' => pSQL($sizeText),
                    'image_snapshot' => pSQL((string) ($image['path'] ?? '')),
                    'product_url' => pSQL($productUrl, true),
                    'currency_iso_code' => pSQL($currencyIso),
                    'quantity' => $quantity,
                    'unit_price_tax_excl' => $unitExcl,
                    'unit_price_tax_incl' => $unitIncl,
                    'tax_rate' => $taxRate,
                    'total_price_tax_excl' => $totalExcl,
                    'total_price_tax_incl' => $totalIncl,
                    'snapshot_json' => pSQL(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', true),
                    'preparation_status' => 'pending',
                    'notes' => '',
                    'date_add' => (string) ($order->date_add ?: date('Y-m-d H:i:s')),
                    'date_upd' => date('Y-m-d H:i:s'),
                ];

                $legacy = $this->orderRepository->findLegacyCandidateForOrderDetail(
                    (int) $order->id,
                    $detailRow
                );
                if ($legacy !== []) {
                    // Preserve operational fields already edited in the back office while
                    // linking the legacy row to its immutable native order detail.
                    foreach (['id_image', 'image_snapshot', 'product_url', 'size_text', 'currency_iso_code', 'preparation_status', 'notes', 'date_add'] as $field) {
                        if (isset($legacy[$field]) && $legacy[$field] !== '' && $legacy[$field] !== '0') {
                            $historyData[$field] = $legacy[$field];
                        }
                    }
                    $updated = $this->orderRepository->updateById(
                        (int) ($legacy['id_fabricssamples_order'] ?? 0),
                        $historyData
                    );
                    if ($updated) {
                        ++$created;
                        continue;
                    }

                    \PrestaShopLogger::addLog(
                        sprintf('fabricssamples: no se pudo enlazar el histórico heredado con order_detail %d: %s', $idOrderDetail, \Db::getInstance()->getMsgError()),
                        3,
                        null,
                        'OrderDetail',
                        $idOrderDetail,
                        true
                    );
                    continue;
                }

                $inserted = $this->orderRepository->insert($historyData);

                if ($inserted) {
                    ++$created;
                } else {
                    \PrestaShopLogger::addLog(
                        sprintf('fabricssamples: no se pudo insertar el histórico para order_detail %d: %s', $idOrderDetail, \Db::getInstance()->getMsgError()),
                        3,
                        null,
                        'OrderDetail',
                        $idOrderDetail,
                        true
                    );
                }
            } catch (\Throwable $exception) {
                // One malformed row must not prevent the other samples from appearing.
                \PrestaShopLogger::addLog(
                    sprintf('fabricssamples: error recuperando order_detail %d: %s', $idOrderDetail, $exception->getMessage()),
                    3,
                    null,
                    'OrderDetail',
                    $idOrderDetail,
                    true
                );
            }
        }

        return $created;
    }

    private function bindAllNativeOrderDetails(\Order $order, \Cart $cart): int
    {
        $samples = $this->cartRepository->findByCart((int) $cart->id);
        if ($samples === []) {
            return 0;
        }

        $details = \Db::getInstance()->executeS(
            'SELECT id_order_detail, product_id, product_attribute_id, id_customization, product_name,'
            . ' unit_price_tax_excl FROM `' . _DB_PREFIX_ . 'order_detail`'
            . ' WHERE id_order=' . (int) $order->id
            . ' ORDER BY id_order_detail ASC'
        );
        if (!is_array($details) || $details === []) {
            return 0;
        }

        $used = [];
        foreach ($this->orderRepository->findByOrder((int) $order->id) as $history) {
            $idCustomization = (int) ($history['id_customization'] ?? 0);
            if ($idCustomization > 0) {
                $used[$idCustomization] = true;
            }
        }

        $bound = 0;
        foreach ($details as $detail) {
            $row = $this->matchCartSample(
                $samples,
                (int) ($detail['product_id'] ?? 0),
                (int) ($detail['product_attribute_id'] ?? 0),
                (int) ($detail['id_customization'] ?? 0),
                (float) ($detail['unit_price_tax_excl'] ?? 0),
                $used
            );
            if ($row === []) {
                continue;
            }

            $idCustomization = (int) ($row['id_customization'] ?? 0);
            $formattedName = $this->nameFormatter->format((string) ($row['product_name'] ?? $detail['product_name'] ?? ''));
            $this->bindOrderDetail((int) $detail['id_order_detail'], $formattedName, $idCustomization);
            if ($idCustomization > 0) {
                $used[$idCustomization] = true;
            }
            ++$bound;
        }

        return $bound;
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @param array<int,bool> $used
     * @return array<string,mixed>
     */
    private function matchCartSample(
        array $samples,
        int $idProduct,
        int $idProductAttribute,
        int $idCustomization,
        float $unitPriceTaxExcl,
        array $used = []
    ): array {
        $candidates = [];
        foreach ($samples as $sample) {
            if ((int) ($sample['id_product'] ?? 0) !== $idProduct
                || (int) ($sample['id_product_attribute'] ?? 0) !== $idProductAttribute) {
                continue;
            }
            $sampleCustomization = (int) ($sample['id_customization'] ?? 0);
            if ($idCustomization > 0 && $sampleCustomization === $idCustomization) {
                return $sample;
            }
            if ($sampleCustomization > 0 && empty($used[$sampleCustomization])) {
                $candidates[] = $sample;
            }
        }

        $best = [];
        $bestDifference = INF;
        foreach ($candidates as $candidate) {
            $candidatePrice = (float) ($candidate['unit_price_tax_excl'] ?? 0);
            $difference = abs($candidatePrice - $unitPriceTaxExcl);
            if ($difference < $bestDifference) {
                $bestDifference = $difference;
                $best = $candidate;
            }
        }

        $tolerance = max(0.02, abs($unitPriceTaxExcl) * 0.002);
        return $best !== [] && $bestDifference <= $tolerance ? $best : [];
    }

    private function bindOrderDetail(int $idOrderDetail, string $formattedName, int $idCustomization = 0): void
    {
        if ($idOrderDetail <= 0 || $formattedName === '') {
            return;
        }

        $data = ['product_name' => pSQL($formattedName)];
        if ($idCustomization > 0) {
            $data['id_customization'] = $idCustomization;
        }
        \Db::getInstance()->update('order_detail', $data, 'id_order_detail=' . $idOrderDetail);
    }

    private function buildProductUrl(int $idProduct, int $idLang, int $idShop): string
    {
        try {
            $product = new \Product($idProduct, false, $idLang, $idShop);
            return \Validate::isLoadedObject($product) ? $this->context->link->getProductLink($product) : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
