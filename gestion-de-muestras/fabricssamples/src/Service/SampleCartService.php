<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Cart\Adapter\NativeCartAdapterInterface;
use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Domain\LimitPolicy;
use NaranjaCreativos\FabricSamples\Domain\LimitExceptionPolicy;
use NaranjaCreativos\FabricSamples\Repository\CartSampleRepository;
use NaranjaCreativos\FabricSamples\Repository\CustomerLimitRepository;
use NaranjaCreativos\FabricSamples\Repository\LimitExceptionRepository;
use NaranjaCreativos\FabricSamples\Repository\SampleProductRepository;
use NaranjaCreativos\FabricSamples\Infrastructure\DatabaseLock;

final class SampleCartService
{
    public function __construct(
        private \Module $module,
        private \Context $context,
        private ModuleConfiguration $configuration,
        private SampleProductRepository $productRepository,
        private CartSampleRepository $cartRepository,
        private CustomerLimitRepository $customerLimitRepository,
        private LimitExceptionRepository $limitExceptionRepository,
        private SampleAvailabilityService $availabilityService,
        private SamplePricingService $pricingService,
        private LimitPolicy $limitPolicy,
        private LimitExceptionPolicy $limitExceptionPolicy,
        private NativeCartAdapterInterface $nativeCartAdapter,
        private CartIntegrityService $integrityService,
        private ProductAttributeResolver $attributeResolver,
        private DatabaseLock $databaseLock
    ) {
    }

