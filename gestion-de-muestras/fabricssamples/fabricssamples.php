<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/config/autoload.php';

use NaranjaCreativos\FabricSamples\Cart\Adapter\NativeCartAdapterFactory;
use NaranjaCreativos\FabricSamples\Cart\Adapter\NativeCartAdapterInterface;
use NaranjaCreativos\FabricSamples\Backup\ResetBackupService;
use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Domain\CouponTransitionPolicy;
use NaranjaCreativos\FabricSamples\Domain\CouponReissuePolicy;
use NaranjaCreativos\FabricSamples\Domain\CouponReactivationPolicy;
use NaranjaCreativos\FabricSamples\Domain\CouponValuePolicy;
use NaranjaCreativos\FabricSamples\Domain\StockReconciliationPolicy;
use NaranjaCreativos\FabricSamples\Domain\LimitPolicy;
use NaranjaCreativos\FabricSamples\Domain\LimitExceptionPolicy;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Infrastructure\SqlFileExecutor;
use NaranjaCreativos\FabricSamples\Infrastructure\DatabaseLock;
use NaranjaCreativos\FabricSamples\Migration\MigrationManager;
use NaranjaCreativos\FabricSamples\Presentation\ButtonStyleProvider;
use NaranjaCreativos\FabricSamples\Presentation\PresentedDataAccessor;
use NaranjaCreativos\FabricSamples\Presentation\PriceFormatter;
use NaranjaCreativos\FabricSamples\Presentation\SampleNameFormatter;
use NaranjaCreativos\FabricSamples\Privacy\PrivacyService;
use NaranjaCreativos\FabricSamples\Repository\CartSampleRepository;
use NaranjaCreativos\FabricSamples\Repository\CouponRepository;
use NaranjaCreativos\FabricSamples\Repository\CouponReissueRepository;
use NaranjaCreativos\FabricSamples\Repository\ConversionRepository;
use NaranjaCreativos\FabricSamples\Repository\CustomerLimitRepository;
use NaranjaCreativos\FabricSamples\Repository\LimitExceptionRepository;
use NaranjaCreativos\FabricSamples\Repository\OrderSampleRepository;
use NaranjaCreativos\FabricSamples\Repository\SampleProductRepository;
use NaranjaCreativos\FabricSamples\Repository\StockMovementRepository;
use NaranjaCreativos\FabricSamples\Service\ConversionTrackingService;
use NaranjaCreativos\FabricSamples\Service\CouponLifecycleService;
use NaranjaCreativos\FabricSamples\Service\CouponService;
use NaranjaCreativos\FabricSamples\Service\CartIntegrityService;
use NaranjaCreativos\FabricSamples\Service\CartSamplePriceProvider;
use NaranjaCreativos\FabricSamples\Service\ImageSnapshotService;
use NaranjaCreativos\FabricSamples\Service\OrderSampleService;
use NaranjaCreativos\FabricSamples\Service\ProductAttributeResolver;
use NaranjaCreativos\FabricSamples\Service\SampleAvailabilityService;
use NaranjaCreativos\FabricSamples\Service\SampleCartService;
use NaranjaCreativos\FabricSamples\Service\SamplePricingService;
use NaranjaCreativos\FabricSamples\Service\StockLifecycleService;

class Fabricssamples extends Module
{
    public const VERSION = '2.15.21';
    public const CFG_PREFIX = ModuleConfiguration::PREFIX;

    private ?ModuleConfiguration $moduleConfiguration = null;
    private ?SampleProductRepository $sampleProductRepository = null;
    private ?CartSampleRepository $cartSampleRepository = null;
    private ?CustomerLimitRepository $customerLimitRepository = null;
    private ?LimitExceptionRepository $limitExceptionRepository = null;
    private ?OrderSampleRepository $orderSampleRepository = null;
    private ?CouponRepository $couponRepository = null;
    private ?CouponReissueRepository $couponReissueRepository = null;
    private ?ConversionRepository $conversionRepository = null;
    private ?StockMovementRepository $stockMovementRepository = null;
    private ?SampleAvailabilityService $sampleAvailabilityService = null;
    private ?SamplePricingService $samplePricingService = null;
    private ?SampleCartService $sampleCartService = null;
    private ?OrderSampleService $orderSampleService = null;
    private ?CouponService $couponService = null;
    private ?CouponLifecycleService $couponLifecycleService = null;
    private ?StockLifecycleService $stockLifecycleService = null;
    private ?ConversionTrackingService $conversionTrackingService = null;
    private ?ImageSnapshotService $imageSnapshotService = null;
    private ?PresentedDataAccessor $presentedDataAccessor = null;
    private ?ButtonStyleProvider $buttonStyleProvider = null;
    private ?SampleNameFormatter $sampleNameFormatter = null;
    private ?LimitPolicy $limitPolicy = null;
    private ?LimitExceptionPolicy $limitExceptionPolicy = null;
    private ?CouponValuePolicy $couponValuePolicy = null;
    private ?CouponTransitionPolicy $couponTransitionPolicy = null;
    private ?CouponReactivationPolicy $couponReactivationPolicy = null;
    private ?CouponReissuePolicy $couponReissuePolicy = null;
    private ?DatabaseLock $databaseLock = null;
    private ?StockReconciliationPolicy $stockReconciliationPolicy = null;
    private ?NativeCartAdapterInterface $nativeCartAdapter = null;
    private ?CartIntegrityService $cartIntegrityService = null;
    private ?CartSamplePriceProvider $cartSamplePriceProvider = null;
    private ?ProductAttributeResolver $productAttributeResolver = null;
    private ?PrivacyService $privacyService = null;
    private string $checkoutLockName = '';

    public function __construct()
    {
        $this->name = 'fabricssamples';
        $this->tab = 'front_office_features';
        $this->version = self::VERSION;
        $this->author = 'Naranja Creativos';
        $this->author_uri = 'https://www.naranjacreativos.es';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => '9.1.99'];

        parent::__construct();

