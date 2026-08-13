<?php

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Presentation\ButtonStyleProvider;
use NaranjaCreativos\FabricSamples\Presentation\PaginationWindow;
use NaranjaCreativos\FabricSamples\Presentation\PriceFormatter;
use NaranjaCreativos\FabricSamples\Repository\CartSampleRepository;
use NaranjaCreativos\FabricSamples\Repository\CatalogSampleRepository;
use NaranjaCreativos\FabricSamples\Security\CssSanitizer;
use NaranjaCreativos\FabricSamples\Security\HtmlSanitizer;

class FabricssamplesSamplesModuleFrontController extends ModuleFrontController
{
    private ?CatalogSampleRepository $catalogRepository = null;
    private ?CartSampleRepository $cartRepository = null;
    private ?ModuleConfiguration $moduleConfiguration = null;
    private ?ButtonStyleProvider $buttonStyleProvider = null;

    public function postProcess()
    {
        if (!Tools::isSubmit('submitFabricSamples')) {
            return parent::postProcess();
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->errors[] = $this->module->l('Esta operación solo admite solicitudes POST.');
            return;
        }

        if (!Tools::getValue('token') || !hash_equals(Tools::getToken(false), (string) Tools::getValue('token'))) {
            $this->errors[] = $this->module->l('El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.');
            return;
        }

        $products = Tools::getValue('products', []);
        if (!is_array($products) || $products === []) {
            $this->errors[] = $this->module->l('Selecciona al menos una muestra.');
            return;
        }

        $productIds = array_values(array_unique(array_filter(array_map('intval', $products))));
        if (count($productIds) > 50) {
            $this->errors[] = $this->module->l('No se pueden procesar más de 50 muestras en una sola solicitud.');
            return;
        }

        try {
            foreach ($productIds as $idProduct) {
                $this->module->addSampleToCart($idProduct, 0, 1);
            }
            Tools::redirect($this->context->link->getPageLink('cart', true, null, ['action' => 'show']));
        } catch (Throwable $exception) {
            $reference = bin2hex(random_bytes(6));
            PrestaShopLogger::addLog('fabricssamples front [' . $reference . ']: ' . $exception->getMessage(), 2);
            $this->errors[] = sprintf($this->module->l('No se pudo completar la operación. Referencia: %s.'), $reference);
        }
    }