    public function addSample(int $idProduct, int $idProductAttribute = 0, int $quantity = 1): int
    {
        $quantity = max(1, $quantity);
        $config = $this->productRepository->findActive($idProduct, (int) $this->context->shop->id);
        if ($config === [] || !$this->availabilityService->isAvailable($config, $idProduct)) {
            throw new \PrestaShopException($this->module->l('La muestra no está disponible.'));
        }

        $cart = $this->ensureCart();
        $operationLock = $this->databaseLock->acquire('cart:' . (int) $cart->id);
        try {
        $this->integrityService->repairCart($cart, true);
        $idProductAttribute = $this->attributeResolver->resolve(
            $idProduct,
            $idProductAttribute,
            (int) $this->context->shop->id
        );
        $this->validateLimits($idProduct, $quantity, $config, false);
        $existing = $this->cartRepository->findByCartProduct((int) $cart->id, $idProduct, $idProductAttribute);

        if ($existing !== []) {
            $newQuantity = (int) $existing['quantity'] + $quantity;
            $this->validateLimits($idProduct, $newQuantity, $config, true);
            $this->clearPricingCaches();
            $this->integrityService->withoutAutomaticRepair(function () use ($cart, &$existing, $quantity, $newQuantity): void {
                \Db::getInstance()->execute('START TRANSACTION');
                try {
                    $this->cartRepository->lockCart((int) $cart->id);
                    if (!$this->nativeCartAdapter->changeQuantity($cart, $existing, $quantity, 'up')) {
                        throw new \PrestaShopException($this->module->l('No se pudo actualizar la muestra en el carrito.'));
                    }
                    if (!$this->cartRepository->updateQuantity((int) $existing['id_fabricssamples_cart'], $newQuantity)) {
                        throw new \PrestaShopException($this->module->l('No se pudo guardar la nueva cantidad de la muestra.'));
                    }
                    $existing['quantity'] = $newQuantity;
                    if (!$this->nativeCartAdapter->forceExactNativeState($existing, $newQuantity)) {
                        throw new \PrestaShopException($this->module->l('No se pudo sincronizar la cantidad de la muestra.'));
                    }
                    \Db::getInstance()->execute('COMMIT');
                } catch (\Throwable $exception) {
                    \Db::getInstance()->execute('ROLLBACK');
                    throw $exception;
                }
            });
            $this->integrityService->repairCart($cart, true);
            $this->clearPricingCaches();

            return (int) $existing['id_customization'];
        }

        $idCustomization = $this->nativeCartAdapter->createCustomization($cart, $idProduct, $idProductAttribute);
        $product = new \Product($idProduct, false, (int) $this->context->language->id, (int) $this->context->shop->id);
        $priceExcl = $this->pricingService->getPrice($config, false);
        $priceIncl = $this->pricingService->getPrice($config, true);

        $this->integrityService->withoutAutomaticRepair(function () use (
            $cart,
            $idProduct,
            $idProductAttribute,
            $idCustomization,
            $product,
            $config,
            $quantity,
            $priceExcl,
            $priceIncl
        ): void {
            \Db::getInstance()->execute('START TRANSACTION');
            try {
                $inserted = $this->cartRepository->insert([
                    'id_cart' => (int) $cart->id,
                    'id_shop' => (int) $this->context->shop->id,
                    'id_product' => $idProduct,
                    'id_product_attribute' => $idProductAttribute,
                    'id_customization' => $idCustomization,
                    'product_name' => pSQL((string) $product->name),
                    'product_reference' => pSQL((string) $product->reference),
                    'size_text' => pSQL((string) ($config['size_text'] ?: $this->configuration->getString('DEFAULT_SIZE'))),
                    'quantity' => $quantity,
                    'unit_price_tax_excl' => $priceExcl,
                    'unit_price_tax_incl' => $priceIncl,
                    'weight' => (float) ($config['sample_weight'] ?: $this->configuration->getFloat('DEFAULT_WEIGHT', 0.02)),
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s'),
                ]);
                if (!$inserted) {
                    throw new \PrestaShopException($this->module->l('No se pudo guardar la muestra en el carrito.'));
                }

                $line = $this->cartRepository->findByCustomization($idCustomization, (int) $cart->id);
                if ($line === [] || !$this->nativeCartAdapter->addQuantity($cart, $line, $quantity)) {
                    throw new \PrestaShopException($this->module->l('No se pudo añadir la muestra al carrito.'));
                }
                if (!$this->nativeCartAdapter->forceExactNativeState($line, $quantity)) {
                    throw new \PrestaShopException($this->module->l('No se pudo normalizar la línea de muestra.'));
                }

                \Db::getInstance()->execute('COMMIT');
            } catch (\Throwable $exception) {
                \Db::getInstance()->execute('ROLLBACK');
                $line = [
                    'id_cart' => (int) $cart->id,
                    'id_product' => $idProduct,
                    'id_product_attribute' => $idProductAttribute,
                    'id_customization' => $idCustomization,
                ];
                $this->nativeCartAdapter->removeLine($cart, $line);
                $this->cartRepository->deleteByCustomization($idCustomization);
                throw $exception;
            }
        });

        $this->integrityService->repairCart($cart, true);
        $this->clearPricingCaches();

        return $idCustomization;
        } finally {
            $this->databaseLock->release($operationLock);
        }
    }

    public function removeSample(int $idCustomization): void
    {
        $cart = $this->ensureCart();
        $operationLock = $this->databaseLock->acquire('cart:' . (int) $cart->id);
        try {
        $this->integrityService->repairCart($cart, true);
        $line = $this->cartRepository->findByCustomization($idCustomization, (int) $cart->id);
        if ($line === []) {
            throw new \PrestaShopException($this->module->l('La muestra no pertenece al carrito actual.'));
        }

        $this->integrityService->withoutAutomaticRepair(function () use ($cart, $line): void {
            \Db::getInstance()->execute('START TRANSACTION');
            try {
                $this->cartRepository->lockCart((int) $cart->id);
                if (!$this->nativeCartAdapter->removeLine($cart, $line)) {
                    throw new \PrestaShopException($this->module->l('No se pudo eliminar la muestra del carrito.'));
                }
                if (!$this->cartRepository->deleteById((int) $line['id_fabricssamples_cart'])) {
                    throw new \PrestaShopException($this->module->l('No se pudo eliminar el registro de la muestra.'));
                }
                \Db::getInstance()->execute('COMMIT');
                $cart->update();
            } catch (\Throwable $exception) {
                \Db::getInstance()->execute('ROLLBACK');
                throw $exception;
            }
        });

        $this->clearPricingCaches();
        } finally {
            $this->databaseLock->release($operationLock);
        }
    }