        $this->displayName = $this->l('Gestión de muestras');
        $this->description = $this->l('Gestión de muestras de tejido de los productos de la tienda y cupones descuento');
        $this->additional_description = $this->l('Creado por Naranja Creativos · www.naranjacreativos.es');
        $this->confirmUninstall = $this->l('¿Desea desinstalar el módulo? Se eliminarán las tablas, configuraciones y líneas de muestra activas.');
    }

    public function install()
    {
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->_errors[] = $this->l('Se requiere PHP 8.1 o superior.');
            return false;
        }

        try {
            if (!parent::install()) {
                $this->_errors[] = $this->l('No se pudo registrar el módulo en PrestaShop.');
                return false;
            }
            if (!$this->executeSqlFile('install.sql', true)) {
                throw new RuntimeException('install.sql');
            }
            if (!$this->configuration()->installDefaults()) {
                throw new RuntimeException('configuration');
            }
            $migrationManager = new MigrationManager(
                $this,
                new SchemaInspector($this->getLocalPath() . 'sql/install.sql')
            );
            if (!$migrationManager->recordFreshInstall(self::VERSION)) {
                throw new RuntimeException('schema_migration');
            }
            if (!$this->installTabs()) {
                throw new RuntimeException('tabs');
            }
            if (!$this->registerHook($this->getRequiredHooks())) {
                throw new RuntimeException('hooks');
            }
            if (!$this->registerCompatibilityHooks()) {
                throw new RuntimeException('compatibility_hooks');
            }

            return true;
        } catch (Throwable $exception) {
            $this->_errors[] = sprintf(
                $this->l('La instalación se detuvo en el paso "%s": %s'),
                $exception instanceof RuntimeException ? $exception->getMessage() : 'database',
                $exception->getMessage()
            );
            PrestaShopLogger::addLog(
                'fabricssamples install: ' . $exception->getMessage(),
                3,
                null,
                'Module',
                (int) $this->id,
                true
            );

            // Roll back every step already installed so a retry starts cleanly.
            $this->safeUninstallCleanup();
            try {
                parent::uninstall();
            } catch (Throwable $rollbackException) {
                PrestaShopLogger::addLog('fabricssamples install rollback: ' . $rollbackException->getMessage(), 3);
            }

            return false;
        }
    }

    public function uninstall()
    {
        // Use direct, LIMIT-free cleanup queries. Some PrestaShop/MariaDB combinations
        // append LIMIT 1 internally to helper methods such as getRow()/getValue(); if a
        // nested ObjectModel deletion also supplies a limit, uninstall may fail with
        // "LIMIT 1 LIMIT 1". The cleanup below avoids that code path entirely.
        if (!$this->safeUninstallCleanup()) {
            $this->_errors[] = $this->l('La desinstalación se ha detenido porque no se pudo verificar toda la limpieza. Revise el registro antes de reintentarlo.');
            return false;
        }

        return parent::uninstall();
    }

    private function safeUninstallCleanup(): bool
    {
        $success = true;
        foreach ([
            fn () => $this->purgeSampleCartRowsDirectly(),
            fn () => $this->purgeSampleCartRulesDirectly(),
            fn () => $this->imageSnapshotService()->purge(),
            fn () => $this->uninstallTabsDirectly(),
            fn () => $this->executeSqlFile('uninstall.sql', false),
            fn () => $this->purgeCustomCatalogImagesDirectly(),
            fn () => $this->purgeModuleConfigurationDirectly(),
        ] as $cleanup) {
            try {
                if ($cleanup() === false) {
                    throw new RuntimeException('Una tarea de limpieza devolvió false.');
                }
            } catch (Throwable $exception) {
                $success = false;
                PrestaShopLogger::addLog(
                    'fabricssamples uninstall cleanup: ' . $exception->getMessage(),
                    2,
                    null,
                    'Module',
                    (int) $this->id,
                    true
                );
            }
        }

        return $success;
    }

    private function purgeSampleCartRowsDirectly(): void
    {
        $db = Db::getInstance();
        if (!$this->databaseTableExists(_DB_PREFIX_ . 'fabricssamples_cart')) {
            return;
        }

        $rows = $db->executeS(
            'SELECT DISTINCT id_customization FROM `' . _DB_PREFIX_ . 'fabricssamples_cart`'
            . ' WHERE id_customization > 0'
        );
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            array_column(is_array($rows) ? $rows : [], 'id_customization')
        ))));
        if ($ids === []) {
            return;
        }

        $in = implode(',', $ids);
        foreach (['cart_product', 'customized_data', 'customization'] as $table) {
            if ($this->databaseTableExists(_DB_PREFIX_ . $table)) {
                $this->executeCleanupSqlOrThrow(
                    $db,
                    'DELETE FROM `' . _DB_PREFIX_ . bqSQL($table) . '`'
                    . ' WHERE id_customization IN (' . $in . ')'
                );
            }
        }
    }

    private function purgeSampleCartRulesDirectly(): void
    {
        $db = Db::getInstance();
        if (!$this->databaseTableExists(_DB_PREFIX_ . 'fabricssamples_coupon')) {
            return;
        }

        $sql = 'SELECT id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` WHERE id_cart_rule>0';
        if ($this->databaseTableExists(_DB_PREFIX_ . 'fabricssamples_coupon_reissue')) {
            $sql .= ' UNION SELECT id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` WHERE id_cart_rule>0';
        }
        $rows = $db->executeS($sql);
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            array_column(is_array($rows) ? $rows : [], 'id_cart_rule')
        ))));
        if ($ids === []) {
            return;
        }

        $in = implode(',', $ids);
        $tables = [
            'cart_cart_rule',
            'cart_rule_carrier',
            'cart_rule_country',
            'cart_rule_group',
            'cart_rule_lang',
            'cart_rule_product_rule_value',
            'cart_rule_product_rule',
            'cart_rule_product_rule_group',
            'cart_rule_shop',
            'cart_rule_combination',
            'cart_rule',
        ];

        foreach ($tables as $table) {
            if (!$this->databaseTableExists(_DB_PREFIX_ . $table)) {
                continue;
            }
            $column = $table === 'cart_rule_product_rule_value'
                ? 'id_product_rule'
                : 'id_cart_rule';
            if ($table === 'cart_rule_product_rule_value') {
                $productRuleRows = $db->executeS(
                    'SELECT id_product_rule FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule`'
                    . ' WHERE id_product_rule_group IN ('
                    . 'SELECT id_product_rule_group FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule_group`'
                    . ' WHERE id_cart_rule IN (' . $in . '))'
                );
                $productRuleIds = array_values(array_unique(array_filter(array_map(
                    'intval',
                    array_column(is_array($productRuleRows) ? $productRuleRows : [], 'id_product_rule')
                ))));
                if ($productRuleIds !== []) {
                    $this->executeCleanupSqlOrThrow(
                        $db,
                        'DELETE FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule_value`'
                        . ' WHERE id_product_rule IN (' . implode(',', $productRuleIds) . ')'
                    );
                }
                continue;
            }
            if ($table === 'cart_rule_product_rule') {
                $this->executeCleanupSqlOrThrow(
                    $db,
                    'DELETE FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule`'
                    . ' WHERE id_product_rule_group IN ('
                    . 'SELECT id_product_rule_group FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule_group`'
                    . ' WHERE id_cart_rule IN (' . $in . '))'
                );
                continue;
            }
            if ($table === 'cart_rule_combination') {
                $this->executeCleanupSqlOrThrow(
                    $db,
                    'DELETE FROM `' . _DB_PREFIX_ . 'cart_rule_combination`'
                    . ' WHERE id_cart_rule_1 IN (' . $in . ') OR id_cart_rule_2 IN (' . $in . ')'
                );
                continue;
            }
            $this->executeCleanupSqlOrThrow(
                $db,
                'DELETE FROM `' . _DB_PREFIX_ . bqSQL($table) . '`'
                . ' WHERE `' . bqSQL($column) . '` IN (' . $in . ')'
            );
        }
    }

    private function executeCleanupSqlOrThrow(Db $db, string $sql): void
    {
        if (!$db->execute($sql)) {
            throw new RuntimeException($db->getMsgError() !== '' ? $db->getMsgError() : 'Error SQL durante la limpieza.');
        }
    }

    private function purgeModuleConfigurationDirectly(): void
    {
        $names = array_map(
            static fn (string $key): string => ModuleConfiguration::PREFIX . $key,
            array_values(array_unique(array_merge(
                ModuleConfiguration::keys(),
                ModuleConfiguration::obsoleteKeys()
            )))
        );
        if ($names === []) {
            return;
        }

        foreach ($names as $name) {
            if (!Configuration::deleteByName($name)) {
                throw new RuntimeException('No se pudo eliminar la configuración ' . $name . '.');
            }
        }
    }

    private function uninstallTabsDirectly(): void
    {
        $db = Db::getInstance();
        if (!$this->databaseTableExists(_DB_PREFIX_ . 'tab')) {
            return;
        }
        $classes = [
            'AdminFabricSamplesAudit',
            'AdminFabricSamplesLimits',
            'AdminFabricSamplesStock',
            'AdminFabricSamplesDiagnostics',
            'AdminFabricSamplesCoupons',
            'AdminFabricSamples',
            'AdminFabricSamplesParent',
        ];
        $quoted = implode(',', array_map(
            static fn (string $class): string => "'" . pSQL($class) . "'",
            $classes
        ));
        $rows = $db->executeS(
            'SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name IN (' . $quoted . ')'
        );
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            array_column(is_array($rows) ? $rows : [], 'id_tab')
        ))));
        if ($ids === []) {
            return;
        }
        $in = implode(',', $ids);
        foreach (['tab_lang', 'tab_shop', 'tab'] as $table) {
            if ($this->databaseTableExists(_DB_PREFIX_ . $table)) {
                $db->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . bqSQL($table) . '` WHERE id_tab IN (' . $in . ')'
                );
            }
        }
    }

    private function databaseTableExists(string $tableName): bool
    {
        $rows = Db::getInstance()->executeS(
            "SHOW TABLES LIKE '" . pSQL($tableName) . "'"
        );

        return is_array($rows) && $rows !== [];
    }

    /** @return list<string> */
    public function getRequiredHooks(): array
    {
        return [
            'displayProductAdditionalInfo',
            'displayProductListReviews',
            'displayCustomerAccount',
            'displayMyAccountBlock',
            'displayCartExtraProductInfo',
            'displayAdminOrderMainBottom',
            'displayOrderDetail',
            'displayPDFInvoice',
            'displayPDFDeliverySlip',
            'actionProductPriceCalculation',
            'actionValidateOrder',
            'actionObjectOrderDetailAddAfter',
            'actionOrderStatusUpdate',
            'actionOrderStatusPostUpdate',
            'actionProductCancel',
            'actionOrderSlipAdd',
            'actionObjectOrderHistoryAddAfter',
            'actionCartSave',
            'actionFrontControllerInitAfter',
            'actionObjectProductDeleteAfter',
            'actionObjectOrderDeleteAfter',
            'actionFrontControllerSetMedia',
            'moduleRoutes',
            'actionPresentProductListing',
            'actionPresentCart',
            'actionExportGDPRData',
            'actionDeleteGDPRCustomer',
        ];
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminFabricSamples'));
    }

    public function hookModuleRoutes($params): array
    {
        $slug = trim($this->configuration()->getString('FRIENDLY_URL', null, 'solicitar-muestras'), '/');
        return [
            'module-fabricssamples-samples' => [
                'controller' => 'samples',
                'rule' => $slug !== '' ? $slug : 'solicitar-muestras',
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => $this->name],
            ],
            'module-fabricssamples-mySamples' => [
                'controller' => 'mysamples',
                'rule' => 'mis-muestras',
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => $this->name],
            ],
            'module-fabricssamples-myCoupons' => [
                'controller' => 'mycoupons',
                'rule' => 'mis-cupones-muestras',
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => $this->name],
            ],
        ];
    }

    public function hookActionFrontControllerSetMedia($params): void
    {
        $controller = $this->context->controller;
        if (!$controller) {
            return;
        }

        $isCartPage = isset($controller->php_self) && (string) $controller->php_self === 'cart';
        $isModulePage = (string) Tools::getValue('module') === $this->name;
        if (!$isCartPage
            && !$isModulePage
            && !$this->configuration()->getBool('SHOW_PRODUCT')
            && !$this->configuration()->getBool('SHOW_LISTING')) {
            return;
        }

        $controller->registerStylesheet(
            'module-fabricssamples-front',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 150, 'version' => self::VERSION]
        );
        $controller->registerJavascript(
            'module-fabricssamples-front',
            'modules/' . $this->name . '/views/js/catalog.js',
            ['position' => 'bottom', 'priority' => 150, 'version' => self::VERSION]
        );

        $sampleCustomizationIds = [];
        if (Validate::isLoadedObject($this->context->cart)) {
            $sampleCustomizationIds = $this->cartRepository()->customizationIdsByCart((int) $this->context->cart->id);
        }

        Media::addJsDef([
            'fabricSamplesAjaxUrl' => $this->context->link->getModuleLink($this->name, 'ajax', [], true),
            'fabricSamplesToken' => Tools::getToken(false),
            'fabricSampleCustomizationIds' => $sampleCustomizationIds,
        ]);
    }

    public function hookDisplayProductAdditionalInfo($params): string
    {
        if (!$this->configuration()->getBool('SHOW_PRODUCT')) {
            return '';
        }

        $idProduct = $this->extractProductId((array) $params);
        $config = $idProduct > 0 ? $this->getSampleConfig($idProduct) : [];
        if ($config === [] || !$this->isSampleAvailable($config, $idProduct)) {
            return '';
        }

        $this->assignSampleTemplate($idProduct, $config);
        return $this->fetch('module:' . $this->name . '/views/templates/hook/product_block.tpl');
    }

    public function hookDisplayProductListReviews($params): string
    {
        if (!$this->configuration()->getBool('SHOW_LISTING')) {
            return '';
        }

        $idProduct = $this->extractProductId((array) $params);
        $config = $idProduct > 0 ? $this->getSampleConfig($idProduct) : [];
        if ($config === [] || !$this->isSampleAvailable($config, $idProduct)) {
            return '';
        }

        $this->assignSampleTemplate($idProduct, $config);
        return $this->fetch('module:' . $this->name . '/views/templates/hook/listing_badge.tpl');
    }

    public function hookActionPresentProductListing(array $params): void
    {
        if (!isset($params['presentedProduct'])) {
            return;
        }

        $presented = &$params['presentedProduct'];
        $idCustomization = (int) $this->accessor()->get($presented, 'id_customization', 0);
        if ($idCustomization <= 0 || $this->getCartSampleByCustomization($idCustomization, (int) ($this->context->cart->id ?? 0)) === []) {
            return;
        }

        $this->accessor()->set(
            $presented,
            'name',
            $this->nameFormatter()->format((string) $this->accessor()->get($presented, 'name', ''))
        );
    }

    public function hookActionPresentCart(array $params): void
    {
        if (!isset($params['presentedCart'])) {
            return;
        }

        $cart = &$params['presentedCart'];
        $products = $this->accessor()->get($cart, 'products', []);
        if (!is_array($products)) {
            return;
        }

        $idCart = (int) ($this->context->cart->id ?? 0);
        foreach ($products as &$product) {
            $idCustomization = (int) $this->accessor()->get($product, 'id_customization', 0);
            if ($idCustomization <= 0 || $this->getCartSampleByCustomization($idCustomization, $idCart) === []) {
                continue;
            }

            $this->accessor()->set(
                $product,
                'name',
                $this->nameFormatter()->format((string) $this->accessor()->get($product, 'name', ''))
            );
            $this->accessor()->set($product, 'fabric_sample', true);
            $this->accessor()->set($product, 'fabric_sample_id_customization', $idCustomization);

            $removeUrl = (string) $this->accessor()->get($product, 'remove_from_cart_url', '');
            if ($removeUrl !== '') {
                $this->accessor()->set(
                    $product,
                    'remove_from_cart_url',
                    $this->appendUrlParameters($removeUrl, [
                        'id_customization' => $idCustomization,
                        'fs_sample' => 1,
                    ])
                );
            }
        }
        unset($product);

        $this->accessor()->set($cart, 'products', $products);
    }

    public function hookActionProductPriceCalculation(&$params): void
    {
        $this->cartSamplePriceProvider()->apply($params);
    }

    public function hookActionCartSave($params): void
    {
        $cart = $params['cart'] ?? $this->context->cart;
        if (!Validate::isLoadedObject($cart)) {
            return;
        }

        $this->cartIntegrityService()->repairCart($cart, false);
    }

    public function hookActionFrontControllerInitAfter($params): void
    {
        $this->privacyService()->runDaily();
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || (int) $cart->id <= 0) {
            return;
        }

        try {
            $this->cartIntegrityService()->repairCart($cart, true);
        } catch (Throwable $exception) {
            $message = $this->l('Se ha detectado una muestra dañada en el carrito. El pago se ha bloqueado para evitar un pedido incorrecto.');
            if (isset($this->context->controller->errors)) {
                $this->context->controller->errors[] = $message;
            }

            $isCartPage = isset($this->context->controller->php_self)
                && (string) $this->context->controller->php_self === 'cart';
            if (!$isCartPage) {
                Tools::redirect($this->context->link->getPageLink('cart', true, null, [
                    'action' => 'show',
                    'fs_integrity_error' => 1,
                ]));
            }
        }
    }

    public function hookActionExportGDPRData($customer): string
    {
        return $this->privacyService()->exportCustomer($this->extractGdprCustomerId($customer));
    }

    public function hookActionDeleteGDPRCustomer($customer): string
    {
        return $this->privacyService()->deleteCustomer($this->extractGdprCustomerId($customer));
    }

    private function extractGdprCustomerId(mixed $customer): int
    {
        if ($customer instanceof Customer) {
            return (int) $customer->id;
        }
        if (is_array($customer)) {
            if (($customer['customer'] ?? null) instanceof Customer) {
                return (int) $customer['customer']->id;
            }
            return (int) ($customer['id_customer'] ?? $customer['id'] ?? 0);
        }
        return is_object($customer) ? (int) ($customer->id ?? 0) : 0;
    }

    public function hookActionValidateOrderBefore($params): void
    {
        $cart = $params['cart'] ?? $this->context->cart;
        if (Validate::isLoadedObject($cart)) {
            $resource = (int) $cart->id_customer > 0
                ? 'checkout-customer:' . (int) $cart->id_shop . ':' . (int) $cart->id_customer
                : 'checkout-cart:' . (int) $cart->id;
            $this->checkoutLockName = $this->databaseLock()->acquire($resource, 10);
            try {
                $this->cartIntegrityService()->repairCart($cart, true);
                $this->cartService()->validateCheckout($cart);
            } catch (Throwable $exception) {
                $this->releaseCheckoutLock();
                throw $exception;
            }
        }
    }

    public function hookActionObjectOrderDetailAddAfter($params): void
    {
        $detail = $params['object'] ?? null;
        if (!$detail instanceof OrderDetail || !Validate::isLoadedObject($detail)) {
            return;
        }

        try {
            $this->orderSampleService()->bindNativeOrderDetail($detail);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('fabricssamples: no se pudo completar el histórico del detalle %d: %s', (int) $detail->id, $exception->getMessage()),
                2,
                null,
                'OrderDetail',
                (int) $detail->id,
                true
            );
        }

        // Do not reconcile stock from this early hook. PrestaShop can still perform
        // native stock/order-state work after OrderDetail::add(). The authoritative
        // initial stock reconciliation is actionValidateOrder, when order creation is complete.
    }

    public function hookActionValidateOrder($params): void
    {
        $order = $params['order'] ?? null;
        $cart = $params['cart'] ?? null;
        if (!Validate::isLoadedObject($order) || !Validate::isLoadedObject($cart)) {
            return;
        }

        try {
            $samples = [];
            try {
                $samples = $this->orderSampleService()->synchronize($order, $cart);
            } catch (Throwable $exception) {
                PrestaShopLogger::addLog(
                    sprintf('fabricssamples: no se pudo completar el histórico del pedido %d: %s', (int) $order->id, $exception->getMessage()),
                    2,
                    null,
                    'Order',
                    (int) $order->id,
                    true
                );
            }

            // Never couple stock to historical snapshots, URLs, images or coupon work.
            try {
                $this->stockLifecycleService()->reconcileOrder($order, null, 'actionValidateOrder');
            } catch (Throwable $exception) {
                PrestaShopLogger::addLog(
                    sprintf('fabricssamples: fallo crítico reconciliando stock del pedido %d: %s', (int) $order->id, $exception->getMessage()),
                    3,
                    null,
                    'Order',
                    (int) $order->id,
                    true
                );
            }

            try {
                $this->couponLifecycleService()->synchronizeOrder($order, null, 'actionValidateOrder');
            } catch (Throwable $exception) {
                PrestaShopLogger::addLog(
                    sprintf('fabricssamples: no se pudo sincronizar el cupón del pedido %d: %s', (int) $order->id, $exception->getMessage()),
                    2,
                    null,
                    'Order',
                    (int) $order->id,
                    true
                );
            }

            if ($samples === []) {
                $samples = $this->orderRepository()->findForCouponByOrder((int) $order->id);
            }

            try {
                $this->conversionTrackingService()->track($order, $samples);
            } catch (Throwable $exception) {
                PrestaShopLogger::addLog(
                    sprintf('fabricssamples: no se pudo registrar la conversión del pedido %d: %s', (int) $order->id, $exception->getMessage()),
                    2,
                    null,
                    'Order',
                    (int) $order->id,
                    true
                );
            }
        } finally {
            $this->releaseCheckoutLock();
        }
    }

    private function releaseCheckoutLock(): void
    {
        if ($this->checkoutLockName !== '') {
            $this->databaseLock()->release($this->checkoutLockName);
            $this->checkoutLockName = '';
        }
    }

    public function hookActionOrderStatusUpdate($params): void
    {
        $this->synchronizeOrderLifecycleFromStatus($params, 'actionOrderStatusUpdate');
    }

    public function hookActionOrderStatusPostUpdate($params): void
    {
        $this->synchronizeOrderLifecycleFromStatus($params, 'actionOrderStatusPostUpdate');
    }

    public function hookActionObjectOrderHistoryAddAfter($params): void
    {
        $history = $params['object'] ?? null;
        if (!$history instanceof OrderHistory || !Validate::isLoadedObject($history)) {
            return;
        }
        $idOrder = (int) $history->id_order;
        $idOrderState = (int) $history->id_order_state;
        if ($idOrder <= 0) {
            return;
        }
        $state = $idOrderState > 0 ? new OrderState($idOrderState) : null;
        $this->stockLifecycleService()->reconcileOrderById($idOrder, $state, 'actionObjectOrderHistoryAddAfter');
        $this->couponLifecycleService()->synchronizeOrderById($idOrder, $state, 'actionObjectOrderHistoryAddAfter');
    }

    public function hookActionProductCancel($params): void
    {
        try {
            $cancelOrder = $params['order'] ?? null;
            $idOrder = $cancelOrder instanceof Order && Validate::isLoadedObject($cancelOrder)
                ? (int) $cancelOrder->id
                : (int) ($params['id_order'] ?? 0);
            if ($idOrder > 0) {
                $this->orderSampleService()->synchronizeOrderById($idOrder);
            }
            $this->stockLifecycleService()->handleProductCancel(is_array($params) ? $params : []);
            if ($idOrder > 0) {
                $this->couponLifecycleService()->synchronizeOrderById($idOrder, null, 'actionProductCancel');
            }
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('fabricssamples: error procesando cancelación/reembolso: ' . $exception->getMessage(), 3);
        }
    }

    public function hookActionOrderSlipAdd($params): void
    {
        $order = $params['order'] ?? null;
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return;
        }
        try {
            $this->orderSampleService()->synchronizeOrderById((int) $order->id);
            $this->stockLifecycleService()->reconcileOrder($order, null, 'actionOrderSlipAdd');
            $this->couponLifecycleService()->synchronizeOrder($order, null, 'actionOrderSlipAdd');
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('fabricssamples: no se pudo procesar el abono del pedido %d: %s', (int) $order->id, $exception->getMessage()),
                3,
                null,
                'Order',
                (int) $order->id,
                true
            );
        }
    }

    /** @param array<string,mixed> $params */
    private function synchronizeOrderLifecycleFromStatus(array $params, string $source): void
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            return;
        }
        $newStatus = $params['newOrderStatus'] ?? null;
        if (!$newStatus instanceof OrderState || !Validate::isLoadedObject($newStatus)) {
            $newStatus = null;
        }
        try {
            $this->orderSampleService()->synchronizeOrderById($idOrder);
            $this->stockLifecycleService()->reconcileOrderById($idOrder, $newStatus, $source);
            $this->couponLifecycleService()->synchronizeOrderById($idOrder, $newStatus, $source);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('fabricssamples: no se pudo sincronizar el pedido %d desde %s: %s', $idOrder, $source, $exception->getMessage()),
                3,
                null,
                'Order',
                $idOrder,
                true
            );
        }
    }

    public function hookDisplayCartExtraProductInfo($params): string
    {
        $idCustomization = (int) ($params['product']['id_customization'] ?? 0);
        if ($idCustomization <= 0) {
            return '';
        }

        $line = $this->getCartSampleByCustomization($idCustomization, (int) ($this->context->cart->id ?? 0));
        if ($line === []) {
            return '';
        }

        $this->context->smarty->assign(['fs_cart_line' => $line]);
        return $this->fetch('module:' . $this->name . '/views/templates/hook/cart_extra.tpl');
    }

    public function hookDisplayAdminOrderMainBottom($params): string
    {
        return $this->renderOrderSamples((int) ($params['id_order'] ?? Tools::getValue('id_order')), true);
    }

    public function hookDisplayOrderDetail($params): string
    {
        $order = $params['order'] ?? null;
        $idOrder = Validate::isLoadedObject($order) ? (int) $order->id : (int) Tools::getValue('id_order');
        return $this->renderOrderSamples($idOrder, false);
    }


    public function hookDisplayPDFInvoice($params): string
    {
        return $this->renderPdfOrderSamples($params);
    }

    public function hookDisplayPDFDeliverySlip($params): string
    {
        return $this->renderPdfOrderSamples($params);
    }

    public function hookDisplayCustomerAccount($params): string
    {
        $this->context->smarty->assign([
            'fs_my_samples_url' => $this->context->link->getModuleLink($this->name, 'mysamples'),
            'fs_my_coupons_url' => $this->context->link->getModuleLink($this->name, 'mycoupons'),
        ]);
        return $this->fetch('module:' . $this->name . '/views/templates/hook/my_account_link.tpl');
    }

    public function hookDisplayMyAccountBlock($params): string
    {
        return $this->hookDisplayCustomerAccount($params);
    }

    public function hookActionObjectProductDeleteAfter($params): void
    {
        $product = $params['object'] ?? null;
        if (Validate::isLoadedObject($product)) {
            $this->productRepository()->deleteByProduct((int) $product->id);
        }
    }

    public function hookActionObjectOrderDeleteAfter($params): void
    {
        $order = $params['object'] ?? null;
        $idOrder = is_object($order) ? (int) ($order->id ?? 0) : (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            return;
        }

        try {
            $this->stockLifecycleService()->releaseOrder($idOrder, 'actionObjectOrderDeleteAfter');
            $coupon = $this->couponRepository()->findByOrder($idOrder);
            if ($coupon !== []) {
                $idCartRule = (int) ($coupon['id_cart_rule'] ?? 0);
                if ($idCartRule > 0) {
                    $rule = new CartRule($idCartRule);
                    if (Validate::isLoadedObject($rule)) {
                        $rule->delete();
                    }
                }
                $this->couponRepository()->deleteByOrder($idOrder);
            }
            $this->stockMovementRepository()->deleteByOrder($idOrder);
            $this->orderRepository()->deleteByOrder($idOrder);
            Db::getInstance()->delete('fabricssamples_conversion', 'id_sample_order=' . $idOrder . ' OR id_purchase_order=' . $idOrder);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                sprintf('fabricssamples: no se pudo limpiar el pedido eliminado %d: %s', $idOrder, $exception->getMessage()),
                2,
                null,
                'Order',
                $idOrder,
                true
            );
        }
    }

    public function getSamplesControllerUrl(array $params = []): string
    {
        return $this->context->link->getModuleLink($this->name, 'samples', $params, true);
    }

    public function getSampleConfig($idProduct, $idShop = null): array
    {
        $idShop = $idShop ?: (int) $this->context->shop->id;
        return $this->productRepository()->findActive((int) $idProduct, (int) $idShop);
    }

    public function isSampleAvailable(array $config, $idProduct): bool
    {
        return $this->availabilityService()->isAvailable($config, (int) $idProduct);
    }

    public function getSamplePrice(array $config, $taxIncl = true): float
    {
        return $this->pricingService()->getPrice($config, (bool) $taxIncl);
    }

    public function getSampleTaxRate(array $config): float
    {
        return $this->pricingService()->getTaxRate($config);
    }

    public function getCartSampleTotal($idCart = 0): int
    {
        $idCart = (int) ($idCart ?: ($this->context->cart->id ?? 0));
        return $idCart > 0 ? $this->cartRepository()->totalQuantity($idCart) : 0;
    }

    public function getCartSampleByCustomization($idCustomization, $idCart = 0): array
    {
        return $this->cartRepository()->findByCustomization((int) $idCustomization, (int) $idCart);
    }

    public function addSampleToCart($idProduct, $idProductAttribute = 0, $quantity = 1): int
    {
        return $this->cartService()->addSample((int) $idProduct, (int) $idProductAttribute, (int) $quantity);
    }

    public function validateSampleQuantity($idProduct, $quantity, array $config): void
    {
        $this->cartService()->validateQuantity((int) $idProduct, (int) $quantity, $config);
    }

    public function removeSampleFromCart($idCustomization): void
    {
        $this->cartService()->removeSample((int) $idCustomization);
    }

    public function updateSampleQuantity($idCustomization, $direction): int
    {
        return $this->cartService()->updateQuantity((int) $idCustomization, (string) $direction);
    }

    public function repairOrderHistory(int $idOrder): array
    {
        return $this->orderSampleService()->synchronizeOrderById($idOrder);
    }

    public function repairCustomerHistory(int $idCustomer, int $idShop): int
    {
        return $this->orderSampleService()->synchronizeCustomerOrders($idCustomer, $idShop);
    }

    public function repairAllOrderHistory(int $idShop = 0): int
    {
        return $this->orderSampleService()->synchronizeAllOrders($idShop);
    }

    public function countMissingOrderHistory(int $idShop = 0): int
    {
        return $this->orderRepository()->countMissingNativeHistory($idShop);
    }

    /** @return array{before:int,repaired:int,remaining:int,success:bool,attempted:int,dropped_indexes:list<string>,failures:list<array<string,mixed>>} */
    public function diagnosticRepairOrderHistory(int $idShop = 0): array
    {
        $before = $this->countMissingOrderHistory($idShop);
        $result = $this->orderSampleService()->forceSynchronizeMissing($idShop);
        $remaining = $this->countMissingOrderHistory($idShop);

        return [
            'before' => $before,
            'repaired' => max((int) ($result['repaired'] ?? 0), max(0, $before - $remaining)),
            'remaining' => $remaining,
            'success' => $remaining === 0,
            'attempted' => (int) ($result['attempted'] ?? 0),
            'dropped_indexes' => is_array($result['dropped_indexes'] ?? null) ? $result['dropped_indexes'] : [],
            'failures' => is_array($result['failures'] ?? null) ? $result['failures'] : [],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function diagnosticMissingOrderHistoryRows(int $idShop = 0, int $limit = 100): array
    {
        try {
            return $this->orderRepository()->findMissingNativeHistory($idShop, $limit);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('fabricssamples diagnostic history list: ' . $exception->getMessage(), 2);
            return [];
        }
    }

    public function countIgnoredOrderHistory(int $idShop = 0): int
    {
        try {
            return $this->orderRepository()->countIgnoredNativeHistory($idShop);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('fabricssamples diagnostic ignored history: ' . $exception->getMessage(), 2);
            return 0;
        }
    }

    /** @return array{ignored:int,error:string} */
    public function diagnosticIgnoreMissingOrderHistory(int $idShop = 0): array
    {
        return $this->orderRepository()->ignoreMissingNativeHistory(
            $idShop,
            (int) ($this->context->employee->id ?? 0)
        );
    }

    public function diagnosticClearIgnoredOrderHistory(int $idShop = 0): int
    {
        return $this->orderRepository()->clearHistoryExclusions($idShop);
    }

    public function generateEligibleCouponsForCustomer(int $idCustomer, int $idShop): int
    {
        if (!$this->configuration()->getBool('COUPON_ENABLED') || $idCustomer <= 0) {
            return 0;
        }
        $created = 0;
        foreach ($this->orderRepository()->orderIdsByCustomer($idCustomer, $idShop) as $idOrder) {
            $before = $this->couponRepository()->findByOrder($idOrder);
            try {
                $after = $this->couponLifecycleService()->synchronizeOrderById($idOrder, null, 'customer_rebuild');
                if ($before === [] && $after !== []) {
                    ++$created;
                }
            } catch (Throwable $exception) {
                PrestaShopLogger::addLog('fabricssamples: ciclo histórico de cupón no sincronizado: ' . $exception->getMessage(), 2);
            }
        }
        return $created;
    }

    /** @return array{reissue:array<string,mixed>,email_sent:bool,email_error:bool} */
    public function issueReplacementCoupon(
        int $idCoupon,
        int $idEmployee,
        string $employeeName,
        bool $sendEmail
    ): array
    {
        $coupon = $this->couponRepository()->findById($idCoupon);
        if ($coupon === []) {
            throw new RuntimeException('El cupón solicitado no existe.');
        }

        return $this->couponService()->issueReplacement(
            $coupon,
            $idEmployee,
            $employeeName,
            $sendEmail
        );
    }

    /** @param list<int> $couponIds
     *  @return array<int,list<array<string,mixed>>>
     */
    public function couponReissues(array $couponIds): array
    {
        $grouped = $this->couponReissueRepository()->findGroupedByCoupons($couponIds);
        foreach ($grouped as $idCoupon => $rows) {
            $grouped[$idCoupon] = $this->couponService()->refreshReissueStates($rows);
        }

        return $grouped;
    }

    /** @return array{history:int,coupons:int} */
    public function rebuildHistoryAndCoupons(int $idShop = 0): array
    {
        $this->purgeOrphanOrderData();
        $history = $this->repairAllOrderHistory($idShop);
        $orderRows = Db::getInstance()->executeS(
            'SELECT DISTINCT id_order FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
            . ($idShop > 0 ? ' WHERE id_shop=' . $idShop : '')
        );
        foreach (is_array($orderRows) ? $orderRows : [] as $orderRow) {
            $idOrder = (int) ($orderRow['id_order'] ?? 0);
            if ($idOrder > 0) {
                $this->stockLifecycleService()->reconcileOrderById($idOrder, null, 'manual_rebuild');
                $this->couponLifecycleService()->synchronizeOrderById($idOrder, null, 'manual_rebuild');
            }
        }
        $coupons = 0;
        if ($this->configuration()->getBool('COUPON_ENABLED')) {
            $where = ' WHERE id_customer>0' . ($idShop > 0 ? ' AND id_shop=' . $idShop : '');
            $customers = Db::getInstance()->executeS(
                'SELECT DISTINCT id_customer,id_shop FROM `' . _DB_PREFIX_ . 'fabricssamples_order`' . $where
            );
            foreach (is_array($customers) ? $customers : [] as $customer) {
                $coupons += $this->generateEligibleCouponsForCustomer((int) $customer['id_customer'], (int) $customer['id_shop']);
            }
        }
        return ['history' => $history, 'coupons' => $coupons];
    }

    /** @return array{carts:int,repaired:int,removed:int,errors:int} */
    public function diagnosticRepairSampleCarts(int $idShop = 0): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT fsc.id_cart FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'cart` c ON c.id_cart=fsc.id_cart'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_cart=fsc.id_cart'
            . ' WHERE o.id_order IS NULL'
            . ($idShop > 0 ? ' AND fsc.id_shop=' . $idShop : '')
            . ' ORDER BY fsc.id_cart ASC'
        );
        $result = ['carts' => 0, 'repaired' => 0, 'removed' => 0, 'errors' => 0];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $cart = new Cart((int) ($row['id_cart'] ?? 0));
            if (!Validate::isLoadedObject($cart)) {
                continue;
            }
            ++$result['carts'];
            $report = $this->cartIntegrityService()->repairCart($cart, false);
            $result['repaired'] += $report->repairedCount();
            $result['removed'] += $report->removedCount();
            $result['errors'] += count($report->errors());
        }
        return $result;
    }

    public function diagnosticReconcileAllStock(int $idShop = 0): int
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT fso.id_order FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
            . ($idShop > 0 ? ' WHERE fso.id_shop=' . $idShop : '')
            . ' ORDER BY fso.id_order ASC'
        );
        $changes = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            try {
                $changes += $this->stockLifecycleService()->reconcileOrderById(
                    (int) ($row['id_order'] ?? 0),
                    null,
                    'diagnostic_reconcile'
                );
            } catch (Throwable $exception) {
                PrestaShopLogger::addLog('fabricssamples diagnostic stock: ' . $exception->getMessage(), 2);
            }
        }
        return $changes;
    }

    public function diagnosticSynchronizeAllCoupons(int $idShop = 0): int
    {
        $where = $idShop > 0 ? ' WHERE o.id_shop=' . $idShop : '';
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT o.id_order FROM `' . _DB_PREFIX_ . 'orders` o'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_order` fso ON fso.id_order=o.id_order'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order=o.id_order'
            . $where
            . ($where === '' ? ' WHERE ' : ' AND ')
            . "(fso.id_order IS NOT NULL OR od.product_name LIKE 'Muestra - %' OR od.product_name LIKE 'Muestra – %')"
            . ' ORDER BY o.id_order ASC'
        );
        $synchronized = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $idOrder = (int) ($row['id_order'] ?? 0);
            if ($idOrder <= 0) {
                continue;
            }
            try {
                $before = $this->couponRepository()->findByOrder($idOrder);
                $after = $this->couponLifecycleService()->synchronizeOrderById($idOrder, null, 'diagnostic_sync');
                if ($after !== [] || $before !== []) {
                    ++$synchronized;
                }
            } catch (Throwable $exception) {
                PrestaShopLogger::addLog('fabricssamples diagnostic coupon: ' . $exception->getMessage(), 2);
            }
        }
        return $synchronized;
    }

    /** @return array{cart_rows:int,product_rows:int,order_rows:int,coupon_suppressions:int,stock_movements:int} */
    public function diagnosticCleanOrphans(int $idShop = 0): array
    {
        $db = Db::getInstance();
        $shopCart = $idShop > 0 ? ' AND fsc.id_shop=' . $idShop : '';
        $shopProduct = $idShop > 0 ? ' AND fsp.id_shop=' . $idShop : '';
        $shopOrder = $idShop > 0 ? ' AND fso.id_shop=' . $idShop : '';
        $shopSuppression = $idShop > 0 ? ' AND s.id_shop=' . $idShop : '';
        $shopMovement = $idShop > 0 ? ' AND sm.id_shop=' . $idShop : '';
        $shopLimitException = $idShop > 0 ? ' AND le.id_shop=' . $idShop : '';
        $shopLimitReset = $idShop > 0 ? ' AND lr.id_shop=' . $idShop : '';

        $count = static function (string $sql) use ($db): int {
            $rows = $db->executeS($sql);
            return (int) ($rows[0]['total'] ?? 0);
        };

        $result = [
            'cart_rows' => $count(
                'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart` c ON c.id_cart=fsc.id_cart'
                . ' WHERE c.id_cart IS NULL' . $shopCart
            ),
            'product_rows' => $count(
                'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_product` fsp'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product=fsp.id_product'
                . ' WHERE p.id_product IS NULL' . $shopProduct
            ),
            'order_rows' => $count(
                'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
                . ' WHERE o.id_order IS NULL' . $shopOrder
            ),
            'coupon_suppressions' => $count(
                'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_suppression` s'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=s.id_order'
                . ' WHERE o.id_order IS NULL' . $shopSuppression
            ),
            'stock_movements' => $count(
                'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement` sm'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=sm.id_order'
                . ' WHERE sm.id_order>0 AND o.id_order IS NULL' . $shopMovement
            ),
            'limit_exceptions' => $count(
                'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_exception` le'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON le.target_type=\'customer\' AND c.id_customer=le.target_id'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'group` g ON le.target_type=\'group\' AND g.id_group=le.target_id'
                . ' WHERE ((le.target_type=\'customer\' AND c.id_customer IS NULL)'
                . ' OR (le.target_type=\'group\' AND g.id_group IS NULL))' . $shopLimitException
            ),
            'limit_resets' => $count(
                'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_reset` lr'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer=lr.id_customer'
                . ' WHERE c.id_customer IS NULL' . $shopLimitReset
            ),
        ];

        $this->purgeOrphanOrderData();
        $db->execute(
            'DELETE fsc FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart` c ON c.id_cart=fsc.id_cart'
            . ' WHERE c.id_cart IS NULL' . $shopCart
        );
        $db->execute(
            'DELETE fsp FROM `' . _DB_PREFIX_ . 'fabricssamples_product` fsp'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product=fsp.id_product'
            . ' WHERE p.id_product IS NULL' . $shopProduct
        );
        $db->execute(
            'DELETE fspl FROM `' . _DB_PREFIX_ . 'fabricssamples_product_lang` fspl'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_product` fsp'
            . ' ON fsp.id_fabricssamples_product=fspl.id_fabricssamples_product'
            . ' WHERE fsp.id_fabricssamples_product IS NULL'
        );
        $db->execute(
            'DELETE s FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_suppression` s'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=s.id_order'
            . ' WHERE o.id_order IS NULL' . $shopSuppression
        );
        $db->execute(
            'DELETE sm FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement` sm'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=sm.id_order'
            . ' WHERE sm.id_order>0 AND o.id_order IS NULL' . $shopMovement
        );
        $db->execute(
            'DELETE le FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_exception` le'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON le.target_type=\'customer\' AND c.id_customer=le.target_id'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'group` g ON le.target_type=\'group\' AND g.id_group=le.target_id'
            . ' WHERE ((le.target_type=\'customer\' AND c.id_customer IS NULL)'
            . ' OR (le.target_type=\'group\' AND g.id_group IS NULL))' . $shopLimitException
        );
        $db->execute(
            'DELETE lr FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_reset` lr'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer=lr.id_customer'
            . ' WHERE c.id_customer IS NULL' . $shopLimitReset
        );

        return $result;
    }

    /**
     * Removes every piece of data owned by the module and recreates the fresh schema.
     *
     * The module registration itself is deliberately preserved so the employee can stay
     * on the diagnostics page while the data, configuration, native sample cart lines,
     * generated cart rules and snapshots are reset.
     *
     * @return array{success:bool,steps:array<string,string>,errors:list<string>,warnings:list<string>,backup:array<string,mixed>}
     */
    public function diagnosticResetModule(): array
    {
        $details = [
            'success' => false,
            'steps' => [],
            'errors' => [],
            'warnings' => [],
            'backup' => [],
        ];

        $runStep = function (string $name, callable $callback, bool $fatal = true) use (&$details): bool {
            try {
                $result = $callback();
                if ($result === false) {
                    throw new RuntimeException('La operación devolvió false.');
                }
                $details['steps'][$name] = 'ok';
                return true;
            } catch (Throwable $exception) {
                $message = $name . ': ' . $exception->getMessage();
                $details['steps'][$name] = 'error';
                if ($fatal) {
                    $details['errors'][] = $message;
                } else {
                    $details['warnings'][] = $message;
                }
                PrestaShopLogger::addLog(
                    'fabricssamples reset ' . $message,
                    $fatal ? 3 : 2,
                    null,
                    'Module',
                    (int) $this->id,
                    true
                );
                return false;
            }
        };

        // A reset must never start without a complete, access-controlled snapshot.
        // If backup creation fails, no module or native data has been touched yet.
        try {
            $details['backup'] = (new ResetBackupService($this))->create();
            $details['steps']['create_backup'] = 'ok';
        } catch (Throwable $exception) {
            $details['steps']['create_backup'] = 'error';
            $details['errors'][] = 'create_backup: ' . $exception->getMessage();
            PrestaShopLogger::addLog(
                'fabricssamples reset create_backup: ' . $exception->getMessage(),
                3,
                null,
                'Module',
                (int) $this->id,
                true
            );
            return $details;
        }

        // These native records must be removed before dropping the module tables, because
        // the module rows contain the identifiers required to locate them safely.
        foreach ([
            'native_sample_cart_rows' => fn () => $this->purgeSampleCartRowsDirectly(),
            'native_sample_cart_rules' => fn () => $this->purgeSampleCartRulesDirectly(),
            'order_image_snapshots' => fn () => $this->imageSnapshotService()->purge(),
            'custom_catalog_images' => fn () => $this->purgeCustomCatalogImagesDirectly(),
        ] as $stepName => $callback) {
            if (!$runStep($stepName, $callback)) {
                return $details;
            }
        }

        // DDL is not transaction-safe on every supported MySQL/MariaDB version. Each
        // operation is therefore idempotent and the rebuild is attempted even if a later
        // cleanup detail fails, leaving the module in an installable state.
        $runStep('drop_module_tables', fn () => $this->executeSqlFile('uninstall.sql', false));
        $runStep('delete_module_configuration', fn () => $this->purgeModuleConfigurationDirectly());

        $schemaInstalled = $runStep(
            'create_fresh_schema',
            fn () => $this->executeSqlFile('install.sql', true)
        );
        $defaultsInstalled = $runStep(
            'install_default_configuration',
            fn () => $this->configuration()->installDefaults()
        );

        if ($schemaInstalled && $defaultsInstalled) {
            $runStep(
                'record_fresh_migration',
                fn () => (new MigrationManager(
                    $this,
                    new SchemaInspector($this->getLocalPath() . 'sql/install.sql')
                ))->recordFreshInstall(self::VERSION)
            );
        }

        $runStep('clear_cache', function (): bool {
            if (method_exists('Tools', 'clearAllCache')) {
                Tools::clearAllCache();
            }
            if (class_exists('Cache')) {
                Cache::clean('*');
            }
            return true;
        }, false);

        $details['success'] = $details['errors'] === [];
        return $details;
    }

    public function purgeOrphanOrderData(): void
    {
        try {
            $orphanOrders = Db::getInstance()->executeS(
                'SELECT DISTINCT fso.id_order FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
                . ' WHERE o.id_order IS NULL'
            );
            foreach (is_array($orphanOrders) ? $orphanOrders : [] as $orphanOrder) {
                $orphanId = (int) ($orphanOrder['id_order'] ?? 0);
                if ($orphanId > 0) {
                    $this->stockLifecycleService()->releaseOrder($orphanId, 'orphan_cleanup');
                    $this->stockMovementRepository()->deleteByOrder($orphanId);
                }
            }
            foreach ($this->couponRepository()->findOrphaned() as $coupon) {
                $idCoupon = (int) ($coupon['id_fabricssamples_coupon'] ?? 0);
                $ruleIds = array_values(array_unique(array_filter(array_merge(
                    [(int) ($coupon['id_cart_rule'] ?? 0)],
                    $this->couponReissueRepository()->cartRuleIds($idCoupon)
                ))));
                foreach ($ruleIds as $idCartRule) {
                    $rule = new CartRule((int) $idCartRule);
                    if (Validate::isLoadedObject($rule)) {
                        $rule->delete();
                    }
                }
                $this->couponReissueRepository()->deleteByCoupon($idCoupon);
                $this->couponRepository()->deleteById($idCoupon);
            }
            $this->orderRepository()->purgeOrphans();
            Db::getInstance()->execute(
                'DELETE c FROM `' . _DB_PREFIX_ . 'fabricssamples_conversion` c'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` so ON so.id_order=c.id_sample_order'
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` po ON po.id_order=c.id_purchase_order'
                . ' WHERE so.id_order IS NULL OR po.id_order IS NULL'
            );
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('fabricssamples: limpieza de datos huérfanos fallida: ' . $exception->getMessage(), 2);
        }
    }

    private function renderOrderSamples(int $idOrder, bool $admin): string
    {
        if ($idOrder <= 0) {
            return '';
        }

        $rows = $this->orderSampleService()->synchronizeOrderById($idOrder);
        if ($rows === []) {
            $rows = $this->orderRepository()->findForCouponByOrder($idOrder);
        }
        $rows = $this->prepareHistoricalRows($rows);
        if ($rows === []) {
            return '';
        }

        $order = new Order($idOrder);
        $coupon = [];
        if (Validate::isLoadedObject($order)) {
            try {
                $coupon = $this->couponLifecycleService()->synchronizeOrder($order, null, 'render_order');
            } catch (Throwable) {
                $coupon = $this->couponRepository()->findByOrder($idOrder);
            }
        }
        $coupon = $this->prepareCoupon($coupon, Validate::isLoadedObject($order) ? (int) $order->id_currency : 0);

        $this->context->smarty->assign([
            'fs_order_samples' => $rows,
            'fs_order_coupon' => $coupon,
            'fs_admin' => $admin,
        ]);
        return $this->fetch('module:' . $this->name . '/views/templates/hook/order_samples.tpl');
    }

    private function renderPdfOrderSamples(array $params): string
    {
        $object = $params['object'] ?? null;
        $idOrder = 0;
        if (is_object($object) && isset($object->id_order)) {
            $idOrder = (int) $object->id_order;
        } elseif (is_object($object) && isset($object->order) && Validate::isLoadedObject($object->order)) {
            $idOrder = (int) $object->order->id;
        }
        if ($idOrder <= 0) {
            return '';
        }

        $rows = $this->orderSampleService()->synchronizeOrderById($idOrder);
        if ($rows === []) {
            $rows = $this->orderRepository()->findForCouponByOrder($idOrder);
        }
        $rows = $this->prepareHistoricalRows($rows);
        if ($rows === []) {
            return '';
        }
        $this->context->smarty->assign(['fs_order_samples' => $rows]);
        return $this->fetch('module:' . $this->name . '/views/templates/pdf/order_samples.tpl');
    }

    private function prepareHistoricalRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['product_name'] = $this->nameFormatter()->format((string) ($row['product_name'] ?? ''));
            $row['image_url'] = $this->imageSnapshotService()->url((string) ($row['image_snapshot'] ?? ''));
            $currency = !empty($row['id_currency']) ? new Currency((int) $row['id_currency']) : $this->context->currency;
            $row['unit_price_formatted'] = PriceFormatter::format((float) $row['unit_price_tax_incl'], $currency);
            $row['total_price_formatted'] = PriceFormatter::format((float) ($row['total_price_tax_incl'] ?? ((float) $row['unit_price_tax_incl'] * (int) $row['quantity'])), $currency);
        }
        unset($row);
        return $rows;
    }

    private function prepareCoupon(array $coupon, int $idCurrency): array
    {
        if ($coupon === []) {
            return [];
        }
        $currency = $idCurrency > 0 ? new Currency($idCurrency) : $this->context->currency;
        $coupon['discount_value_formatted'] = PriceFormatter::format((float) $coupon['discount_value'], $currency);
        $coupon['minimum_order_formatted'] = PriceFormatter::format((float) $coupon['minimum_order'], $currency);
        return $coupon;
    }

    private function isOrderPaid(Order $order): bool
    {
        try {
            $state = new OrderState((int) $order->current_state);
            return Validate::isLoadedObject($state) && !empty($state->paid);
        } catch (Throwable) {
            return false;
        }
    }

    private function assignSampleTemplate(int $idProduct, array $config): void
    {
        $idLang = (int) $this->context->language->id;
        $this->context->smarty->assign([
            'fs_id_product' => $idProduct,
            'fs_product_block_title' => $this->configuration()->getString('PRODUCT_BLOCK_TITLE', $idLang),
            'fs_product_block_text' => $this->configuration()->getString('PRODUCT_BLOCK_TEXT', $idLang),
            'fs_product_block_button' => $this->configuration()->getString('PRODUCT_BLOCK_BUTTON', $idLang),
            'fs_listing_button_text' => $this->configuration()->getString('LISTING_BUTTON_TEXT', $idLang),
            'fs_product_button_style' => $this->buttonStyles()->style('PRODUCT_BLOCK_BUTTON'),
            'fs_listing_button_style' => $this->buttonStyles()->style('LISTING_BUTTON'),
            'fs_catalog_url' => $this->getSamplesControllerUrl(),
            'fs_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], true),
            'fs_token' => Tools::getToken(false),
        ]);
    }

    private function extractProductId(array $params): int
    {
        if (!empty($params['product']['id_product'])) {
            return (int) $params['product']['id_product'];
        }
        if (!empty($params['product']['id'])) {
            return (int) $params['product']['id'];
        }
        if (!empty($params['product']->id)) {
            return (int) $params['product']->id;
        }
        return (int) Tools::getValue('id_product');
    }

    private function appendUrlParameters(string $url, array $parameters): string
    {
        if ($url === '' || $parameters === []) {
            return $url;
        }

        $fragment = '';
        $hashPosition = strpos($url, '#');
        if ($hashPosition !== false) {
            $fragment = substr($url, $hashPosition);
            $url = substr($url, 0, $hashPosition);
        }

        return $url
            . (strpos($url, '?') === false ? '?' : '&')
            . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)
            . $fragment;
    }

    private function executeSqlFile(string $filename, bool $install): bool
    {
        $replacements = $install
            ? ['PREFIX_' => _DB_PREFIX_, 'ENGINE_TYPE' => _MYSQL_ENGINE_]
            : ['PREFIX_' => _DB_PREFIX_];

        return (new SqlFileExecutor())->execute(__DIR__ . '/sql/' . $filename, $replacements);
    }

    private function installTabs(): bool
    {
        // Do not use Tab::getIdFromClassName() here. Some customized PrestaShop
        // installations route that lookup through Db::getValue() with an existing
        // LIMIT clause, which can become "LIMIT 1 LIMIT 1" on MariaDB.
        $this->removeStaleTabsBeforeInstall();

        $parent = new Tab();
        $parent->active = 1;
        $parent->class_name = 'AdminFabricSamplesParent';
        $parent->module = $this->name;
        $parent->id_parent = 0;
        foreach (Language::getLanguages(false) as $lang) {
            $parent->name[(int) $lang['id_lang']] = 'Muestras de tejidos';
        }
        if (!$parent->add()) {
            return false;
        }

        $tabs = [
            'AdminFabricSamples' => 'Productos y configuración',
            'AdminFabricSamplesCoupons' => 'Cupones de muestras',
            'AdminFabricSamplesStock' => 'Stock de muestras',
            'AdminFabricSamplesLimits' => 'Límites y excepciones',
            'AdminFabricSamplesAudit' => 'Auditoría',
            'AdminFabricSamplesDiagnostics' => 'Diagnóstico',
        ];
        foreach ($tabs as $className => $label) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = $className;
            $tab->module = $this->name;
            $tab->id_parent = (int) $parent->id;
            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[(int) $lang['id_lang']] = $label;
            }
            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    private function removeStaleTabsBeforeInstall(): void
    {
        $db = Db::getInstance();
        if (!$this->databaseTableExists(_DB_PREFIX_ . 'tab')) {
            return;
        }

        $classes = [
            'AdminFabricSamplesAudit',
            'AdminFabricSamplesLimits',
            'AdminFabricSamplesStock',
            'AdminFabricSamplesDiagnostics',
            'AdminFabricSamplesCoupons',
            'AdminFabricSamples',
            'AdminFabricSamplesParent',
        ];
        $quoted = implode(',', array_map(
            static fn (string $class): string => "'" . pSQL($class) . "'",
            $classes
        ));
        $rows = $db->executeS(
            'SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab`'
            . ' WHERE class_name IN (' . $quoted . ') OR module=\'' . pSQL($this->name) . '\''
        );
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            array_column(is_array($rows) ? $rows : [], 'id_tab')
        ))));
        if ($ids === []) {
            return;
        }

        $in = implode(',', $ids);
        foreach (['tab_lang', 'tab_shop', 'tab'] as $table) {
            if ($this->databaseTableExists(_DB_PREFIX_ . $table)) {
                $db->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . bqSQL($table) . '` WHERE id_tab IN (' . $in . ')'
                );
            }
        }
    }

    private function registerCompatibilityHooks(): bool
    {
        foreach (['actionValidateOrderBefore'] as $hookName) {
            if ((int) Hook::getIdByName($hookName) <= 0) {
                continue;
            }
            if (!$this->isRegisteredInHook($hookName) && !$this->registerHook($hookName)) {
                return false;
            }
        }

        return true;
    }

    private function purgeCustomCatalogImagesDirectly(): bool
    {
        $directory = $this->getLocalPath() . 'views/img/custom/';
        if (!is_dir($directory)) {
            return true;
        }

        $ok = true;
        foreach (glob($directory . '*') ?: [] as $file) {
            if (!is_file($file) || basename($file) === 'index.php') {
                continue;
            }
            $ok = @unlink($file) && $ok;
        }

        return $ok;
    }

    private function configuration(): ModuleConfiguration
    {
        return $this->moduleConfiguration ??= new ModuleConfiguration();
    }

    private function privacyService(): PrivacyService
    {
        return $this->privacyService ??= new PrivacyService($this->configuration());
    }

    private function productRepository(): SampleProductRepository
    {
        return $this->sampleProductRepository ??= new SampleProductRepository();
    }

    private function cartRepository(): CartSampleRepository
    {
        return $this->cartSampleRepository ??= new CartSampleRepository();
    }


    private function customerLimitRepository(): CustomerLimitRepository
    {
        return $this->customerLimitRepository ??= new CustomerLimitRepository();
    }

    private function limitPolicy(): LimitPolicy
    {
        return $this->limitPolicy ??= new LimitPolicy();
    }

    private function couponValuePolicy(): CouponValuePolicy
    {
        return $this->couponValuePolicy ??= new CouponValuePolicy();
    }

    private function couponTransitionPolicy(): CouponTransitionPolicy
    {
        return $this->couponTransitionPolicy ??= new CouponTransitionPolicy();
    }

    private function stockReconciliationPolicy(): StockReconciliationPolicy
    {
        return $this->stockReconciliationPolicy ??= new StockReconciliationPolicy();
    }
    private function orderRepository(): OrderSampleRepository
    {
        return $this->orderSampleRepository ??= new OrderSampleRepository();
    }

    private function couponRepository(): CouponRepository
    {
        return $this->couponRepository ??= new CouponRepository();
    }

    private function couponReissueRepository(): CouponReissueRepository
    {
        return $this->couponReissueRepository ??= new CouponReissueRepository();
    }

    private function conversionRepository(): ConversionRepository
    {
        return $this->conversionRepository ??= new ConversionRepository();
    }

    private function stockMovementRepository(): StockMovementRepository
    {
        return $this->stockMovementRepository ??= new StockMovementRepository();
    }

    private function limitExceptionRepository(): LimitExceptionRepository
    {
        return $this->limitExceptionRepository ??= new LimitExceptionRepository();
    }

    private function limitExceptionPolicy(): LimitExceptionPolicy
    {
        return $this->limitExceptionPolicy ??= new LimitExceptionPolicy();
    }

    private function availabilityService(): SampleAvailabilityService
    {
        return $this->sampleAvailabilityService ??= new SampleAvailabilityService($this->configuration());
    }

    private function pricingService(): SamplePricingService
    {
        return $this->samplePricingService ??= new SamplePricingService($this->context, $this->configuration());
    }

    private function cartService(): SampleCartService
    {
        return $this->sampleCartService ??= new SampleCartService(
            $this,
            $this->context,
            $this->configuration(),
            $this->productRepository(),
            $this->cartRepository(),
            $this->customerLimitRepository(),
            $this->limitExceptionRepository(),
            $this->availabilityService(),
            $this->pricingService(),
            $this->limitPolicy(),
            $this->limitExceptionPolicy(),
            $this->nativeCartAdapter(),
            $this->cartIntegrityService(),
            $this->productAttributeResolver(),
            $this->databaseLock()
        );
    }

    private function nativeCartAdapter(): NativeCartAdapterInterface
    {
        return $this->nativeCartAdapter ??= NativeCartAdapterFactory::forCurrentPlatform();
    }

    private function cartIntegrityService(): CartIntegrityService
    {
        return $this->cartIntegrityService ??= new CartIntegrityService(
            $this,
            $this->cartRepository(),
            $this->nativeCartAdapter(),
            $this->productAttributeResolver()
        );
    }

    private function productAttributeResolver(): ProductAttributeResolver
    {
        return $this->productAttributeResolver ??= new ProductAttributeResolver();
    }

    private function cartSamplePriceProvider(): CartSamplePriceProvider
    {
        return $this->cartSamplePriceProvider ??= new CartSamplePriceProvider($this->cartRepository());
    }

    private function orderSampleService(): OrderSampleService
    {
        return $this->orderSampleService ??= new OrderSampleService(
            $this->cartRepository(),
            $this->orderRepository(),
            $this->productRepository(),
            $this->nameFormatter(),
            $this->imageSnapshotService(),
            $this->context
        );
    }

    private function couponService(): CouponService
    {
        return $this->couponService ??= new CouponService(
            $this,
            $this->configuration(),
            $this->couponRepository(),
            $this->couponReissueRepository(),
            $this->couponValuePolicy(),
            $this->couponReactivationPolicy(),
            $this->couponReissuePolicy(),
            $this->databaseLock()
        );
    }

    private function couponLifecycleService(): CouponLifecycleService
    {
        return $this->couponLifecycleService ??= new CouponLifecycleService(
            $this->configuration(),
            $this->orderRepository(),
            $this->couponRepository(),
            $this->couponService(),
            $this->couponTransitionPolicy()
        );
    }

    private function stockLifecycleService(): StockLifecycleService
    {
        return $this->stockLifecycleService ??= new StockLifecycleService(
            $this->configuration(),
            $this->orderRepository(),
            $this->productRepository(),
            $this->stockMovementRepository(),
            $this->stockReconciliationPolicy()
        );
    }

    private function conversionTrackingService(): ConversionTrackingService
    {
        return $this->conversionTrackingService ??= new ConversionTrackingService($this->conversionRepository());
    }

    private function imageSnapshotService(): ImageSnapshotService
    {
        return $this->imageSnapshotService ??= new ImageSnapshotService($this);
    }

    private function couponReactivationPolicy(): CouponReactivationPolicy
    {
        return $this->couponReactivationPolicy ??= new CouponReactivationPolicy();
    }

    private function couponReissuePolicy(): CouponReissuePolicy
    {
        return $this->couponReissuePolicy ??= new CouponReissuePolicy();
    }

    private function databaseLock(): DatabaseLock
    {
        return $this->databaseLock ??= new DatabaseLock();
    }

    private function buttonStyles(): ButtonStyleProvider
    {
        return $this->buttonStyleProvider ??= new ButtonStyleProvider($this->configuration());
    }

    private function accessor(): PresentedDataAccessor
    {
        return $this->presentedDataAccessor ??= new PresentedDataAccessor();
    }

    private function nameFormatter(): SampleNameFormatter
    {
        return $this->sampleNameFormatter ??= new SampleNameFormatter($this);
    }
}