    public function initContent()
    {
        parent::initContent();

        $configuration = $this->configuration();
        $perPageOptions = $this->parsePerPageOptions($configuration->getString('PAGE_PER_PAGE_OPTIONS', null, '12,24,36,48,72'));
        $defaultPerPage = $configuration->getInt('PRODUCTS_PER_PAGE', 24);
        if (!in_array($defaultPerPage, $perPageOptions, true)) {
            $defaultPerPage = $perPageOptions[0];
        }
        $requestedPerPage = (int) Tools::getValue('per_page', $defaultPerPage);
        $perPage = in_array($requestedPerPage, $perPageOptions, true) ? $requestedPerPage : $defaultPerPage;
        $perPage = min(100, max(1, $perPage));

        $page = max(1, (int) Tools::getValue('page', 1));
        $search = trim((string) Tools::getValue('q', ''));
        $idCategory = max(0, (int) Tools::getValue('id_category', 0));
        $order = (string) Tools::getValue('order', 'name_asc');
        $offset = ($page - 1) * $perPage;
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        $result = $this->repository()->search(
            $idShop,
            $idLang,
            $search,
            $idCategory,
            $order,
            $offset,
            $perPage,
            $configuration->getFloat('DEFAULT_PRICE', 2.5)
        );
        $pages = max(1, (int) ceil($result['total'] / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $offset = ($page - 1) * $perPage;
            $result = $this->repository()->search(
                $idShop,
                $idLang,
                $search,
                $idCategory,
                $order,
                $offset,
                $perPage,
                $configuration->getFloat('DEFAULT_PRICE', 2.5)
            );
        }

        $cartRows = Validate::isLoadedObject($this->context->cart)
            ? $this->cartRepository()->findByCart((int) $this->context->cart->id)
            : [];
        $cartByProduct = [];
        $cartSampleCount = 0;
        foreach ($cartRows as $cartRow) {
            $cartByProduct[(int) $cartRow['id_product']] = $cartRow;
            $cartSampleCount += (int) $cartRow['quantity'];
        }

        $rows = $result['rows'];
        foreach ($rows as &$row) {
            $idProduct = (int) $row['id_product'];
            $idImage = (int) ($row['id_image'] ?? 0);
            $row['url'] = $this->context->link->getProductLink($idProduct, $row['link_rewrite']);
            $row['image'] = $idImage > 0
                ? $this->context->link->getImageLink($row['link_rewrite'], $idImage, ImageType::getFormattedName('home'))
                : '';
            // The catalog query already contains the complete sample configuration.
            // Reusing it avoids one extra database query for every displayed card.
            $sampleConfig = $row;
            $row['price_numeric'] = $this->module->getSamplePrice($sampleConfig, true);
            $row['price_formatted'] = PriceFormatter::format((float) $row['price_numeric'], $this->context->currency);
            $cartLine = $cartByProduct[$idProduct] ?? [];
            $row['in_cart_quantity'] = (int) ($cartLine['quantity'] ?? 0);
            $row['id_customization'] = (int) ($cartLine['id_customization'] ?? 0);
            $maxPerOrder = (int) (($sampleConfig['max_per_order'] ?? 0) ?: $configuration->getInt('MAX_PER_PRODUCT'));
            $maxTotal = $configuration->getInt('MAX_TOTAL');
            $row['can_add'] = ($maxPerOrder <= 0 || $row['in_cart_quantity'] < $maxPerOrder)
                && ($maxTotal <= 0 || $cartSampleCount < $maxTotal);
            $row['card_explainer_html'] = HtmlSanitizer::sanitize((string) ($row['card_explainer_html'] ?? ''));
        }
        unset($row);

        $formUrl = $this->module->getSamplesControllerUrl();
        $isAjaxCatalog = (int) Tools::getValue('ajax_catalog', 0) === 1
            && $configuration->getBool('PAGE_AJAX_FILTERS', true);
        $this->context->smarty->assign(array_merge(
            [
                'fs_products' => $rows,
                'fs_configured_total' => $this->repository()->countConfigured($idShop),
                'fs_total' => $result['total'],
                'fs_page' => $page,
                'fs_pages' => $pages,
                'fs_pagination_window' => PaginationWindow::build($page, $pages),
                'fs_q' => $search,
                'fs_id_category' => $idCategory,
                'fs_order' => $order,
                'fs_per_page' => $perPage,
                'fs_per_page_options' => $perPageOptions,
                'fs_categories' => $isAjaxCatalog ? [] : $this->repository()->categories($idShop, $idLang),
                'fs_form_url' => $formUrl,
                'fs_url_separator' => strpos($formUrl, '?') !== false ? '&' : '?',
                'fs_token' => Tools::getToken(false),
                'fs_product_link_image' => $this->getProductLinkImageUrl(),
            ],
            $this->getPageConfiguration($idLang)
        ));

        if ($isAjaxCatalog) {
            header('Content-Type: application/json; charset=utf-8');
            $html = $this->context->smarty->fetch('module:fabricssamples/views/templates/front/_catalog_results.tpl');
            $this->ajaxRender(json_encode([
                'success' => true,
                'html' => $html,
                'total' => (int) $result['total'],
                'cart_count' => $cartSampleCount,
            ]));
            return;
        }

        $metaTitle = trim($configuration->getString('META_TITLE', $idLang));
        $metaDescription = trim($configuration->getString('META_DESCRIPTION', $idLang));
        if ($metaTitle !== '') {
            $this->context->smarty->assign('page_title', $metaTitle);
        }
        $this->context->smarty->assign('fs_meta_title', $metaTitle);
        $this->context->smarty->assign('fs_meta_description', $metaDescription);
        $this->setTemplate('module:fabricssamples/views/templates/front/catalog.tpl');
    }

    /** @return array<string,mixed> */
    private function getPageConfiguration(int $idLang): array
    {
        $configuration = $this->configuration();

        return [
            'fs_intro' => HtmlSanitizer::sanitize($configuration->getString('INFO_TEXT', $idLang)),
            'fs_page_title' => $configuration->getString('PAGE_TITLE', $idLang, $this->module->l('Solicita muestras antes de comprar tu tejido')),
            'fs_page_intro_html' => HtmlSanitizer::sanitize($configuration->getString('PAGE_INTRO_HTML', $idLang)),
            'fs_page_warning' => HtmlSanitizer::sanitize($configuration->getString('PAGE_WARNING', $idLang)),
            'fs_page_accent_color' => $configuration->getString('PAGE_ACCENT_COLOR', null, '#202020'),
            'fs_page_background_color' => $configuration->getString('PAGE_BACKGROUND_COLOR', null, '#ffffff'),
            'fs_filter_button_style' => $this->buttonStyles()->style('FILTER_BUTTON'),
            'fs_add_button_style' => $this->buttonStyles()->style('ADD_BUTTON'),
            'fs_product_link_button_style' => $this->buttonStyles()->style('PRODUCT_LINK_BUTTON'),
            'fs_page_columns_desktop' => min(6, max(1, $configuration->getInt('PAGE_COLUMNS_DESKTOP', $configuration->getInt('PAGE_COLUMNS', 4)))),
            'fs_page_columns_tablet' => min(4, max(1, $configuration->getInt('PAGE_COLUMNS_TABLET', 3))),
            'fs_page_columns_mobile' => min(2, max(1, $configuration->getInt('PAGE_COLUMNS_MOBILE', 1))),
            'fs_page_custom_css' => CssSanitizer::sanitize($configuration->getString('PAGE_CUSTOM_CSS')),
            'fs_ajax_filters' => $configuration->getBool('PAGE_AJAX_FILTERS', true),
            'fs_show_result_count' => $configuration->getBool('PAGE_SHOW_RESULT_COUNT', true),
            'fs_show_in_cart_status' => $configuration->getBool('PAGE_SHOW_IN_CART_STATUS', true),
            'fs_allow_remove' => $configuration->getBool('PAGE_ALLOW_REMOVE', true),
            'fs_important_label' => $configuration->getString('IMPORTANT_LABEL', $idLang, $this->module->l('Importante:')),
            'fs_filter_search_placeholder' => $configuration->getString('FILTER_SEARCH_PLACEHOLDER', $idLang, $this->module->l('Buscar por nombre o referencia')),
            'fs_filter_all_categories' => $configuration->getString('FILTER_ALL_CATEGORIES', $idLang, $this->module->l('Todas las categorías')),
            'fs_filter_order_name_asc' => $configuration->getString('FILTER_ORDER_NAME_ASC', $idLang, $this->module->l('Nombre A-Z')),
            'fs_filter_order_name_desc' => $configuration->getString('FILTER_ORDER_NAME_DESC', $idLang, $this->module->l('Nombre Z-A')),
            'fs_filter_order_price_asc' => $configuration->getString('FILTER_ORDER_PRICE_ASC', $idLang, $this->module->l('Precio menor')),
            'fs_filter_order_price_desc' => $configuration->getString('FILTER_ORDER_PRICE_DESC', $idLang, $this->module->l('Precio mayor')),
            'fs_filter_order_newest' => $configuration->getString('FILTER_ORDER_NEWEST', $idLang, $this->module->l('Más recientes')),
            'fs_filter_order_popular' => $configuration->getString('FILTER_ORDER_POPULAR', $idLang, $this->module->l('Más solicitadas')),
            'fs_filter_button_text' => $configuration->getString('FILTER_BUTTON_TEXT', $idLang, $this->module->l('Filtrar muestras')),
            'fs_card_label' => $configuration->getString('CARD_LABEL', $idLang, $this->module->l('MUESTRA DE TEJIDO')),
            'fs_no_image_text' => $configuration->getString('NO_IMAGE_TEXT', $idLang, $this->module->l('Imagen no disponible')),
            'fs_reference_label' => $configuration->getString('REFERENCE_LABEL', $idLang, $this->module->l('Referencia:')),
            'fs_category_label' => $configuration->getString('CATEGORY_LABEL', $idLang, $this->module->l('Categoría:')),
            'fs_add_button_text' => $configuration->getString('ADD_BUTTON_TEXT', $idLang, $this->module->l('Añadir muestra al carrito')),
            'fs_added_text' => $configuration->getString('ADDED_TEXT', $idLang, $this->module->l('Muestra añadida')),
            'fs_product_link_text' => $configuration->getString('PRODUCT_LINK_TEXT', $idLang, $this->module->l('Ver el tejido por metros')),
            'fs_result_count_text' => $configuration->getString('RESULT_COUNT_TEXT', $idLang, '%count% muestras encontradas'),
            'fs_in_cart_text' => $configuration->getString('IN_CART_TEXT', $idLang, 'En el carrito: %count%'),
            'fs_remove_sample_text' => $configuration->getString('REMOVE_SAMPLE_TEXT', $idLang, 'Quitar muestra'),
            'fs_limit_reached_text' => $configuration->getString('LIMIT_REACHED_TEXT', $idLang, 'Límite alcanzado'),
            'fs_per_page_text' => $configuration->getString('PER_PAGE_TEXT', $idLang, 'Mostrar'),
            'fs_empty_filtered_text' => $configuration->getString('EMPTY_FILTERED_TEXT', $idLang),
            'fs_empty_config_text' => $configuration->getString('EMPTY_CONFIG_TEXT', $idLang),
            'fs_show_card_label' => $configuration->getBool('SHOW_CARD_LABEL', true),
            'fs_show_card_image' => $configuration->getBool('SHOW_CARD_IMAGE', true),
            'fs_show_card_name' => $configuration->getBool('SHOW_CARD_NAME', true),
            'fs_show_card_reference' => $configuration->getBool('SHOW_CARD_REFERENCE', true),
            'fs_show_card_category' => $configuration->getBool('SHOW_CARD_CATEGORY', true),
            'fs_show_card_explainer' => $configuration->getBool('SHOW_CARD_EXPLAINER', true),
            'fs_show_card_price' => $configuration->getBool('SHOW_CARD_PRICE', true),
            'fs_show_card_product_link' => $configuration->getBool('SHOW_CARD_PRODUCT_LINK', true),
        ];
    }

    /** @return list<int> */
    private function parsePerPageOptions(string $value): array
    {
        $options = array_values(array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', $value) ?: []))));
        $options = array_values(array_filter($options, static fn (int $option): bool => $option >= 1 && $option <= 100));
        sort($options);
        return $options !== [] ? $options : [12, 24, 36, 48, 72];
    }

    private function getProductLinkImageUrl(): string
    {
        $filename = basename($this->configuration()->getString('PRODUCT_LINK_IMAGE'));
        if ($filename === '' || !is_file($this->module->getLocalPath() . 'views/img/custom/' . $filename)) {
            return '';
        }

        return $this->module->getPathUri() . 'views/img/custom/' . rawurlencode($filename);
    }

    private function repository(): CatalogSampleRepository
    {
        return $this->catalogRepository ??= new CatalogSampleRepository();
    }

    private function cartRepository(): CartSampleRepository
    {
        return $this->cartRepository ??= new CartSampleRepository();
    }

    private function buttonStyles(): ButtonStyleProvider
    {
        return $this->buttonStyleProvider ??= new ButtonStyleProvider($this->configuration());
    }

    private function configuration(): ModuleConfiguration
    {
        return $this->moduleConfiguration ??= new ModuleConfiguration();
    }

    public function getCanonicalURL()
    {
        return $this->module->getSamplesControllerUrl();
    }
}