    public function updateQuantity(int $idCustomization, string $direction): int
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new \PrestaShopException($this->module->l('Dirección de cantidad no válida.'));
        }

        $cart = $this->ensureCart();
        $operationLock = $this->databaseLock->acquire('cart:' . (int) $cart->id);
        try {
        $this->integrityService->repairCart($cart, true);
        $line = $this->cartRepository->findByCustomization($idCustomization, (int) $cart->id);
        if ($line === []) {
            throw new \PrestaShopException($this->module->l('La muestra no pertenece al carrito actual.'));
        }

        $currentQuantity = max(1, (int) $line['quantity']);
        if ($direction === 'down' && $currentQuantity <= 1) {
            throw new \PrestaShopException($this->module->l('La cantidad mínima de una muestra es 1. Use la papelera para eliminarla.'));
        }

        $config = $this->productRepository->findActive((int) $line['id_product'], (int) $this->context->shop->id);
        if ($config === [] || !$this->availabilityService->isAvailable($config, (int) $line['id_product'])) {
            throw new \PrestaShopException($this->module->l('La muestra ya no está disponible.'));
        }

        $newQuantity = $direction === 'up' ? $currentQuantity + 1 : $currentQuantity - 1;
        if ($direction === 'up') {
            $this->validateLimits((int) $line['id_product'], $newQuantity, $config, true);
        }

        $this->clearPricingCaches();
        $this->integrityService->withoutAutomaticRepair(function () use ($cart, &$line, $newQuantity, $direction): void {
            \Db::getInstance()->execute('START TRANSACTION');
            try {
                $this->cartRepository->lockCart((int) $cart->id);
                if (!$this->nativeCartAdapter->changeQuantity($cart, $line, 1, $direction)) {
                    throw new \PrestaShopException($this->module->l('No se pudo actualizar la cantidad de la muestra.'));
                }
                if (!$this->cartRepository->updateQuantity((int) $line['id_fabricssamples_cart'], $newQuantity)) {
                    throw new \PrestaShopException($this->module->l('No se pudo guardar la cantidad de la muestra.'));
                }
                $line['quantity'] = $newQuantity;
                if (!$this->nativeCartAdapter->forceExactNativeState($line, $newQuantity)) {
                    throw new \PrestaShopException($this->module->l('No se pudo sincronizar la cantidad de la muestra.'));
                }
                $cart->update();
                \Db::getInstance()->execute('COMMIT');
            } catch (\Throwable $exception) {
                \Db::getInstance()->execute('ROLLBACK');
                throw $exception;
            }
        });

        $this->integrityService->repairCart($cart, true);
        $this->clearPricingCaches();

        return $newQuantity;
        } finally {
            $this->databaseLock->release($operationLock);
        }
    }

    public function validateQuantity(int $idProduct, int $quantity, array $config): void
    {
        $this->validateLimits($idProduct, $quantity, $config, true);
    }

    public function validateCheckout(\Cart $cart): void
    {
        foreach ($this->cartRepository->findByCart((int) $cart->id) as $line) {
            $idProduct = (int) ($line['id_product'] ?? 0);
            $config = $this->productRepository->findActive($idProduct, (int) $cart->id_shop);
            if ($config === [] || !$this->availabilityService->isAvailable($config, $idProduct)) {
                throw new \PrestaShopException($this->module->l('Una muestra del carrito ya no está disponible.'));
            }
            $this->validateLimits($idProduct, max(1, (int) ($line['quantity'] ?? 1)), $config, true);
        }
    }

    private function ensureCart(): \Cart
    {
        if (\Validate::isLoadedObject($this->context->cart)) {
            return $this->context->cart;
        }

        $cart = new \Cart();
        $cart->id_shop_group = (int) $this->context->shop->id_shop_group;
        $cart->id_shop = (int) $this->context->shop->id;
        $cart->id_lang = (int) $this->context->language->id;
        $cart->id_currency = (int) $this->context->currency->id;
        $cart->id_customer = (int) $this->context->customer->id;
        $cart->id_guest = (int) $this->context->cookie->id_guest;
        if (!$cart->add()) {
            throw new \PrestaShopException($this->module->l('No se pudo crear el carrito.'));
        }

        $this->context->cart = $cart;
        $this->context->cookie->id_cart = (int) $cart->id;

        return $cart;
    }

    private function validateLimits(int $idProduct, int $quantity, array $config, bool $absolute): void
    {
        $idCart = (int) $this->context->cart->id;
        $cartTotal = $this->cartRepository->totalQuantity($idCart);
        $cartTotalAfter = $absolute
            ? $cartTotal - $this->cartRepository->productQuantity($idCart, $idProduct) + $quantity
            : $cartTotal + $quantity;

        $currentProductQuantity = $this->cartRepository->productQuantity($idCart, $idProduct);
        $productAfter = $absolute ? $quantity : $currentProductQuantity + $quantity;

        if (($config['stock_mode'] ?? '') === 'independent' && (int) $config['sample_stock'] < $productAfter) {
            throw new \PrestaShopException($this->message('LIMIT_ERROR_STOCK', []));
        }

        if (!$this->configuration->getBool('LIMITS_ENABLED', true)) {
            return;
        }

        $idCustomer = (int) ($this->context->customer->id ?? 0);
        $groups = $idCustomer > 0 ? array_map('intval', \Customer::getGroupsStatic($idCustomer)) : [];
        $defaultExempt = $this->customerIsExempt();
        $rules = $this->limitExceptionRepository->findRules($idCustomer, (int) $this->context->shop->id, $groups);
        $resolution = $this->limitExceptionPolicy->resolve(
            $rules['customer'],
            $rules['groups'],
            [
                'max_total' => $this->configuration->getInt('MAX_CUSTOMER_TOTAL_PERIOD'),
                'max_product' => (int) (($config['max_per_customer'] ?? 0)
                    ?: $this->configuration->getInt('MAX_CUSTOMER_PRODUCT_PERIOD')),
                'period_days' => max(1, $this->configuration->getInt('CUSTOMER_PERIOD_DAYS', 30)),
            ]
        );
        if ($defaultExempt || $resolution['exempt']) {
            return;
        }

        $maxCustomerTotal = $resolution['max_total'];
        $maxCustomerProduct = $resolution['max_product'];
        $historyTotal = 0;
        $historyProduct = 0;
        $days = $resolution['period_days'];

        if ($this->shouldApplyHistoricalCustomerLimits() && ($maxCustomerTotal > 0 || $maxCustomerProduct > 0)) {
            $dateFrom = (new \DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
            $idShop = (int) $this->context->shop->id;
            $resetDate = $this->limitExceptionRepository->latestResetDate($idCustomer, $idShop);
            $states = $this->countedOrderStateIds();
            $historyTotal = $this->customerLimitRepository->quantitySince($idCustomer, $idShop, $dateFrom, null, $states, $resetDate);
            $historyProduct = $this->customerLimitRepository->quantitySince($idCustomer, $idShop, $dateFrom, $idProduct, $states, $resetDate);
        }

        $maxProduct = (int) (($config['max_per_order'] ?? 0) ?: $this->configuration->getInt('MAX_PER_PRODUCT'));
        $state = [
            'cart_total_after' => $cartTotalAfter,
            'product_after' => $productAfter,
            'customer_total_after' => $historyTotal + $cartTotalAfter,
            'customer_product_after' => $historyProduct + $productAfter,
        ];
        $violation = $this->limitPolicy->firstViolation(
            $state,
            [
                'max_total' => $this->configuration->getInt('MAX_TOTAL'),
                'max_product' => $maxProduct,
                'max_customer_total' => $maxCustomerTotal,
                'max_customer_product' => $maxCustomerProduct,
            ]
        );

        if ($violation === null) {
            return;
        }
        $messageKey = [
            'total' => 'LIMIT_ERROR_TOTAL',
            'product' => 'LIMIT_ERROR_PRODUCT',
            'customer_total' => 'LIMIT_ERROR_CUSTOMER_TOTAL',
            'customer_product' => 'LIMIT_ERROR_CUSTOMER_PRODUCT',
        ][$violation['code']] ?? 'LIMIT_ERROR_TOTAL';
        $message = $this->message($messageKey, [
            '%limit%' => (string) $violation['limit'],
            '%days%' => (string) $days,
        ]);
        $observed = match ($violation['code']) {
            'product' => $state['product_after'],
            'customer_total' => $state['customer_total_after'],
            'customer_product' => $state['customer_product_after'],
            default => $state['cart_total_after'],
        };
        $this->limitExceptionRepository->logEvent([
            'id_shop' => (int) $this->context->shop->id,
            'id_customer' => $idCustomer,
            'id_guest' => (int) ($this->context->cookie->id_guest ?? 0),
            'id_cart' => $idCart,
            'id_product' => $idProduct,
            'event_type' => 'blocked',
            'limit_code' => $violation['code'],
            'limit_value' => $violation['limit'],
            'observed_value' => $observed,
            'source_type' => $resolution['source_type'],
            'source_id' => $resolution['source_id'],
            'message' => $message,
            'metadata' => ['days' => $days, 'states' => $this->countedOrderStateIds()],
        ]);

        throw new \PrestaShopException($message);
    }

    /** @return list<int> */
    private function countedOrderStateIds(): array
    {
        $rawConfigured = trim($this->configuration->getString('LIMIT_ORDER_STATES'));
        if ($rawConfigured === 'none') {
            return [-1];
        }
        $configured = array_values(array_unique(array_filter(array_map(
            'intval',
            preg_split('/[^0-9]+/', $rawConfigured) ?: []
        ))));
        if ($configured !== []) {
            return $configured;
        }

        return array_values(array_unique(array_filter(array_map('intval', [
            \Configuration::get('PS_OS_PAYMENT'),
            \Configuration::get('PS_OS_PREPARATION'),
            \Configuration::get('PS_OS_SHIPPING'),
            \Configuration::get('PS_OS_DELIVERED'),
        ]))));
    }

    private function shouldApplyHistoricalCustomerLimits(): bool
    {
        if (!\Validate::isLoadedObject($this->context->customer) || (int) $this->context->customer->id <= 0) {
            return false;
        }

        $isGuest = (bool) ($this->context->customer->is_guest ?? false);

        return !$isGuest || $this->configuration->getBool('LIMIT_GUESTS', true);
    }

    private function customerIsExempt(): bool
    {
        if (!\Validate::isLoadedObject($this->context->customer) || (int) $this->context->customer->id <= 0) {
            return false;
        }

        $configured = array_values(array_filter(array_map(
            'intval',
            preg_split('/[^0-9]+/', $this->configuration->getString('LIMIT_EXEMPT_GROUPS')) ?: []
        )));
        if ($configured === []) {
            return false;
        }

        $groups = array_map('intval', \Customer::getGroupsStatic((int) $this->context->customer->id));

        return array_intersect($configured, $groups) !== [];
    }

    /** @param array<string,string> $replacements */
    private function message(string $key, array $replacements): string
    {
        $fallbacks = [
            'LIMIT_ERROR_TOTAL' => 'El máximo total es de %limit% muestras por pedido.',
            'LIMIT_ERROR_PRODUCT' => 'Solo se permiten %limit% muestras de esta referencia.',
            'LIMIT_ERROR_CUSTOMER_TOTAL' => 'Has alcanzado el máximo de %limit% muestras durante los últimos %days% días.',
            'LIMIT_ERROR_CUSTOMER_PRODUCT' => 'Has alcanzado el máximo de %limit% muestras de esta referencia durante los últimos %days% días.',
            'LIMIT_ERROR_STOCK' => 'No hay suficiente stock de muestras.',
        ];

        $text = $this->configuration->getString(
            $key,
            (int) $this->context->language->id,
            $fallbacks[$key] ?? 'No se puede añadir la muestra.'
        );

        return strtr($text, $replacements);
    }

    private function clearPricingCaches(): void
    {
        if (method_exists('Product', 'flushPriceCache')) {
            \Product::flushPriceCache();
        }
        if (method_exists('Cart', 'resetStaticCache')) {
            \Cart::resetStaticCache();
        }
    }
}
