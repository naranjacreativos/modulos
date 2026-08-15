<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Configuration;

final class ModuleConfiguration
{
    public const PREFIX = 'FABRICS_SAMPLES_';

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return [
            'SCHEMA_VERSION' => '2.15.21',
            'DEFAULT_PRICE' => '2.50',
            'DEFAULT_SIZE' => 'Muestra aproximada de 10 × 10 cm',
            'INFO_TEXT' => 'El color puede variar según la pantalla y la posición del estampado puede variar.',
            'DEFAULT_WEIGHT' => '0.02',
            'MAX_TOTAL' => '10',
            'MAX_PER_PRODUCT' => '1',
            'LIMITS_ENABLED' => '1',
            'MAX_CUSTOMER_TOTAL_PERIOD' => '0',
            'MAX_CUSTOMER_PRODUCT_PERIOD' => '0',
            'CUSTOMER_PERIOD_DAYS' => '30',
            'LIMIT_GUESTS' => '1',
            'LIMIT_EXEMPT_GROUPS' => '',
            'LIMIT_ORDER_STATES' => '',
            'LIMIT_ERROR_TOTAL' => 'El máximo total es de %limit% muestras por pedido.',
            'LIMIT_ERROR_PRODUCT' => 'Solo se permiten %limit% muestras de esta referencia.',
            'LIMIT_ERROR_CUSTOMER_TOTAL' => 'Has alcanzado el máximo de %limit% muestras durante los últimos %days% días.',
            'LIMIT_ERROR_CUSTOMER_PRODUCT' => 'Has alcanzado el máximo de %limit% muestras de esta referencia durante los últimos %days% días.',
            'LIMIT_ERROR_STOCK' => 'No hay suficiente stock de muestras.',
            'SHOW_PRODUCT' => '1',
            'SHOW_LISTING' => '1',
            'ENABLE_CATALOG' => '1',
            'FRIENDLY_URL' => 'solicitar-muestras',
            'PRODUCTS_PER_PAGE' => '24',
            'PAGE_AJAX_FILTERS' => '1',
            'PAGE_SHOW_RESULT_COUNT' => '1',
            'PAGE_SHOW_IN_CART_STATUS' => '1',
            'PAGE_ALLOW_REMOVE' => '1',
            'PAGE_PER_PAGE_OPTIONS' => '12,24,36,48,72',
            'RESULT_COUNT_TEXT' => '%count% muestras encontradas',
            'IN_CART_TEXT' => 'En el carrito: %count%',
            'REMOVE_SAMPLE_TEXT' => 'Quitar muestra',
            'LIMIT_REACHED_TEXT' => 'Límite alcanzado',
            'PER_PAGE_TEXT' => 'Mostrar',
            'FILTER_ORDER_NEWEST' => 'Más recientes',
            'FILTER_ORDER_POPULAR' => 'Más solicitadas',
            'PRODUCT_BLOCK_TITLE' => '¿Quieres comprobar el color y la textura antes de comprar?',
            'PRODUCT_BLOCK_TEXT' => 'Solicita una muestra aproximada de 10 × 10 cm desde nuestra página de muestras.',
            'PRODUCT_BLOCK_BUTTON' => 'Solicitar una muestra',
            'LISTING_BUTTON_TEXT' => 'Solicitar muestra',
            'TAX_MODE' => 'inherit',
            'GLOBAL_TAX_RULES_GROUP' => '0',
            'STOCK_MODE' => 'availability',
            'STOCK_MINIMUM' => '1',
            'LOW_STOCK_THRESHOLD' => '5',
            'PAGE_TITLE' => 'Solicita muestras antes de comprar tu tejido',
            'PAGE_INTRO_HTML' => '<p>Comprueba el color, la textura y el acabado del tejido antes de realizar tu pedido.</p>',
            'PAGE_WARNING' => 'Cada artículo de esta página es una muestra aproximada de 10 × 10 cm, no un metro de tejido. El color y la posición del estampado pueden variar.',
            'PAGE_ACCENT_COLOR' => '#202020',
            'PAGE_BACKGROUND_COLOR' => '#ffffff',
            'PRODUCT_LINK_BUTTON_BG' => '#58a178',
            'PRODUCT_LINK_BUTTON_TEXT_COLOR' => '#ffffff',
            'PRODUCT_LINK_BUTTON_RADIUS' => '0',
            'PRODUCT_LINK_BUTTON_BORDER_WIDTH' => '1',
            'PRODUCT_LINK_BUTTON_FONT_SIZE' => '16',
            'PRODUCT_LINK_BUTTON_GAP' => '8',
            'PRODUCT_BLOCK_BUTTON_BG' => '#3d3d3d',
            'PRODUCT_BLOCK_BUTTON_TEXT_COLOR' => '#ffffff',
            'PRODUCT_BLOCK_BUTTON_BORDER_COLOR' => '#3d3d3d',
            'PRODUCT_BLOCK_BUTTON_BORDER_WIDTH' => '1',
            'PRODUCT_BLOCK_BUTTON_RADIUS' => '0',
            'PRODUCT_BLOCK_BUTTON_FONT_SIZE' => '16',
            'PRODUCT_BLOCK_BUTTON_FONT_WEIGHT' => '600',
            'PRODUCT_BLOCK_BUTTON_PADDING_Y' => '12',
            'PRODUCT_BLOCK_BUTTON_PADDING_X' => '18',
            'PRODUCT_BLOCK_BUTTON_MARGIN_TOP' => '8',
            'PRODUCT_BLOCK_BUTTON_MARGIN_BOTTOM' => '0',
            'PRODUCT_BLOCK_BUTTON_WIDTH' => 'auto',
            'PRODUCT_BLOCK_BUTTON_WIDTH_PX' => '0',
            'PRODUCT_BLOCK_BUTTON_HEIGHT_PX' => '0',
            'LISTING_BUTTON_BG' => '#3d3d3d',
            'LISTING_BUTTON_TEXT_COLOR' => '#ffffff',
            'LISTING_BUTTON_BORDER_COLOR' => '#3d3d3d',
            'LISTING_BUTTON_BORDER_WIDTH' => '1',
            'LISTING_BUTTON_RADIUS' => '0',
            'LISTING_BUTTON_FONT_SIZE' => '16',
            'LISTING_BUTTON_FONT_WEIGHT' => '500',
            'LISTING_BUTTON_PADDING_Y' => '14',
            'LISTING_BUTTON_PADDING_X' => '16',
            'LISTING_BUTTON_MARGIN_TOP' => '8',
            'LISTING_BUTTON_MARGIN_BOTTOM' => '0',
            'LISTING_BUTTON_WIDTH' => 'full',
            'LISTING_BUTTON_WIDTH_PX' => '0',
            'LISTING_BUTTON_HEIGHT_PX' => '0',
            'ADD_BUTTON_BG' => '#58a178',
            'ADD_BUTTON_TEXT_COLOR' => '#ffffff',
            'ADD_BUTTON_BORDER_COLOR' => '#58a178',
            'ADD_BUTTON_BORDER_WIDTH' => '1',
            'ADD_BUTTON_RADIUS' => '0',
            'ADD_BUTTON_FONT_SIZE' => '16',
            'ADD_BUTTON_FONT_WEIGHT' => '700',
            'ADD_BUTTON_PADDING_Y' => '12',
            'ADD_BUTTON_PADDING_X' => '16',
            'ADD_BUTTON_MARGIN_TOP' => '4',
            'ADD_BUTTON_MARGIN_BOTTOM' => '0',
            'ADD_BUTTON_WIDTH' => 'full',
            'ADD_BUTTON_WIDTH_PX' => '0',
            'ADD_BUTTON_HEIGHT_PX' => '0',
            'PRODUCT_LINK_BUTTON_BORDER_COLOR' => '#58a178',
            'PRODUCT_LINK_BUTTON_FONT_WEIGHT' => '700',
            'PRODUCT_LINK_BUTTON_PADDING_Y' => '12',
            'PRODUCT_LINK_BUTTON_PADDING_X' => '16',
            'PRODUCT_LINK_BUTTON_MARGIN_TOP' => '8',
            'PRODUCT_LINK_BUTTON_MARGIN_BOTTOM' => '0',
            'PRODUCT_LINK_BUTTON_WIDTH' => 'full',
            'PRODUCT_LINK_BUTTON_WIDTH_PX' => '0',
            'PRODUCT_LINK_BUTTON_HEIGHT_PX' => '0',
            'FILTER_BUTTON_BG' => '#3d3d3d',
            'FILTER_BUTTON_TEXT_COLOR' => '#ffffff',
            'FILTER_BUTTON_BORDER_COLOR' => '#3d3d3d',
            'FILTER_BUTTON_BORDER_WIDTH' => '1',
            'FILTER_BUTTON_RADIUS' => '0',
            'FILTER_BUTTON_FONT_SIZE' => '16',
            'FILTER_BUTTON_FONT_WEIGHT' => '600',
            'FILTER_BUTTON_PADDING_Y' => '10',
            'FILTER_BUTTON_PADDING_X' => '18',
            'FILTER_BUTTON_MARGIN_TOP' => '0',
            'FILTER_BUTTON_MARGIN_BOTTOM' => '0',
            'FILTER_BUTTON_WIDTH' => 'auto',
            'FILTER_BUTTON_WIDTH_PX' => '0',
            'FILTER_BUTTON_HEIGHT_PX' => '0',
            'PAGE_COLUMNS' => '4',
            'PAGE_COLUMNS_DESKTOP' => '4',
            'PAGE_COLUMNS_TABLET' => '3',
            'PAGE_COLUMNS_MOBILE' => '1',
            'IMPORTANT_LABEL' => 'Importante:',
            'FILTER_SEARCH_PLACEHOLDER' => 'Buscar por nombre o referencia',
            'FILTER_ALL_CATEGORIES' => 'Todas las categorías',
            'FILTER_ORDER_NAME_ASC' => 'Nombre A-Z',
            'FILTER_ORDER_NAME_DESC' => 'Nombre Z-A',
            'FILTER_ORDER_PRICE_ASC' => 'Precio menor',
            'FILTER_ORDER_PRICE_DESC' => 'Precio mayor',
            'FILTER_BUTTON_TEXT' => 'Filtrar muestras',
            'CARD_LABEL' => 'MUESTRA DE TEJIDO',
            'NO_IMAGE_TEXT' => 'Imagen no disponible',
            'REFERENCE_LABEL' => 'Referencia:',
            'CATEGORY_LABEL' => 'Categoría:',
            'ADD_BUTTON_TEXT' => 'Añadir muestra al carrito',
            'ADDED_TEXT' => 'Muestra añadida',
            'PRODUCT_LINK_TEXT' => 'Ver el tejido por metros',
            'PRODUCT_LINK_IMAGE' => '',
            'EMPTY_FILTERED_TEXT' => 'Hay productos configurados como muestra, pero ninguno coincide con los filtros actuales o está activo en esta tienda.',
            'EMPTY_CONFIG_TEXT' => 'Todavía no hay productos activados para solicitar muestras. Actívalos desde Muestras de tejidos > Productos y configuración.',
            'PAGE_CUSTOM_CSS' => '',
            'RETENTION_AUDIT_DAYS' => '365',
            'RETENTION_LIMIT_EVENT_DAYS' => '365',
            'RETENTION_LIMIT_RESET_DAYS' => '730',
            'BACKUP_RETENTION_COUNT' => '10',
            'BACKUP_RETENTION_DAYS' => '90',
            'AJAX_RATE_LIMIT_PER_MINUTE' => '30',
            'RETENTION_LAST_RUN' => '',
            'META_TITLE' => 'Solicitar muestras de tejidos',
            'META_DESCRIPTION' => 'Solicita muestras de tejidos antes de comprar por metros y comprueba el color, la textura y el estampado.',
            'SHOW_CARD_LABEL' => '1',
            'SHOW_CARD_IMAGE' => '1',
            'SHOW_CARD_NAME' => '1',
            'SHOW_CARD_REFERENCE' => '1',
            'SHOW_CARD_CATEGORY' => '1',
            'SHOW_CARD_EXPLAINER' => '1',
            'SHOW_CARD_PRICE' => '1',
            'SHOW_CARD_PRODUCT_LINK' => '1',
            'COUPON_ENABLED' => '1',
            'COUPON_TRIGGER' => 'order',
            'COUPON_VALUE_MODE' => 'full',
            'COUPON_SAMPLE_PERCENT' => '100',
            'COUPON_FIXED_AMOUNT' => '0',
            'COUPON_MINIMUM_ORDER' => '0',
            'COUPON_VALID_DAYS' => '60',
            'COUPON_LIMIT_TO_PRODUCTS' => '1',
            'COUPON_PARTIAL_USE' => '0',
            'COUPON_SEND_EMAIL' => '0',
            'COUPON_CODE_PREFIX' => 'MUESTRA',
            'COUPON_NAME' => 'Descuento por muestras de tejidos',
            'COUPON_EMAIL_SUBJECT' => 'Tu descuento por las muestras de tejidos',
        ];
    }


    /** @return list<string> */
    public static function obsoleteKeys(): array
    {
        return [
            'ALLOW_ONLY_SAMPLES',
            'ALLOW_OUT_OF_STOCK',
            'SHOW_BADGE',
            'PAGE_SHOW_CART_SUMMARY',
            'CART_SUMMARY_TEXT',
            'ENABLE_AJAX',
            'BUTTON_TEXT',
            'KEEP_DATA',
            'META_ROBOTS',
            'MANUFACTURER_LABEL',
            'FILTER_ALL_MANUFACTURERS',
            'FORMAT_LABEL',
            'SHOW_CARD_MANUFACTURER',
            'SHOW_CARD_FORMAT',
            'CARD_EXPLAINER_HTML',
            'PAGE_VIEW_TOGGLE',
            'PAGE_DEFAULT_VIEW',
            'VIEW_GRID_TEXT',
            'VIEW_LIST_TEXT',
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::defaults());
    }

    /** @return list<string> */
    public static function multilingualKeys(): array
    {
        return [
            'DEFAULT_SIZE',
            'INFO_TEXT',
            'LIMIT_ERROR_TOTAL',
            'LIMIT_ERROR_PRODUCT',
            'LIMIT_ERROR_CUSTOMER_TOTAL',
            'LIMIT_ERROR_CUSTOMER_PRODUCT',
            'LIMIT_ERROR_STOCK',
            'RESULT_COUNT_TEXT',
            'IN_CART_TEXT',
            'REMOVE_SAMPLE_TEXT',
            'LIMIT_REACHED_TEXT',
            'PER_PAGE_TEXT',
            'FILTER_ORDER_NEWEST',
            'FILTER_ORDER_POPULAR',
            'PRODUCT_BLOCK_TITLE',
            'PRODUCT_BLOCK_TEXT',
            'PRODUCT_BLOCK_BUTTON',
            'LISTING_BUTTON_TEXT',
            'PAGE_TITLE',
            'PAGE_INTRO_HTML',
            'IMPORTANT_LABEL',
            'PAGE_WARNING',
            'FILTER_SEARCH_PLACEHOLDER',
            'FILTER_ALL_CATEGORIES',
            'FILTER_ORDER_NAME_ASC',
            'FILTER_ORDER_NAME_DESC',
            'FILTER_ORDER_PRICE_ASC',
            'FILTER_ORDER_PRICE_DESC',
            'FILTER_BUTTON_TEXT',
            'CARD_LABEL',
            'NO_IMAGE_TEXT',
            'REFERENCE_LABEL',
            'CATEGORY_LABEL',
            'ADD_BUTTON_TEXT',
            'ADDED_TEXT',
            'PRODUCT_LINK_TEXT',
            'EMPTY_FILTERED_TEXT',
            'EMPTY_CONFIG_TEXT',
            'META_TITLE',
            'META_DESCRIPTION',
            'COUPON_NAME',
            'COUPON_EMAIL_SUBJECT',
        ];
    }

    /** @return list<string> */
    public static function htmlKeys(): array
    {
        return ['INFO_TEXT', 'PAGE_INTRO_HTML', 'PAGE_WARNING'];
    }

    /** @return list<string> */
    public static function catalogExampleTextKeys(): array
    {
        return [
            'RESULT_COUNT_TEXT',
            'IN_CART_TEXT',
            'REMOVE_SAMPLE_TEXT',
            'LIMIT_REACHED_TEXT',
            'PER_PAGE_TEXT',
            'FILTER_ORDER_NEWEST',
            'FILTER_ORDER_POPULAR',
        ];
    }

    /** @return list<string> */
    public static function catalogCriticalKeys(): array
    {
        return [
            'PRODUCTS_PER_PAGE',
            'PAGE_PER_PAGE_OPTIONS',
            'PAGE_COLUMNS_DESKTOP',
            'PAGE_COLUMNS_TABLET',
            'PAGE_COLUMNS_MOBILE',
            'SHOW_CARD_LABEL',
            'SHOW_CARD_IMAGE',
            'SHOW_CARD_NAME',
            'SHOW_CARD_REFERENCE',
            'SHOW_CARD_CATEGORY',
            'SHOW_CARD_EXPLAINER',
            'SHOW_CARD_PRICE',
            'SHOW_CARD_PRODUCT_LINK',
            'FILTER_BUTTON_BG',
            'FILTER_BUTTON_TEXT_COLOR',
            'FILTER_BUTTON_BORDER_COLOR',
            'FILTER_BUTTON_BORDER_WIDTH',
            'FILTER_BUTTON_RADIUS',
            'FILTER_BUTTON_FONT_SIZE',
            'FILTER_BUTTON_FONT_WEIGHT',
            'FILTER_BUTTON_PADDING_Y',
            'FILTER_BUTTON_PADDING_X',
            'FILTER_BUTTON_WIDTH',
            'FILTER_BUTTON_WIDTH_PX',
            'FILTER_BUTTON_HEIGHT_PX',
            'ADD_BUTTON_BG',
            'ADD_BUTTON_TEXT_COLOR',
            'ADD_BUTTON_BORDER_COLOR',
            'ADD_BUTTON_BORDER_WIDTH',
            'ADD_BUTTON_FONT_SIZE',
            'ADD_BUTTON_FONT_WEIGHT',
            'ADD_BUTTON_PADDING_Y',
            'ADD_BUTTON_PADDING_X',
            'ADD_BUTTON_WIDTH',
            'ADD_BUTTON_WIDTH_PX',
            'ADD_BUTTON_HEIGHT_PX',
        ];
    }

    public function installDefaults(): bool
    {
        $languages = class_exists('Language') ? \Language::getLanguages(false) : [];
        $multilingualKeys = array_flip(self::multilingualKeys());
        $htmlKeys = array_flip(self::htmlKeys());

        foreach (self::defaults() as $key => $value) {
            $configurationValue = $value;
            if (isset($multilingualKeys[$key]) && $languages !== []) {
                $configurationValue = [];
                foreach ($languages as $language) {
                    $configurationValue[(int) $language['id_lang']] = $value;
                }
            }

            if (!\Configuration::updateValue(
                self::PREFIX . $key,
                $configurationValue,
                isset($htmlKeys[$key])
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Restores missing configuration without overwriting valid merchant values.
     * Empty values are only replaced for keys explicitly marked as required.
     *
     * @param list<string> $replaceEmptyKeys
     */
    public function repairMissingDefaults(array $replaceEmptyKeys = []): bool
    {
        $defaults = self::defaults();
        $languages = class_exists('Language') ? \Language::getLanguages(false) : [];
        $multilingualKeys = array_flip(self::multilingualKeys());
        $htmlKeys = array_flip(self::htmlKeys());
        $replaceEmpty = array_flip($replaceEmptyKeys);

        foreach ($defaults as $key => $default) {
            $name = self::PREFIX . $key;
            if (isset($multilingualKeys[$key]) && $languages !== []) {
                $values = [];
                $mustUpdate = false;
                foreach ($languages as $language) {
                    $idLang = (int) $language['id_lang'];
                    $current = \Configuration::get($name, $idLang);
                    if ($current === false || (isset($replaceEmpty[$key]) && trim((string) $current) === '')) {
                        $current = $default;
                        $mustUpdate = true;
                    }
                    $values[$idLang] = (string) $current;
                }
                if ($mustUpdate && !\Configuration::updateValue($name, $values, isset($htmlKeys[$key]))) {
                    return false;
                }
                continue;
            }

            $current = \Configuration::get($name);
            if ($current !== false && !(isset($replaceEmpty[$key]) && trim((string) $current) === '')) {
                continue;
            }
            if (!\Configuration::updateValue($name, $default, isset($htmlKeys[$key]))) {
                return false;
            }
        }

        return true;
    }

    public function deleteAll(): bool
    {
        $ok = true;
        foreach (array_values(array_unique(array_merge(self::keys(), self::obsoleteKeys()))) as $key) {
            $ok = \Configuration::deleteByName(self::PREFIX . $key) && $ok;
        }

        return $ok;
    }

    public function get(string $key, ?int $idLang = null)
    {
        return \Configuration::get(self::PREFIX . $key, $idLang);
    }

    public function getString(string $key, ?int $idLang = null, string $fallback = ''): string
    {
        $value = (string) $this->get($key, $idLang);
        return $value !== '' ? $value : $fallback;
    }

    public function getInt(string $key, int $fallback = 0): int
    {
        $value = $this->get($key);
        return $value === false || $value === '' ? $fallback : (int) $value;
    }

    public function getFloat(string $key, float $fallback = 0.0): float
    {
        $value = $this->get($key);
        return $value === false || $value === '' ? $fallback : (float) $value;
    }

    public function getBool(string $key, bool $fallback = false): bool
    {
        $value = $this->get($key);
        return $value === false || $value === '' ? $fallback : (bool) $value;
    }
}
