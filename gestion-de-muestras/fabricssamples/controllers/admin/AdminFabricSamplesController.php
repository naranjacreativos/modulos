<?php

require_once dirname(__DIR__, 2) . '/config/autoload.php';

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Repository\AdminSampleRepository;
use NaranjaCreativos\FabricSamples\Repository\AuditRepository;
use NaranjaCreativos\FabricSamples\Service\AuditService;
use NaranjaCreativos\FabricSamples\Repository\CouponRepository;
use NaranjaCreativos\FabricSamples\Service\CouponStatusPresenter;
use NaranjaCreativos\FabricSamples\Presentation\PaginationWindow;
use NaranjaCreativos\FabricSamples\Presentation\PriceFormatter;
use NaranjaCreativos\FabricSamples\Security\AdminControllerSecurityTrait;
use NaranjaCreativos\FabricSamples\Security\CssSanitizer;
use NaranjaCreativos\FabricSamples\Security\HtmlSanitizer;

class AdminFabricSamplesController extends ModuleAdminController
{
    use AdminControllerSecurityTrait;

    private ?AdminSampleRepository $sampleRepository = null;
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('Muestras de tejidos');
    }


    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addJS($this->module->getPathUri() . 'views/js/admin-tabs.js');
        $this->addCSS($this->module->getPathUri() . 'views/css/admin-tabs.css');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitFabricSamplesConfig')) {
            if ($this->guardAdminAction('edit')) {
                $this->saveConfig();
            }
        }
        if (Tools::isSubmit('submitFabricSampleProduct')) {
            if ($this->guardAdminAction('edit')) {
                $this->saveProductConfig();
            }
        }
        if (Tools::isSubmit('repairFabricSamplesHistory')) {
            if ($this->guardAdminAction('edit')) {
                $result = method_exists($this->module, 'rebuildHistoryAndCoupons')
                    ? $this->module->rebuildHistoryAndCoupons((int) $this->context->shop->id)
                    : ['history' => 0, 'coupons' => 0];
                $this->confirmations[] = sprintf($this->module->l('Reparación completada: %d muestras históricas y %d cupones generados.'), (int) $result['history'], (int) $result['coupons']);
            }
        }
        if (Tools::isSubmit('bulkEnableSamples') || Tools::isSubmit('bulkDisableSamples')) {
            $ids = Tools::getValue('productBox', []);
            if ($this->guardAdminAction('edit') && is_array($ids) && $ids) {
                $active = Tools::isSubmit('bulkEnableSamples') ? 1 : 0;
                foreach (array_map('intval', $ids) as $idProduct) {
                    $this->upsertProduct($idProduct, ['active' => $active]);
                }
                $this->auditService()->log('bulk_product_status', 'products', 'bulk', [], ['active' => $active, 'product_ids' => array_values(array_map('intval', $ids))], 'Acción masiva sobre muestras.', (int) $this->context->shop->id);
                $this->confirmations[] = $this->module->l('Acción masiva aplicada.');
            }
        }
        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();
        $idProduct = (int) Tools::getValue('edit_product');
        if ($idProduct) {
            $this->content .= $this->renderProductForm($idProduct);
        } else {
            $this->content .= $this->renderDashboard();
            $this->content .= $this->renderCouponList();
            $this->content .= $this->renderConfigForm();
            $this->content .= $this->renderProductList();
        }
        $this->context->smarty->assign(['content' => $this->content]);
    }

    private function renderDashboard()
    {
        $metrics = $this->repository()->dashboardMetrics((int) $this->context->shop->id);
        $this->context->smarty->assign([
            'fs_kpi_active' => $metrics['active'],
            'fs_kpi_sold' => $metrics['sold'],
            'fs_kpi_revenue' => PriceFormatter::format((float) $metrics['revenue'], $this->context->currency),
            'fs_kpi_orders' => $metrics['orders'],
        ]);
        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/dashboard.tpl');
    }

    private function renderCouponList(): string
    {
        if (!class_exists(CouponRepository::class)) {
            return '';
        }
        $repository = new CouponRepository();
        $contextShopId = (int) Shop::getContextShopID();
        $rows = $repository->findForAdmin($contextShopId, 500);
        foreach ($rows as &$row) {
            $order = new Order((int) $row['id_order']);
            $currency = Validate::isLoadedObject($order) ? new Currency((int) $order->id_currency) : $this->context->currency;
            $row['discount_value_formatted'] = PriceFormatter::format((float) $row['discount_value'], $currency);
            $row['minimum_order_formatted'] = PriceFormatter::format((float) $row['minimum_order'], $currency);
            $status = (new CouponStatusPresenter())->present($row);
            $row['status'] = $status['key'];
            $row['status_label'] = $this->module->l($status['label_source']);
            $row['status_front_class'] = $status['front_class'];
            $row['status_admin_class'] = $status['admin_class'];
            $row['order_link'] = $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => (int) $row['id_order'], 'vieworder' => 1]);
            $row['customer_link'] = $this->context->link->getAdminLink('AdminCustomers', true, [], ['id_customer' => (int) $row['id_customer'], 'viewcustomer' => 1]);
            $row['cart_rule_link'] = !empty($row['id_cart_rule']) ? $this->context->link->getAdminLink('AdminCartRules', true, [], ['id_cart_rule' => (int) $row['id_cart_rule'], 'updatecart_rule' => 1]) : '';
            $row['edit_link'] = $this->context->link->getAdminLink('AdminFabricSamplesCoupons', true, [], ['edit_coupon' => (int) $row['id_fabricssamples_coupon']]);
        }
        unset($row);
        $this->context->smarty->assign([
            'fs_admin_coupons' => $rows,
            'fs_coupon_stats' => $repository->stats($contextShopId),
            'fs_admin_link' => $this->context->link->getAdminLink('AdminFabricSamplesCoupons'),
            'fs_repair_link' => $this->context->link->getAdminLink('AdminFabricSamples'),
            'fs_config_link' => '',
            'fs_coupon_feature_enabled' => (bool) Configuration::get('FABRICS_SAMPLES_COUPON_ENABLED'),
            'fs_show_shop_column' => Shop::isFeatureActive(),
        ]);
        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/coupon_list.tpl');
    }

    private function renderConfigForm()
    {
        $yesNo = [
            ['id' => 'on', 'value' => 1, 'label' => $this->module->l('Sí')],
            ['id' => 'off', 'value' => 0, 'label' => $this->module->l('No')],
        ];
        $imageName = (string) Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_IMAGE');
        $imagePreview = '';
        if ($imageName !== '' && is_file($this->module->getLocalPath() . 'views/img/custom/' . basename($imageName))) {
            $imagePreview = '<div class="alert alert-info"><p><strong>' . $this->module->l('Imagen actual del enlace:') . '</strong></p>'
                . '<img src="' . $this->module->getPathUri() . 'views/img/custom/' . rawurlencode(basename($imageName)) . '" alt="" style="max-width:220px;max-height:120px;height:auto">'
                . '</div>';
        }

        $buttonDesignFields = [];
        foreach ($this->getButtonDefinitions() as $definition) {
            $buttonDesignFields = array_merge($buttonDesignFields, $this->buildButtonDesignFields($definition));
        }

        $fields = [[
            'form' => [
                'legend' => ['title' => $this->module->l('Configuración del módulo'), 'icon' => 'icon-cogs'],
                'input' => [
                    ['type'=>'html','name'=>'fs_tab_general','html_content'=>'<div class="fs-tab-marker" data-tab="general" data-title="' . $this->module->l('Configuración') . '" data-icon="icon-cogs"></div>'],
                    ['type'=>'text','label'=>$this->module->l('Precio predeterminado sin impuestos'),'name'=>'FABRICS_SAMPLES_DEFAULT_PRICE','class'=>'fixed-width-sm','suffix'=>$this->context->currency->sign],
                    ['type'=>'text','label'=>$this->module->l('Tamaño predeterminado'),'name'=>'FABRICS_SAMPLES_DEFAULT_SIZE','lang'=>true],
                    ['type'=>'textarea','label'=>$this->module->l('Texto informativo general'),'name'=>'FABRICS_SAMPLES_INFO_TEXT','lang'=>true,'autoload_rte'=>true,'rows'=>4],
                    ['type'=>'text','label'=>$this->module->l('Peso predeterminado'),'name'=>'FABRICS_SAMPLES_DEFAULT_WEIGHT','class'=>'fixed-width-sm','suffix'=>'kg'],
                    ['type'=>'text','label'=>$this->module->l('Aviso de stock bajo'),'name'=>'FABRICS_SAMPLES_LOW_STOCK_THRESHOLD','class'=>'fixed-width-sm','suffix'=>$this->module->l('unidades'),'desc'=>$this->module->l('Use 0 para desactivar el aviso en los registros de PrestaShop.')],
                    ['type'=>'html','name'=>'fs_limits_heading','html_content'=>'<div class="fs-tab-marker" data-tab="limits" data-title="' . $this->module->l('Límites') . '" data-icon="icon-shield"></div><p class="help-block">' . $this->module->l('Los límites se validan siempre en servidor. Use 0 para indicar sin límite.') . '</p>'],
                    ['type'=>'switch','label'=>$this->module->l('Activar límites avanzados'),'name'=>'FABRICS_SAMPLES_LIMITS_ENABLED','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'text','label'=>$this->module->l('Máximo total por pedido'),'name'=>'FABRICS_SAMPLES_MAX_TOTAL','class'=>'fixed-width-sm'],
                    ['type'=>'text','label'=>$this->module->l('Máximo por referencia'),'name'=>'FABRICS_SAMPLES_MAX_PER_PRODUCT','class'=>'fixed-width-sm'],
                    ['type'=>'text','label'=>$this->module->l('Máximo total por cliente en el periodo'),'name'=>'FABRICS_SAMPLES_MAX_CUSTOMER_TOTAL_PERIOD','class'=>'fixed-width-sm'],
                    ['type'=>'text','label'=>$this->module->l('Máximo por referencia y cliente en el periodo'),'name'=>'FABRICS_SAMPLES_MAX_CUSTOMER_PRODUCT_PERIOD','class'=>'fixed-width-sm','desc'=>$this->module->l('Puede sobrescribirse en la configuración individual del tejido.')],
                    ['type'=>'text','label'=>$this->module->l('Periodo de control'),'name'=>'FABRICS_SAMPLES_CUSTOMER_PERIOD_DAYS','class'=>'fixed-width-sm','suffix'=>$this->module->l('días')],
                    ['type'=>'switch','label'=>$this->module->l('Aplicar límites históricos a clientes invitados'),'name'=>'FABRICS_SAMPLES_LIMIT_GUESTS','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'text','label'=>$this->module->l('Grupos exentos'),'name'=>'FABRICS_SAMPLES_LIMIT_EXEMPT_GROUPS','desc'=>$this->module->l('IDs de grupos separados por comas. Los clientes de esos grupos no tendrán límites, aunque sí se mantiene el control de stock.')],
                    ['type'=>'html','name'=>'fs_limit_order_states','html_content'=>$this->renderOrderStateSelector()],
                    ['type'=>'text','label'=>$this->module->l('Error: máximo total'),'name'=>'FABRICS_SAMPLES_LIMIT_ERROR_TOTAL','lang'=>true,'desc'=>$this->module->l('Marcadores disponibles: %limit% y %days%.')],
                    ['type'=>'text','label'=>$this->module->l('Error: máximo por referencia'),'name'=>'FABRICS_SAMPLES_LIMIT_ERROR_PRODUCT','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Error: máximo histórico total'),'name'=>'FABRICS_SAMPLES_LIMIT_ERROR_CUSTOMER_TOTAL','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Error: máximo histórico por referencia'),'name'=>'FABRICS_SAMPLES_LIMIT_ERROR_CUSTOMER_PRODUCT','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Error: stock insuficiente'),'name'=>'FABRICS_SAMPLES_LIMIT_ERROR_STOCK','lang'=>true],
                    ['type'=>'switch','label'=>$this->module->l('Página exclusiva activa'),'name'=>'FABRICS_SAMPLES_ENABLE_CATALOG','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'text','label'=>$this->module->l('URL amigable'),'name'=>'FABRICS_SAMPLES_FRIENDLY_URL','prefix'=>'/'],
                    ['type'=>'text','label'=>$this->module->l('Productos por página'),'name'=>'FABRICS_SAMPLES_PRODUCTS_PER_PAGE','class'=>'fixed-width-sm'],

                    ['type'=>'html','name'=>'fs_catalog_heading','html_content'=>'<div class="fs-tab-marker" data-tab="catalog" data-title="' . $this->module->l('Página de muestras') . '" data-icon="icon-th-large"></div>'],
                    ['type'=>'switch','label'=>$this->module->l('Filtros AJAX'),'name'=>'FABRICS_SAMPLES_PAGE_AJAX_FILTERS','is_bool'=>true,'values'=>$yesNo,'desc'=>$this->module->l('Si se desactiva, los filtros siguen funcionando mediante recarga normal.')],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar cantidad de resultados'),'name'=>'FABRICS_SAMPLES_PAGE_SHOW_RESULT_COUNT','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar estado ya añadido'),'name'=>'FABRICS_SAMPLES_PAGE_SHOW_IN_CART_STATUS','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Permitir quitar desde la página'),'name'=>'FABRICS_SAMPLES_PAGE_ALLOW_REMOVE','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'text','label'=>$this->module->l('Opciones de productos por página'),'name'=>'FABRICS_SAMPLES_PAGE_PER_PAGE_OPTIONS','desc'=>$this->module->l('Valores separados por comas, por ejemplo: 12,24,36,48,72.')],
                    ['type'=>'html','name'=>'fs_responsive_columns_help','html_content'=>'<div class="alert alert-info"><strong>' . $this->module->l('Diseño adaptable por dispositivo') . '</strong><br>' . $this->module->l('Elige cuántas muestras se muestran por fila en ordenador, tableta y móvil. La página cambia automáticamente según el ancho de la pantalla.') . '</div>'],
                    ['type'=>'select','label'=>$this->module->l('Productos por fila en ordenador'),'name'=>'FABRICS_SAMPLES_PAGE_COLUMNS_DESKTOP','options'=>['query'=>$this->getColumnOptions(1,6), 'id'=>'id','name'=>'name'],'desc'=>$this->module->l('Se aplica en pantallas de más de 1100 píxeles.')],
                    ['type'=>'select','label'=>$this->module->l('Productos por fila en tablet'),'name'=>'FABRICS_SAMPLES_PAGE_COLUMNS_TABLET','options'=>['query'=>$this->getColumnOptions(1,4), 'id'=>'id','name'=>'name'],'desc'=>$this->module->l('Se aplica entre 769 y 1100 píxeles.')],
                    ['type'=>'select','label'=>$this->module->l('Productos por fila en móvil'),'name'=>'FABRICS_SAMPLES_PAGE_COLUMNS_MOBILE','options'=>['query'=>$this->getColumnOptions(1,2), 'id'=>'id','name'=>'name'],'desc'=>$this->module->l('Se aplica hasta 768 píxeles.')],
                    ['type'=>'text','label'=>$this->module->l('Texto del contador de resultados'),'name'=>'FABRICS_SAMPLES_RESULT_COUNT_TEXT','lang'=>true,'placeholder'=>'%count% muestras encontradas','desc'=>$this->module->l('Ejemplo: %count% muestras encontradas. El marcador %count% se sustituye por el número real de resultados.')],
                    ['type'=>'text','label'=>$this->module->l('Texto de muestra en carrito'),'name'=>'FABRICS_SAMPLES_IN_CART_TEXT','lang'=>true,'placeholder'=>'En el carrito: %count%','desc'=>$this->module->l('Ejemplo: En el carrito: %count%. El marcador %count% indica cuántas unidades de esa muestra hay en el carrito.')],
                    ['type'=>'text','label'=>$this->module->l('Texto para quitar la muestra'),'name'=>'FABRICS_SAMPLES_REMOVE_SAMPLE_TEXT','lang'=>true,'placeholder'=>'Quitar muestra','desc'=>$this->module->l('Ejemplo: Quitar muestra.')],
                    ['type'=>'text','label'=>$this->module->l('Texto cuando se alcanza el límite'),'name'=>'FABRICS_SAMPLES_LIMIT_REACHED_TEXT','lang'=>true,'placeholder'=>'Límite alcanzado','desc'=>$this->module->l('Ejemplo: Límite alcanzado.')],
                    ['type'=>'text','label'=>$this->module->l('Texto selector por página'),'name'=>'FABRICS_SAMPLES_PER_PAGE_TEXT','lang'=>true,'placeholder'=>'Mostrar','desc'=>$this->module->l('Ejemplo: Mostrar.')],
                    ['type'=>'text','label'=>$this->module->l('Orden más recientes'),'name'=>'FABRICS_SAMPLES_FILTER_ORDER_NEWEST','lang'=>true,'placeholder'=>'Más recientes','desc'=>$this->module->l('Ejemplo: Más recientes.')],
                    ['type'=>'text','label'=>$this->module->l('Orden más solicitadas'),'name'=>'FABRICS_SAMPLES_FILTER_ORDER_POPULAR','lang'=>true,'placeholder'=>'Más solicitadas','desc'=>$this->module->l('Ejemplo: Más solicitadas.')],

                    ['type'=>'html','name'=>'fs_texts_heading','html_content'=>'<div class="fs-tab-marker" data-tab="texts" data-title="' . $this->module->l('Textos') . '" data-icon="icon-font"></div><p class="help-block">' . $this->module->l('Los campos con selector de idioma pueden personalizarse para cada idioma de la tienda.') . '</p>'],
                    ['type'=>'text','label'=>$this->module->l('Título del bloque en la ficha'),'name'=>'FABRICS_SAMPLES_PRODUCT_BLOCK_TITLE','lang'=>true],
                    ['type'=>'textarea','label'=>$this->module->l('Texto del bloque en la ficha'),'name'=>'FABRICS_SAMPLES_PRODUCT_BLOCK_TEXT','lang'=>true,'rows'=>3],
                    ['type'=>'text','label'=>$this->module->l('Título principal'),'name'=>'FABRICS_SAMPLES_PAGE_TITLE','lang'=>true],
                    ['type'=>'textarea','label'=>$this->module->l('Introducción de la página'),'name'=>'FABRICS_SAMPLES_PAGE_INTRO_HTML','lang'=>true,'autoload_rte'=>true,'rows'=>6,'desc'=>$this->module->l('Admite texto, enlaces, formato e imágenes mediante HTML.')],
                    ['type'=>'text','label'=>$this->module->l('Etiqueta del aviso'),'name'=>'FABRICS_SAMPLES_IMPORTANT_LABEL','lang'=>true],
                    ['type'=>'textarea','label'=>$this->module->l('Aviso destacado'),'name'=>'FABRICS_SAMPLES_PAGE_WARNING','lang'=>true,'autoload_rte'=>true,'rows'=>4],
                    ['type'=>'text','label'=>$this->module->l('Texto de búsqueda'),'name'=>'FABRICS_SAMPLES_FILTER_SEARCH_PLACEHOLDER','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Opción todas las categorías'),'name'=>'FABRICS_SAMPLES_FILTER_ALL_CATEGORIES','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Orden Nombre A-Z'),'name'=>'FABRICS_SAMPLES_FILTER_ORDER_NAME_ASC','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Orden Nombre Z-A'),'name'=>'FABRICS_SAMPLES_FILTER_ORDER_NAME_DESC','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Orden precio menor'),'name'=>'FABRICS_SAMPLES_FILTER_ORDER_PRICE_ASC','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Orden precio mayor'),'name'=>'FABRICS_SAMPLES_FILTER_ORDER_PRICE_DESC','lang'=>true],
                    ['type'=>'html','name'=>'fs_visibility_heading','html_content'=>'<div class="fs-tab-marker" data-tab="visibility" data-title="' . $this->module->l('Campos visibles') . '" data-icon="icon-eye"></div><p class="help-block">' . $this->module->l('Elige qué datos se muestran en cada tarjeta de la página de muestras.') . '</p>'],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar etiqueta MUESTRA DE TEJIDO'),'name'=>'FABRICS_SAMPLES_SHOW_CARD_LABEL','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar imagen'),'name'=>'FABRICS_SAMPLES_SHOW_CARD_IMAGE','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar nombre del tejido'),'name'=>'FABRICS_SAMPLES_SHOW_CARD_NAME','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar referencia'),'name'=>'FABRICS_SAMPLES_SHOW_CARD_REFERENCE','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar categoría'),'name'=>'FABRICS_SAMPLES_SHOW_CARD_CATEGORY','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar explicación de la tarjeta'),'name'=>'FABRICS_SAMPLES_SHOW_CARD_EXPLAINER','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar precio'),'name'=>'FABRICS_SAMPLES_SHOW_CARD_PRICE','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar enlace al tejido por metros'),'name'=>'FABRICS_SAMPLES_SHOW_CARD_PRODUCT_LINK','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'text','label'=>$this->module->l('Etiqueta superior de cada tarjeta'),'name'=>'FABRICS_SAMPLES_CARD_LABEL','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Texto cuando no hay imagen'),'name'=>'FABRICS_SAMPLES_NO_IMAGE_TEXT','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Etiqueta referencia'),'name'=>'FABRICS_SAMPLES_REFERENCE_LABEL','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Etiqueta categoría'),'name'=>'FABRICS_SAMPLES_CATEGORY_LABEL','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Mensaje muestra añadida'),'name'=>'FABRICS_SAMPLES_ADDED_TEXT','lang'=>true],
                    ['type'=>'file','label'=>$this->module->l('Imagen opcional del enlace al tejido'),'name'=>'FABRICS_SAMPLES_PRODUCT_LINK_IMAGE','desc'=>$this->module->l('Formatos permitidos: JPG, PNG, GIF y WEBP. Máximo 4 MB.')],
                    ['type'=>'html','name'=>'fs_link_image_preview','html_content'=>$imagePreview],
                    ['type'=>'switch','label'=>$this->module->l('Eliminar la imagen actual del enlace'),'name'=>'FABRICS_SAMPLES_REMOVE_PRODUCT_LINK_IMAGE','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'textarea','label'=>$this->module->l('Mensaje sin resultados'),'name'=>'FABRICS_SAMPLES_EMPTY_FILTERED_TEXT','lang'=>true,'rows'=>3],
                    ['type'=>'textarea','label'=>$this->module->l('Mensaje sin muestras configuradas'),'name'=>'FABRICS_SAMPLES_EMPTY_CONFIG_TEXT','lang'=>true,'rows'=>3],

                    ['type'=>'html','name'=>'fs_design_heading','html_content'=>'<div class="fs-tab-marker" data-tab="design" data-title="' . $this->module->l('Diseño') . '" data-icon="icon-desktop"></div>'],
                    ['type'=>'html','name'=>'fs_button_visibility_help','html_content'=>'<div class="alert alert-info"><strong>' . $this->module->l('Visibilidad de los botones de muestras') . '</strong><br>' . $this->module->l('Activa o desactiva de forma independiente el botón de muestras en la ficha de producto y en categorías o listados compatibles.') . '</div>'],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar botón en ficha de producto'),'name'=>'FABRICS_SAMPLES_SHOW_PRODUCT','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Mostrar botón en categorías y listados'),'name'=>'FABRICS_SAMPLES_SHOW_LISTING','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'color','label'=>$this->module->l('Color principal'),'name'=>'FABRICS_SAMPLES_PAGE_ACCENT_COLOR'],
                    ['type'=>'color','label'=>$this->module->l('Color de fondo'),'name'=>'FABRICS_SAMPLES_PAGE_BACKGROUND_COLOR'],
                    ...$buttonDesignFields,
                    ['type'=>'textarea','label'=>$this->module->l('CSS personalizado'),'name'=>'FABRICS_SAMPLES_PAGE_CUSTOM_CSS','rows'=>8,'desc'=>$this->module->l('CSS aplicado únicamente a la página de muestras. No incluya etiquetas style.')],
                    ['type'=>'html','name'=>'fs_privacy_heading','html_content'=>'<div class="fs-tab-marker" data-tab="privacy" data-title="' . $this->module->l('Privacidad y seguridad') . '" data-icon="icon-lock"></div><p class="help-block">' . $this->module->l('Define la conservación de registros y de copias cifradas. Los datos vinculados a pedidos se conservan según las obligaciones fiscales de la tienda.') . '</p>'],
                    ['type'=>'text','label'=>$this->module->l('Retención de auditoría'),'name'=>'FABRICS_SAMPLES_RETENTION_AUDIT_DAYS','class'=>'fixed-width-sm','suffix'=>$this->module->l('días'),'desc'=>$this->module->l('Entre 30 y 3650 días.')],
                    ['type'=>'text','label'=>$this->module->l('Retención de eventos de límites'),'name'=>'FABRICS_SAMPLES_RETENTION_LIMIT_EVENT_DAYS','class'=>'fixed-width-sm','suffix'=>$this->module->l('días')],
                    ['type'=>'text','label'=>$this->module->l('Retención de reinicios de límites'),'name'=>'FABRICS_SAMPLES_RETENTION_LIMIT_RESET_DAYS','class'=>'fixed-width-sm','suffix'=>$this->module->l('días')],
                    ['type'=>'text','label'=>$this->module->l('Número máximo de copias'),'name'=>'FABRICS_SAMPLES_BACKUP_RETENTION_COUNT','class'=>'fixed-width-sm','desc'=>$this->module->l('Entre 1 y 50 copias cifradas.')],
                    ['type'=>'text','label'=>$this->module->l('Antigüedad máxima de copias'),'name'=>'FABRICS_SAMPLES_BACKUP_RETENTION_DAYS','class'=>'fixed-width-sm','suffix'=>$this->module->l('días')],
                    ['type'=>'text','label'=>$this->module->l('Máximo de peticiones AJAX por minuto'),'name'=>'FABRICS_SAMPLES_AJAX_RATE_LIMIT_PER_MINUTE','class'=>'fixed-width-sm','desc'=>$this->module->l('Entre 10 y 120 por carrito y origen.')],
                    ['type'=>'html','name'=>'fs_coupon_heading','html_content'=>'<div class="fs-tab-marker" data-tab="coupons" data-title="' . $this->module->l('Cupones') . '" data-icon="icon-ticket"></div><p class="help-block">' . $this->module->l('Genera un cupón nativo y de un solo uso para el cliente que haya comprado muestras.') . '</p>'],
                    ['type'=>'switch','label'=>$this->module->l('Activar cupones por muestras'),'name'=>'FABRICS_SAMPLES_COUPON_ENABLED','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'select','label'=>$this->module->l('Momento de generación'),'name'=>'FABRICS_SAMPLES_COUPON_TRIGGER','options'=>['query'=>[['id'=>'paid','name'=>$this->module->l('Cuando el pedido esté pagado')],['id'=>'order','name'=>$this->module->l('Al crear el pedido')]],'id'=>'id','name'=>'name']],
                    ['type'=>'select','label'=>$this->module->l('Importe del cupón'),'name'=>'FABRICS_SAMPLES_COUPON_VALUE_MODE','options'=>['query'=>[['id'=>'full','name'=>$this->module->l('Importe completo de las muestras')],['id'=>'cheapest','name'=>$this->module->l('Muestra más económica')],['id'=>'most_expensive','name'=>$this->module->l('Muestra más cara')],['id'=>'percentage','name'=>$this->module->l('Porcentaje del importe de las muestras')],['id'=>'fixed','name'=>$this->module->l('Importe fijo')]],'id'=>'id','name'=>'name'],'desc'=>$this->module->l('Las opciones muestra más económica y muestra más cara descuentan una unidad de la muestra correspondiente, aunque el pedido incluya varias muestras o cantidades.')],
                    ['type'=>'text','label'=>$this->module->l('Porcentaje recuperable'),'name'=>'FABRICS_SAMPLES_COUPON_SAMPLE_PERCENT','class'=>'fixed-width-sm','suffix'=>'%','desc'=>$this->module->l('Se utiliza cuando el modo es porcentaje del importe de las muestras.')],
                    ['type'=>'text','label'=>$this->module->l('Importe fijo'),'name'=>'FABRICS_SAMPLES_COUPON_FIXED_AMOUNT','class'=>'fixed-width-sm','suffix'=>$this->context->currency->sign],
                    ['type'=>'text','label'=>$this->module->l('Compra mínima'),'name'=>'FABRICS_SAMPLES_COUPON_MINIMUM_ORDER','class'=>'fixed-width-sm','suffix'=>$this->context->currency->sign],
                    ['type'=>'text','label'=>$this->module->l('Validez'),'name'=>'FABRICS_SAMPLES_COUPON_VALID_DAYS','class'=>'fixed-width-sm','suffix'=>$this->module->l('días')],
                    ['type'=>'switch','label'=>$this->module->l('Exigir uno de los tejidos muestreados'),'name'=>'FABRICS_SAMPLES_COUPON_LIMIT_TO_PRODUCTS','is_bool'=>true,'values'=>$yesNo,'desc'=>$this->module->l('El cupón solo será válido si el carrito contiene al menos uno de los tejidos de los que se pidió muestra. El descuento fijo se aplica al total de productos, según el funcionamiento nativo de PrestaShop.')],
                    ['type'=>'switch','label'=>$this->module->l('Permitir uso parcial'),'name'=>'FABRICS_SAMPLES_COUPON_PARTIAL_USE','is_bool'=>true,'values'=>$yesNo],
                    ['type'=>'switch','label'=>$this->module->l('Enviar el cupón por correo'),'name'=>'FABRICS_SAMPLES_COUPON_SEND_EMAIL','is_bool'=>true,'values'=>$yesNo,'desc'=>$this->module->l('Requiere que el correo de PrestaShop esté configurado correctamente.')],
                    ['type'=>'text','label'=>$this->module->l('Prefijo del código'),'name'=>'FABRICS_SAMPLES_COUPON_CODE_PREFIX','class'=>'fixed-width-lg'],
                    ['type'=>'text','label'=>$this->module->l('Nombre del cupón'),'name'=>'FABRICS_SAMPLES_COUPON_NAME','lang'=>true],
                    ['type'=>'text','label'=>$this->module->l('Asunto del correo'),'name'=>'FABRICS_SAMPLES_COUPON_EMAIL_SUBJECT','lang'=>true],

                    ['type'=>'html','name'=>'fs_seo_heading','html_content'=>'<div class="fs-tab-marker" data-tab="seo" data-title="SEO y avanzado" data-icon="icon-search"></div>'],
                    ['type'=>'text','label'=>$this->module->l('Meta title / título mostrado por el tema'),'name'=>'FABRICS_SAMPLES_META_TITLE','lang'=>true],
                    ['type'=>'textarea','label'=>$this->module->l('Meta description'),'name'=>'FABRICS_SAMPLES_META_DESCRIPTION','lang'=>true,'rows'=>2],
                ],
                'submit' => ['title' => $this->module->l('Guardar configuración')],
            ],
        ]];

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->controller_name;
        $helper->token = Tools::getAdminTokenLite($this->controller_name);
        $helper->currentIndex = self::$currentIndex;
        $helper->submit_action = 'submitFabricSamplesConfig';
        $helper->enctype = 'multipart/form-data';
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->fields_value = [];
        $configurationDefaults = ModuleConfiguration::defaults();
        $exampleTextKeys = array_flip(ModuleConfiguration::catalogExampleTextKeys());

        $simpleKeys = array_merge(
            ['DEFAULT_PRICE','DEFAULT_WEIGHT','LOW_STOCK_THRESHOLD','LIMITS_ENABLED','MAX_TOTAL','MAX_PER_PRODUCT','MAX_CUSTOMER_TOTAL_PERIOD','MAX_CUSTOMER_PRODUCT_PERIOD','CUSTOMER_PERIOD_DAYS','LIMIT_GUESTS','LIMIT_EXEMPT_GROUPS','SHOW_PRODUCT','SHOW_LISTING','ENABLE_CATALOG','FRIENDLY_URL','PRODUCTS_PER_PAGE','PAGE_AJAX_FILTERS','PAGE_SHOW_RESULT_COUNT','PAGE_SHOW_IN_CART_STATUS','PAGE_ALLOW_REMOVE','PAGE_PER_PAGE_OPTIONS','PAGE_ACCENT_COLOR','PAGE_BACKGROUND_COLOR','PAGE_CUSTOM_CSS','PAGE_COLUMNS_DESKTOP','PAGE_COLUMNS_TABLET','PAGE_COLUMNS_MOBILE','SHOW_CARD_LABEL','SHOW_CARD_IMAGE','SHOW_CARD_NAME','SHOW_CARD_REFERENCE','SHOW_CARD_CATEGORY','SHOW_CARD_EXPLAINER','SHOW_CARD_PRICE','SHOW_CARD_PRODUCT_LINK','COUPON_ENABLED','COUPON_TRIGGER','COUPON_VALUE_MODE','COUPON_SAMPLE_PERCENT','COUPON_FIXED_AMOUNT','COUPON_MINIMUM_ORDER','COUPON_VALID_DAYS','COUPON_LIMIT_TO_PRODUCTS','COUPON_PARTIAL_USE','COUPON_SEND_EMAIL','COUPON_CODE_PREFIX','RETENTION_AUDIT_DAYS','RETENTION_LIMIT_EVENT_DAYS','RETENTION_LIMIT_RESET_DAYS','BACKUP_RETENTION_COUNT','BACKUP_RETENTION_DAYS','AJAX_RATE_LIMIT_PER_MINUTE'],
            $this->getButtonStyleConfigurationKeys()
        );
        foreach ($simpleKeys as $key) {
            $value = Configuration::get('FABRICS_SAMPLES_' . $key);
            if ($value === false || ($value === '' && (string) ($configurationDefaults[$key] ?? '') !== '')) {
                $value = $configurationDefaults[$key] ?? '';
            }
            $helper->fields_value['FABRICS_SAMPLES_' . $key] = $value;
        }
        $helper->fields_value['FABRICS_SAMPLES_REMOVE_PRODUCT_LINK_IMAGE'] = 0;

        $langKeys = ['LIMIT_ERROR_TOTAL','LIMIT_ERROR_PRODUCT','LIMIT_ERROR_CUSTOMER_TOTAL','LIMIT_ERROR_CUSTOMER_PRODUCT','LIMIT_ERROR_STOCK','RESULT_COUNT_TEXT','IN_CART_TEXT','REMOVE_SAMPLE_TEXT','LIMIT_REACHED_TEXT','PER_PAGE_TEXT','FILTER_ORDER_NEWEST','FILTER_ORDER_POPULAR','DEFAULT_SIZE','INFO_TEXT','PRODUCT_BLOCK_TITLE','PRODUCT_BLOCK_TEXT','PRODUCT_BLOCK_BUTTON','LISTING_BUTTON_TEXT','PAGE_TITLE','PAGE_INTRO_HTML','IMPORTANT_LABEL','PAGE_WARNING','FILTER_SEARCH_PLACEHOLDER','FILTER_ALL_CATEGORIES','FILTER_ORDER_NAME_ASC','FILTER_ORDER_NAME_DESC','FILTER_ORDER_PRICE_ASC','FILTER_ORDER_PRICE_DESC','FILTER_BUTTON_TEXT','CARD_LABEL','NO_IMAGE_TEXT','REFERENCE_LABEL','CATEGORY_LABEL','ADD_BUTTON_TEXT','ADDED_TEXT','PRODUCT_LINK_TEXT','EMPTY_FILTERED_TEXT','EMPTY_CONFIG_TEXT','META_TITLE','META_DESCRIPTION','COUPON_NAME','COUPON_EMAIL_SUBJECT'];
        foreach ($langKeys as $key) {
            foreach (Language::getLanguages(false) as $language) {
                $idLang = (int) $language['id_lang'];
                $value = Configuration::get('FABRICS_SAMPLES_' . $key, $idLang);
                if ($value === false || (isset($exampleTextKeys[$key]) && trim((string) $value) === '')) {
                    $value = $configurationDefaults[$key] ?? '';
                }
                $helper->fields_value['FABRICS_SAMPLES_' . $key][$idLang] = (string) $value;
            }
        }

        return $helper->generateForm($fields);
    }

    private function saveConfig()
    {
        $defaults = ModuleConfiguration::defaults();
        $price = (float) str_replace(',', '.', Tools::getValue('FABRICS_SAMPLES_DEFAULT_PRICE'));
        if ($price < 0) {
            $this->errors[] = $this->module->l('El precio no puede ser negativo.');
            return;
        }
        $slug = Tools::str2url((string) Tools::getValue('FABRICS_SAMPLES_FRIENDLY_URL'));
        if (!$slug) {
            $slug = 'solicitar-muestras';
        }

        $selectedLimitStates = array_values(array_unique(array_filter(array_map('intval', (array) Tools::getValue('FABRICS_SAMPLES_LIMIT_ORDER_STATES', [])))));

        $simpleValues = [
            'DEFAULT_PRICE' => $price,
            'DEFAULT_WEIGHT' => max(0, (float) str_replace(',', '.', Tools::getValue('FABRICS_SAMPLES_DEFAULT_WEIGHT'))),
            'LOW_STOCK_THRESHOLD' => max(0, (int) Tools::getValue('FABRICS_SAMPLES_LOW_STOCK_THRESHOLD')),
            'LIMITS_ENABLED' => (int) Tools::getValue('FABRICS_SAMPLES_LIMITS_ENABLED'),
            'MAX_TOTAL' => max(0, (int) Tools::getValue('FABRICS_SAMPLES_MAX_TOTAL')),
            'MAX_PER_PRODUCT' => max(0, (int) Tools::getValue('FABRICS_SAMPLES_MAX_PER_PRODUCT')),
            'MAX_CUSTOMER_TOTAL_PERIOD' => max(0, (int) Tools::getValue('FABRICS_SAMPLES_MAX_CUSTOMER_TOTAL_PERIOD')),
            'MAX_CUSTOMER_PRODUCT_PERIOD' => max(0, (int) Tools::getValue('FABRICS_SAMPLES_MAX_CUSTOMER_PRODUCT_PERIOD')),
            'CUSTOMER_PERIOD_DAYS' => min(3650, max(1, (int) Tools::getValue('FABRICS_SAMPLES_CUSTOMER_PERIOD_DAYS'))),
            'LIMIT_GUESTS' => (int) Tools::getValue('FABRICS_SAMPLES_LIMIT_GUESTS'),
            'LIMIT_EXEMPT_GROUPS' => implode(',', array_values(array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', (string) Tools::getValue('FABRICS_SAMPLES_LIMIT_EXEMPT_GROUPS')) ?: []))))),
            'LIMIT_ORDER_STATES' => $selectedLimitStates === [] ? 'none' : implode(',', $selectedLimitStates),
            'SHOW_PRODUCT' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_PRODUCT'),
            'SHOW_LISTING' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_LISTING'),
            'ENABLE_CATALOG' => (int) Tools::getValue('FABRICS_SAMPLES_ENABLE_CATALOG'),
            'FRIENDLY_URL' => $slug,
            'PRODUCTS_PER_PAGE' => min(100, max(1, $this->submittedInt('PRODUCTS_PER_PAGE', (int) $defaults['PRODUCTS_PER_PAGE']))),
            'PAGE_AJAX_FILTERS' => (int) Tools::getValue('FABRICS_SAMPLES_PAGE_AJAX_FILTERS'),
            'PAGE_SHOW_RESULT_COUNT' => (int) Tools::getValue('FABRICS_SAMPLES_PAGE_SHOW_RESULT_COUNT'),
            'PAGE_SHOW_IN_CART_STATUS' => (int) Tools::getValue('FABRICS_SAMPLES_PAGE_SHOW_IN_CART_STATUS'),
            'PAGE_ALLOW_REMOVE' => (int) Tools::getValue('FABRICS_SAMPLES_PAGE_ALLOW_REMOVE'),
            'PAGE_PER_PAGE_OPTIONS' => $this->sanitizePerPageOptions((string) Tools::getValue('FABRICS_SAMPLES_PAGE_PER_PAGE_OPTIONS')),
            'PAGE_ACCENT_COLOR' => $this->validateColor($this->submittedString('PAGE_ACCENT_COLOR', (string) $defaults['PAGE_ACCENT_COLOR']), '#202020'),
            'PAGE_BACKGROUND_COLOR' => $this->validateColor($this->submittedString('PAGE_BACKGROUND_COLOR', (string) $defaults['PAGE_BACKGROUND_COLOR']), '#ffffff'),
            'PAGE_COLUMNS' => min(6, max(1, $this->submittedInt('PAGE_COLUMNS_DESKTOP', (int) $defaults['PAGE_COLUMNS_DESKTOP']))),
            'PAGE_COLUMNS_DESKTOP' => min(6, max(1, $this->submittedInt('PAGE_COLUMNS_DESKTOP', (int) $defaults['PAGE_COLUMNS_DESKTOP']))),
            'PAGE_COLUMNS_TABLET' => min(4, max(1, $this->submittedInt('PAGE_COLUMNS_TABLET', (int) $defaults['PAGE_COLUMNS_TABLET']))),
            'PAGE_COLUMNS_MOBILE' => min(2, max(1, $this->submittedInt('PAGE_COLUMNS_MOBILE', (int) $defaults['PAGE_COLUMNS_MOBILE']))),
            'PAGE_CUSTOM_CSS' => $this->sanitizeCustomCss((string) Tools::getValue('FABRICS_SAMPLES_PAGE_CUSTOM_CSS')),
            'RETENTION_AUDIT_DAYS' => min(3650, max(30, $this->submittedInt('RETENTION_AUDIT_DAYS', 365))),
            'RETENTION_LIMIT_EVENT_DAYS' => min(3650, max(30, $this->submittedInt('RETENTION_LIMIT_EVENT_DAYS', 365))),
            'RETENTION_LIMIT_RESET_DAYS' => min(3650, max(30, $this->submittedInt('RETENTION_LIMIT_RESET_DAYS', 730))),
            'BACKUP_RETENTION_COUNT' => min(50, max(1, $this->submittedInt('BACKUP_RETENTION_COUNT', 10))),
            'BACKUP_RETENTION_DAYS' => min(3650, max(1, $this->submittedInt('BACKUP_RETENTION_DAYS', 90))),
            'AJAX_RATE_LIMIT_PER_MINUTE' => min(120, max(10, $this->submittedInt('AJAX_RATE_LIMIT_PER_MINUTE', 30))),
            'SHOW_CARD_LABEL' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_CARD_LABEL'),
            'SHOW_CARD_IMAGE' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_CARD_IMAGE'),
            'SHOW_CARD_NAME' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_CARD_NAME'),
            'SHOW_CARD_REFERENCE' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_CARD_REFERENCE'),
            'SHOW_CARD_CATEGORY' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_CARD_CATEGORY'),
            'SHOW_CARD_EXPLAINER' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_CARD_EXPLAINER'),
            'SHOW_CARD_PRICE' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_CARD_PRICE'),
            'SHOW_CARD_PRODUCT_LINK' => (int) Tools::getValue('FABRICS_SAMPLES_SHOW_CARD_PRODUCT_LINK'),
            'COUPON_ENABLED' => (int) Tools::getValue('FABRICS_SAMPLES_COUPON_ENABLED'),
            'COUPON_TRIGGER' => in_array((string) Tools::getValue('FABRICS_SAMPLES_COUPON_TRIGGER'), ['paid','order'], true) ? (string) Tools::getValue('FABRICS_SAMPLES_COUPON_TRIGGER') : 'paid',
            'COUPON_VALUE_MODE' => in_array((string) Tools::getValue('FABRICS_SAMPLES_COUPON_VALUE_MODE'), ['full','cheapest','most_expensive','percentage','fixed'], true) ? (string) Tools::getValue('FABRICS_SAMPLES_COUPON_VALUE_MODE') : 'full',
            'COUPON_SAMPLE_PERCENT' => min(100, max(0, (float) str_replace(',', '.', Tools::getValue('FABRICS_SAMPLES_COUPON_SAMPLE_PERCENT')))),
            'COUPON_FIXED_AMOUNT' => max(0, (float) str_replace(',', '.', Tools::getValue('FABRICS_SAMPLES_COUPON_FIXED_AMOUNT'))),
            'COUPON_MINIMUM_ORDER' => max(0, (float) str_replace(',', '.', Tools::getValue('FABRICS_SAMPLES_COUPON_MINIMUM_ORDER'))),
            'COUPON_VALID_DAYS' => min(3650, max(1, (int) Tools::getValue('FABRICS_SAMPLES_COUPON_VALID_DAYS'))),
            'COUPON_LIMIT_TO_PRODUCTS' => (int) Tools::getValue('FABRICS_SAMPLES_COUPON_LIMIT_TO_PRODUCTS'),
            'COUPON_PARTIAL_USE' => (int) Tools::getValue('FABRICS_SAMPLES_COUPON_PARTIAL_USE'),
            'COUPON_SEND_EMAIL' => (int) Tools::getValue('FABRICS_SAMPLES_COUPON_SEND_EMAIL'),
            'COUPON_CODE_PREFIX' => strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', (string) Tools::getValue('FABRICS_SAMPLES_COUPON_CODE_PREFIX')) ?: 'MUESTRA', 0, 20)),
        ];
        $simpleValues = array_merge($simpleValues, $this->getButtonStyleValues());

        $oldConfiguration = [];
        foreach (array_keys($simpleValues) as $key) {
            $oldConfiguration[$key] = Configuration::get('FABRICS_SAMPLES_' . $key);
        }

        foreach ($simpleValues as $key => $value) {
            Configuration::updateValue('FABRICS_SAMPLES_' . $key, $value, true);
        }

        $htmlKeys = ['INFO_TEXT','PAGE_INTRO_HTML','PAGE_WARNING'];
        $langKeys = ['LIMIT_ERROR_TOTAL','LIMIT_ERROR_PRODUCT','LIMIT_ERROR_CUSTOMER_TOTAL','LIMIT_ERROR_CUSTOMER_PRODUCT','LIMIT_ERROR_STOCK','RESULT_COUNT_TEXT','IN_CART_TEXT','REMOVE_SAMPLE_TEXT','LIMIT_REACHED_TEXT','PER_PAGE_TEXT','FILTER_ORDER_NEWEST','FILTER_ORDER_POPULAR','DEFAULT_SIZE','INFO_TEXT','PRODUCT_BLOCK_TITLE','PRODUCT_BLOCK_TEXT','PRODUCT_BLOCK_BUTTON','LISTING_BUTTON_TEXT','PAGE_TITLE','PAGE_INTRO_HTML','IMPORTANT_LABEL','PAGE_WARNING','FILTER_SEARCH_PLACEHOLDER','FILTER_ALL_CATEGORIES','FILTER_ORDER_NAME_ASC','FILTER_ORDER_NAME_DESC','FILTER_ORDER_PRICE_ASC','FILTER_ORDER_PRICE_DESC','FILTER_BUTTON_TEXT','CARD_LABEL','NO_IMAGE_TEXT','REFERENCE_LABEL','CATEGORY_LABEL','ADD_BUTTON_TEXT','ADDED_TEXT','PRODUCT_LINK_TEXT','EMPTY_FILTERED_TEXT','EMPTY_CONFIG_TEXT','META_TITLE','META_DESCRIPTION','COUPON_NAME','COUPON_EMAIL_SUBJECT'];
        foreach ($langKeys as $key) {
            $values = $this->getMultilangValue('FABRICS_SAMPLES_' . $key);
            if (in_array($key, $htmlKeys, true)) {
                $values = array_map(static fn (string $value): string => HtmlSanitizer::sanitize($value), $values);
            }
            Configuration::updateValue('FABRICS_SAMPLES_' . $key, $values, in_array($key, $htmlKeys, true));
        }

        if ((int) Tools::getValue('FABRICS_SAMPLES_REMOVE_PRODUCT_LINK_IMAGE') === 1) {
            $this->deleteProductLinkImage();
        }
        if (!empty($_FILES['FABRICS_SAMPLES_PRODUCT_LINK_IMAGE']['tmp_name'])) {
            $this->saveProductLinkImage($_FILES['FABRICS_SAMPLES_PRODUCT_LINK_IMAGE']);
        }

        if (!$this->errors) {
            $this->auditService()->log('configuration_update', 'module', 'fabricssamples', $oldConfiguration, $simpleValues, 'Configuración general actualizada.', (int) $this->context->shop->id);
            $this->confirmations[] = $this->module->l('Configuración guardada. Puede ser necesario vaciar la caché para aplicar un cambio de URL.');
        }
    }


/** @return list<array<string,string>> */
private function getButtonDefinitions(): array
{
    return [
        ['title'=>$this->module->l('Botón en la ficha del producto'),'text_label'=>$this->module->l('Texto del botón'),'text_key'=>'PRODUCT_BLOCK_BUTTON','style_prefix'=>'PRODUCT_BLOCK_BUTTON'],
        ['title'=>$this->module->l('Botón en categorías y listados'),'text_label'=>$this->module->l('Texto del botón'),'text_key'=>'LISTING_BUTTON_TEXT','style_prefix'=>'LISTING_BUTTON'],
        ['title'=>$this->module->l('Botón para añadir en la página de muestras'),'text_label'=>$this->module->l('Texto del botón'),'text_key'=>'ADD_BUTTON_TEXT','style_prefix'=>'ADD_BUTTON'],
        ['title'=>$this->module->l('Botón para ver el tejido por metros'),'text_label'=>$this->module->l('Texto del botón'),'text_key'=>'PRODUCT_LINK_TEXT','style_prefix'=>'PRODUCT_LINK_BUTTON'],
        ['title'=>$this->module->l('Botón de filtros de la página de muestras'),'text_label'=>$this->module->l('Texto del botón'),'text_key'=>'FILTER_BUTTON_TEXT','style_prefix'=>'FILTER_BUTTON'],
    ];
}

/** @return list<array<string,mixed>> */
private function buildButtonDesignFields(array $definition): array
{
    $prefix = (string) $definition['style_prefix'];
    return [
        ['type'=>'html','name'=>'fs_button_heading_' . strtolower($prefix),'html_content'=>'<hr><h3 style="margin:18px 0 12px"><i class="icon-magic"></i> ' . $definition['title'] . '</h3>'],
        ['type'=>'text','label'=>$definition['text_label'],'name'=>'FABRICS_SAMPLES_' . $definition['text_key'],'lang'=>true],
        ['type'=>'color','label'=>$this->module->l('Color de fondo'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_BG'],
        ['type'=>'color','label'=>$this->module->l('Color del texto'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_TEXT_COLOR'],
        ['type'=>'color','label'=>$this->module->l('Color del borde'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_BORDER_COLOR'],
        ['type'=>'text','label'=>$this->module->l('Grosor del borde'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_BORDER_WIDTH','class'=>'fixed-width-sm','suffix'=>'px','desc'=>$this->module->l('Entre 0 y 20 píxeles.')],
        ['type'=>'text','label'=>$this->module->l('Radio de las esquinas'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_RADIUS','class'=>'fixed-width-sm','suffix'=>'px','desc'=>$this->module->l('Entre 0 y 100 píxeles.')],
        ['type'=>'text','label'=>$this->module->l('Tamaño del texto'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_FONT_SIZE','class'=>'fixed-width-sm','suffix'=>'px','desc'=>$this->module->l('Entre 8 y 60 píxeles.')],
        ['type'=>'select','label'=>$this->module->l('Grosor del texto'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_FONT_WEIGHT','options'=>['query'=>$this->getFontWeightOptions(),'id'=>'id','name'=>'name']],
        ['type'=>'text','label'=>$this->module->l('Espacio interior vertical'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_PADDING_Y','class'=>'fixed-width-sm','suffix'=>'px','desc'=>$this->module->l('Relleno superior e inferior.')],
        ['type'=>'text','label'=>$this->module->l('Espacio interior horizontal'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_PADDING_X','class'=>'fixed-width-sm','suffix'=>'px','desc'=>$this->module->l('Relleno izquierdo y derecho.')],
        ['type'=>'text','label'=>$this->module->l('Separación superior'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_MARGIN_TOP','class'=>'fixed-width-sm','suffix'=>'px'],
        ['type'=>'text','label'=>$this->module->l('Separación inferior'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_MARGIN_BOTTOM','class'=>'fixed-width-sm','suffix'=>'px'],
        ['type'=>'select','label'=>$this->module->l('Ancho del botón'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_WIDTH','options'=>['query'=>$this->getButtonWidthOptions(),'id'=>'id','name'=>'name']],
        ['type'=>'text','label'=>$this->module->l('Ancho exacto'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_WIDTH_PX','class'=>'fixed-width-sm','suffix'=>'px','desc'=>$this->module->l('0 mantiene el ancho automático o completo elegido arriba. Entre 40 y 1200 píxeles.')],
        ['type'=>'text','label'=>$this->module->l('Alto exacto'),'name'=>'FABRICS_SAMPLES_' . $prefix . '_HEIGHT_PX','class'=>'fixed-width-sm','suffix'=>'px','desc'=>$this->module->l('0 mantiene el alto automático. Entre 16 y 300 píxeles. El valor indicado será la altura final del botón y prevalece sobre el espacio interior vertical.')],
    ];
}

/** @return list<string> */
private function getButtonStyleConfigurationKeys(): array
{
    $keys = [];
    foreach ($this->getButtonDefinitions() as $definition) {
        $prefix = (string) $definition['style_prefix'];
        foreach (['BG','TEXT_COLOR','BORDER_COLOR','BORDER_WIDTH','RADIUS','FONT_SIZE','FONT_WEIGHT','PADDING_Y','PADDING_X','MARGIN_TOP','MARGIN_BOTTOM','WIDTH','WIDTH_PX','HEIGHT_PX'] as $suffix) {
            $keys[] = $prefix . '_' . $suffix;
        }
    }
    return $keys;
}

/** @return array<string,mixed> */
private function getButtonStyleValues(): array
{
    $values = [];
    $defaults = ModuleConfiguration::defaults();
    foreach ($this->getButtonDefinitions() as $definition) {
        $prefix = (string) $definition['style_prefix'];
        $values[$prefix . '_BG'] = $this->validateColor(
            $this->submittedString($prefix . '_BG', (string) ($defaults[$prefix . '_BG'] ?? '#3d3d3d')),
            (string) ($defaults[$prefix . '_BG'] ?? '#3d3d3d')
        );
        $values[$prefix . '_TEXT_COLOR'] = $this->validateColor(
            $this->submittedString($prefix . '_TEXT_COLOR', (string) ($defaults[$prefix . '_TEXT_COLOR'] ?? '#ffffff')),
            (string) ($defaults[$prefix . '_TEXT_COLOR'] ?? '#ffffff')
        );
        $values[$prefix . '_BORDER_COLOR'] = $this->validateColor(
            $this->submittedString($prefix . '_BORDER_COLOR', (string) ($defaults[$prefix . '_BORDER_COLOR'] ?? '#3d3d3d')),
            (string) ($defaults[$prefix . '_BORDER_COLOR'] ?? '#3d3d3d')
        );
        $values[$prefix . '_BORDER_WIDTH'] = min(20, max(0, $this->submittedInt($prefix . '_BORDER_WIDTH', (int) ($defaults[$prefix . '_BORDER_WIDTH'] ?? 1))));
        $values[$prefix . '_RADIUS'] = min(100, max(0, $this->submittedInt($prefix . '_RADIUS', (int) ($defaults[$prefix . '_RADIUS'] ?? 0))));
        $values[$prefix . '_FONT_SIZE'] = min(60, max(8, $this->submittedInt($prefix . '_FONT_SIZE', (int) ($defaults[$prefix . '_FONT_SIZE'] ?? 16))));
        $fontWeight = $this->submittedInt($prefix . '_FONT_WEIGHT', (int) ($defaults[$prefix . '_FONT_WEIGHT'] ?? 600));
        $values[$prefix . '_FONT_WEIGHT'] = in_array($fontWeight, [300,400,500,600,700,800,900], true)
            ? $fontWeight
            : (int) ($defaults[$prefix . '_FONT_WEIGHT'] ?? 600);
        $values[$prefix . '_PADDING_Y'] = min(60, max(0, $this->submittedInt($prefix . '_PADDING_Y', (int) ($defaults[$prefix . '_PADDING_Y'] ?? 12))));
        $values[$prefix . '_PADDING_X'] = min(100, max(0, $this->submittedInt($prefix . '_PADDING_X', (int) ($defaults[$prefix . '_PADDING_X'] ?? 18))));
        $values[$prefix . '_MARGIN_TOP'] = min(100, max(0, $this->submittedInt($prefix . '_MARGIN_TOP', (int) ($defaults[$prefix . '_MARGIN_TOP'] ?? 0))));
        $values[$prefix . '_MARGIN_BOTTOM'] = min(100, max(0, $this->submittedInt($prefix . '_MARGIN_BOTTOM', (int) ($defaults[$prefix . '_MARGIN_BOTTOM'] ?? 0))));
        $width = $this->submittedString($prefix . '_WIDTH', (string) ($defaults[$prefix . '_WIDTH'] ?? 'auto'));
        $values[$prefix . '_WIDTH'] = in_array($width, ['auto','full'], true)
            ? $width
            : (string) ($defaults[$prefix . '_WIDTH'] ?? 'auto');
        $widthPx = $this->submittedInt($prefix . '_WIDTH_PX', (int) ($defaults[$prefix . '_WIDTH_PX'] ?? 0));
        $heightPx = $this->submittedInt($prefix . '_HEIGHT_PX', (int) ($defaults[$prefix . '_HEIGHT_PX'] ?? 0));
        $values[$prefix . '_WIDTH_PX'] = $widthPx === 0 ? 0 : min(1200, max(40, $widthPx));
        $values[$prefix . '_HEIGHT_PX'] = $heightPx === 0 ? 0 : min(300, max(16, $heightPx));
    }

    return $values;
}

private function getFontWeightOptions(): array
{
    return [
        ['id'=>300,'name'=>$this->module->l('Ligero')],
        ['id'=>400,'name'=>$this->module->l('Normal')],
        ['id'=>500,'name'=>$this->module->l('Medio')],
        ['id'=>600,'name'=>$this->module->l('Seminegrita')],
        ['id'=>700,'name'=>$this->module->l('Negrita')],
        ['id'=>800,'name'=>$this->module->l('Muy negrita')],
        ['id'=>900,'name'=>$this->module->l('Extra negrita')],
    ];
}

private function getButtonWidthOptions(): array
{
    return [
        ['id'=>'auto','name'=>$this->module->l('Automático')],
        ['id'=>'full','name'=>$this->module->l('Ancho completo')],
    ];
}

    private function sanitizePerPageOptions(string $value): string
    {
        $options = array_values(array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', $value) ?: []))));
        $options = array_values(array_filter($options, static fn (int $option): bool => $option >= 1 && $option <= 100));
        sort($options);
        return implode(',', $options !== [] ? $options : [12,24,36,48,72]);
    }

    private function getColumnOptions($min, $max)
    {
        $options = [];
        for ($i = (int) $min; $i <= (int) $max; ++$i) {
            $options[] = ['id' => $i, 'name' => (string) $i];
        }
        return $options;
    }

    private function getMultilangValue($fieldName)
    {
        $values = [];
        $key = str_replace('FABRICS_SAMPLES_', '', (string) $fieldName);
        $defaults = ModuleConfiguration::defaults();
        $requiredExamples = array_flip(ModuleConfiguration::catalogExampleTextKeys());
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $value = (string) Tools::getValue($fieldName . '_' . $idLang, '');
            if (isset($requiredExamples[$key]) && trim($value) === '') {
                $value = (string) ($defaults[$key] ?? '');
            }
            $values[$idLang] = $value;
        }
        return $values;
    }

    private function saveProductLinkImage(array $file)
    {
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->module->l('No se pudo subir la imagen del enlace.');
            return;
        }
        if ((int) $file['size'] > 4 * 1024 * 1024) {
            $this->errors[] = $this->module->l('La imagen no puede superar los 4 MB.');
            return;
        }
        $imageInfo = @getimagesize($file['tmp_name']);
        $allowedMimes = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        if (!$imageInfo || empty($allowedMimes[$imageInfo['mime']])) {
            $this->errors[] = $this->module->l('El archivo debe ser una imagen JPG, PNG, GIF o WEBP válida.');
            return;
        }
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width <= 0 || $height <= 0 || $width > 6000 || $height > 6000 || ($width * $height) > 25000000) {
            $this->errors[] = $this->module->l('La imagen supera las dimensiones de seguridad permitidas.');
            return;
        }
        if (!function_exists('imagecreatefromstring')) {
            $this->errors[] = $this->module->l('La extensión GD de PHP es obligatoria para procesar imágenes de forma segura.');
            return;
        }
        $contents = @file_get_contents($file['tmp_name']);
        $image = is_string($contents) ? @imagecreatefromstring($contents) : false;
        if ($image === false) {
            $this->errors[] = $this->module->l('No fue posible decodificar la imagen subida.');
            return;
        }
        $directory = $this->module->getLocalPath() . 'views/img/custom/';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
            $this->errors[] = $this->module->l('No se pudo crear la carpeta para guardar la imagen.');
            return;
        }
        $this->deleteProductLinkImage();
        $extension = $allowedMimes[$imageInfo['mime']];
        $filename = 'product-link-' . bin2hex(random_bytes(12)) . '.' . $extension;
        $destination = $directory . $filename;
        $saved = match ($extension) {
            'jpg' => imagejpeg($image, $destination, 90),
            'png' => imagepng($image, $destination, 6),
            'gif' => imagegif($image, $destination),
            'webp' => function_exists('imagewebp') && imagewebp($image, $destination, 90),
            default => false,
        };
        imagedestroy($image);
        if (!$saved) {
            @unlink($destination);
            $this->errors[] = $this->module->l('No se pudo guardar la imagen subida.');
            return;
        }
        @chmod($destination, 0644);
        Configuration::updateValue('FABRICS_SAMPLES_PRODUCT_LINK_IMAGE', $filename);
    }

    private function deleteProductLinkImage()
    {
        $filename = basename((string) Configuration::get('FABRICS_SAMPLES_PRODUCT_LINK_IMAGE'));
        if ($filename !== '') {
            $path = $this->module->getLocalPath() . 'views/img/custom/' . $filename;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        Configuration::deleteByName('FABRICS_SAMPLES_PRODUCT_LINK_IMAGE');
    }

    private function submittedInt(string $key, int $default): int
    {
        $name = 'FABRICS_SAMPLES_' . $key;
        $submitted = Tools::getValue($name, null);
        if ($submitted !== null && trim((string) $submitted) !== '') {
            return (int) $submitted;
        }

        $current = Configuration::get($name);
        return $current === false || trim((string) $current) === '' ? $default : (int) $current;
    }

    private function submittedString(string $key, string $default): string
    {
        $name = 'FABRICS_SAMPLES_' . $key;
        $submitted = Tools::getValue($name, null);
        if ($submitted !== null && trim((string) $submitted) !== '') {
            return (string) $submitted;
        }

        $current = Configuration::get($name);
        return $current === false || trim((string) $current) === '' ? $default : (string) $current;
    }

    private function validateColor($value, $default)
    {
        $value = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
    }

    private function sanitizeCustomCss($css)
    {
        return CssSanitizer::sanitize((string) $css);
    }

    private function renderProductList()
    {
        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = min(500, max(20, (int) Tools::getValue('limit', 50)));
        $offset = ($page - 1) * $limit;
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $q = trim((string) Tools::getValue('product_filter'));
        $result = $this->repository()->searchProducts($idShop, $idLang, $q, $offset, $limit);
        $total = $result['total'];
        $rows = $result['rows'];
        $this->context->smarty->assign([
            'fs_admin_products' => $rows,
            'fs_admin_page' => $page,
            'fs_admin_pages' => max(1, (int) ceil($total/$limit)),
            'fs_admin_pagination_window' => PaginationWindow::build($page, max(1, (int) ceil($total / $limit))),
            'fs_admin_limit' => $limit,
            'fs_admin_filter' => $q,
            'fs_admin_link' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite($this->controller_name),
            'fs_default_price' => PriceFormatter::format((float) Configuration::get('FABRICS_SAMPLES_DEFAULT_PRICE'), $this->context->currency),
        ]);
        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/product_list.tpl');
    }

    private function renderProductForm($idProduct)
    {
        $product = new Product($idProduct, false, (int) $this->context->language->id, (int) $this->context->shop->id);
        if (!Validate::isLoadedObject($product)) {
            return $this->module->displayError($this->module->l('Producto no válido.'));
        }
        $cfg = $this->repository()->findProductConfiguration($idProduct, (int) $this->context->shop->id);
        if (!$cfg) {
            $cfg = ['active'=>0,'use_default_price'=>1,'sample_price'=>Configuration::get('FABRICS_SAMPLES_DEFAULT_PRICE'),'size_text'=>'','info_text'=>'','available'=>1,'stock_mode'=>'availability','sample_stock'=>0,'max_per_order'=>Configuration::get('FABRICS_SAMPLES_MAX_PER_PRODUCT'),'max_per_customer'=>0,'sample_weight'=>Configuration::get('FABRICS_SAMPLES_DEFAULT_WEIGHT'),'tax_mode'=>'inherit','id_tax_rules_group'=>0,'internal_notes'=>''];
        }
        $explainers = $this->repository()->findProductExplainers($idProduct, (int) $this->context->shop->id);
        $fields = [[
            'form' => [
                'legend' => ['title' => sprintf($this->module->l('Muestra: %s'), $product->name), 'icon' => 'icon-cut'],
                'input' => [
                    ['type'=>'hidden','name'=>'id_product'],
                    ['type'=>'switch','label'=>$this->module->l('Permitir muestra'),'name'=>'active','is_bool'=>true,'values'=>[['id'=>'a1','value'=>1,'label'=>$this->module->l('Sí')],['id'=>'a0','value'=>0,'label'=>$this->module->l('No')]]],
                    ['type'=>'switch','label'=>$this->module->l('Usar precio general'),'name'=>'use_default_price','is_bool'=>true,'values'=>[['id'=>'d1','value'=>1,'label'=>$this->module->l('Sí')],['id'=>'d0','value'=>0,'label'=>$this->module->l('No')]]],
                    ['type'=>'text','label'=>$this->module->l('Precio específico sin impuestos'),'name'=>'sample_price','suffix'=>$this->context->currency->sign],
                    ['type'=>'text','label'=>$this->module->l('Tamaño mostrado'),'name'=>'size_text'],
                    ['type'=>'textarea','label'=>$this->module->l('Explicación dentro de la tarjeta'),'name'=>'card_explainer_html','lang'=>true,'autoload_rte'=>true,'rows'=>6,'desc'=>$this->module->l('Texto individual de esta muestra. Admite HTML e imágenes y puede editarse por idioma.')],
                    ['type'=>'switch','label'=>$this->module->l('Disponible'),'name'=>'available','is_bool'=>true,'values'=>[['id'=>'v1','value'=>1,'label'=>$this->module->l('Sí')],['id'=>'v0','value'=>0,'label'=>$this->module->l('No')]]],
                    ['type'=>'select','label'=>$this->module->l('Modo de stock'),'name'=>'stock_mode','options'=>['query'=>[['id'=>'availability','name'=>$this->module->l('Disponible/no disponible')],['id'=>'independent','name'=>$this->module->l('Stock independiente')],['id'=>'product','name'=>$this->module->l('Según stock del producto')],['id'=>'product_minimum','name'=>$this->module->l('Según mínimo del producto')]],'id'=>'id','name'=>'name']],
                    ['type'=>'text','label'=>$this->module->l('Stock de muestras'),'name'=>'sample_stock'],
                    ['type'=>'text','label'=>$this->module->l('Máximo por pedido'),'name'=>'max_per_order'],
                    ['type'=>'text','label'=>$this->module->l('Máximo por cliente'),'name'=>'max_per_customer'],
                    ['type'=>'text','label'=>$this->module->l('Peso'),'name'=>'sample_weight','suffix'=>'kg'],
                    ['type'=>'textarea','label'=>$this->module->l('Notas internas'),'name'=>'internal_notes','rows'=>3],
                ],
                'submit' => ['title' => $this->module->l('Guardar muestra')],
                'buttons' => [['href'=>self::$currentIndex.'&token='.Tools::getAdminTokenLite($this->controller_name),'title'=>$this->module->l('Volver'),'icon'=>'process-icon-back']],
            ],
        ]];
        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->controller_name;
        $helper->token = Tools::getAdminTokenLite($this->controller_name);
        $helper->currentIndex = self::$currentIndex;
        $helper->submit_action = 'submitFabricSampleProduct';
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->fields_value = $cfg;
        $helper->fields_value['id_product'] = $idProduct;
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $helper->fields_value['card_explainer_html'][$idLang] = $explainers[$idLang] ?? '';
        }
        return $helper->generateForm($fields);
    }

    private function saveProductConfig()
    {
        $idProduct = (int) Tools::getValue('id_product');
        if (!$idProduct || !Validate::isLoadedObject(new Product($idProduct))) {
            $this->errors[] = $this->module->l('Producto no válido.');
            return;
        }
        $oldData = $this->repository()->findProductConfiguration($idProduct, (int) $this->context->shop->id);
        $data = [
            'active'=>(int) Tools::getValue('active'),
            'use_default_price'=>(int) Tools::getValue('use_default_price'),
            'sample_price'=>max(0,(float) str_replace(',','.',Tools::getValue('sample_price'))),
            'size_text'=>pSQL((string) Tools::getValue('size_text')),
            'info_text'=>'',
            'available'=>(int) Tools::getValue('available'),
            'stock_mode'=>pSQL((string) Tools::getValue('stock_mode')),
            'sample_stock'=>max(0,(int) Tools::getValue('sample_stock')),
            'max_per_order'=>max(0,(int) Tools::getValue('max_per_order')),
            'max_per_customer'=>max(0,(int) Tools::getValue('max_per_customer')),
            'sample_weight'=>max(0,(float) str_replace(',','.',Tools::getValue('sample_weight'))),
            'tax_mode'=>'inherit',
            'id_tax_rules_group'=>0,
            'internal_notes'=>pSQL((string) Tools::getValue('internal_notes'), true),
        ];
        if (!$this->upsertProduct($idProduct, $data)) {
            $this->errors[] = $this->module->l('No se pudo guardar la configuración de la muestra.');
            return;
        }
        $explainers = array_map(
            static fn (string $html): string => HtmlSanitizer::sanitize($html),
            $this->getMultilangValue('card_explainer_html')
        );
        if (!$this->repository()->upsertProductExplainers($idProduct, (int) $this->context->shop->id, $explainers)) {
            $this->errors[] = $this->module->l('La muestra se guardó, pero no fue posible guardar sus explicaciones por idioma.');
            return;
        }
        $this->auditService()->log('product_configuration_update', 'product', $idProduct, $oldData, $data, 'Configuración de muestra actualizada.', (int) $this->context->shop->id);
        $this->confirmations[] = $this->module->l('Configuración del producto guardada.');
    }

    private function upsertProduct($idProduct, array $data)
    {
        return $this->repository()->upsertProduct(
            (int) $idProduct,
            (int) $this->context->shop->id,
            $data,
            [
                'sample_price' => (float) Configuration::get('FABRICS_SAMPLES_DEFAULT_PRICE'),
                'max_per_order' => (int) Configuration::get('FABRICS_SAMPLES_MAX_PER_PRODUCT'),
                'sample_weight' => (float) Configuration::get('FABRICS_SAMPLES_DEFAULT_WEIGHT'),
            ]
        );
    }

    private function renderOrderStateSelector(): string
    {
        $rawConfigured = trim((string) Configuration::get('FABRICS_SAMPLES_LIMIT_ORDER_STATES'));
        $configured = array_values(array_unique(array_filter(array_map(
            'intval',
            preg_split('/[^0-9]+/', $rawConfigured) ?: []
        ))));
        if ($configured === [] && $rawConfigured !== 'none') {
            $configured = array_values(array_unique(array_filter(array_map('intval', [
                Configuration::get('PS_OS_PAYMENT'),
                Configuration::get('PS_OS_PREPARATION'),
                Configuration::get('PS_OS_SHIPPING'),
                Configuration::get('PS_OS_DELIVERED'),
            ]))));
        }
        $states = OrderState::getOrderStates((int) $this->context->language->id);
        $html = '<div class="form-group"><label class="control-label col-lg-3">' . $this->module->l('Estados de pedido que cuentan para los límites') . '</label><div class="col-lg-9">';
        $html .= '<div class="well" style="max-height:260px;overflow:auto">';
        foreach ($states as $state) {
            $id = (int) ($state['id_order_state'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $checked = in_array($id, $configured, true) ? ' checked' : '';
            $html .= '<label style="display:block;margin-bottom:7px"><input type="checkbox" name="FABRICS_SAMPLES_LIMIT_ORDER_STATES[]" value="' . $id . '"' . $checked . '> '
                . Tools::safeOutput((string) ($state['name'] ?? ('Estado ' . $id))) . ' <span class="text-muted">(#' . $id . ')</span></label>';
        }
        $html .= '</div><p class="help-block">' . $this->module->l('Solo las muestras pertenecientes a pedidos cuyo estado actual esté marcado se acumulan en los límites históricos. Los pedidos cancelados, erróneos o reembolsados pueden excluirse desmarcando sus estados.') . '</p></div></div>';
        return $html;
    }

    private function auditService(): AuditService
    {
        return new AuditService(new AuditRepository());
    }

    private function repository(): AdminSampleRepository
    {
        return $this->sampleRepository ??= new AdminSampleRepository();
    }
}
