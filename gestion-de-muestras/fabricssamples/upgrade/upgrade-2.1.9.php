<?php
if (!defined('_PS_VERSION_')) { exit; }
function upgrade_module_2_1_9($module)
{
    $languages = Language::getLanguages(false);
    $defaults = [
        'PRODUCT_BLOCK_TITLE'=>'¿Quieres comprobar el color y la textura antes de comprar?',
        'PRODUCT_BLOCK_TEXT'=>'Solicita una muestra aproximada de 10 × 10 cm desde nuestra página de muestras.',
        'PRODUCT_BLOCK_BUTTON'=>'Solicitar una muestra',
        'LISTING_BUTTON_TEXT'=>'Solicitar muestra',
        'IMPORTANT_LABEL'=>'Importante:', 'FILTER_SEARCH_PLACEHOLDER'=>'Buscar por nombre o referencia',
        'FILTER_ALL_CATEGORIES'=>'Todas las categorías', 'FILTER_ALL_MANUFACTURERS'=>'Todos los fabricantes',
        'FILTER_ORDER_NAME_ASC'=>'Nombre A-Z', 'FILTER_ORDER_NAME_DESC'=>'Nombre Z-A',
        'FILTER_ORDER_PRICE_ASC'=>'Precio menor', 'FILTER_ORDER_PRICE_DESC'=>'Precio mayor',
        'FILTER_BUTTON_TEXT'=>'Filtrar muestras', 'CARD_LABEL'=>'MUESTRA DE TEJIDO',
        'NO_IMAGE_TEXT'=>'Imagen no disponible', 'REFERENCE_LABEL'=>'Referencia:', 'CATEGORY_LABEL'=>'Categoría:',
        'MANUFACTURER_LABEL'=>'Fabricante:', 'FORMAT_LABEL'=>'Formato:',
        'CARD_EXPLAINER_HTML'=>'<p>Recibirás un recorte de muestra, no una unidad ni un metro de tejido.</p>',
        'ADD_BUTTON_TEXT'=>'Añadir muestra al carrito', 'ADDED_TEXT'=>'Muestra añadida',
        'PRODUCT_LINK_TEXT'=>'Ver el tejido por metros',
        'EMPTY_FILTERED_TEXT'=>'Hay productos configurados como muestra, pero ninguno coincide con los filtros actuales o está activo en esta tienda.',
        'EMPTY_CONFIG_TEXT'=>'Todavía no hay productos activados para solicitar muestras. Actívalos desde Muestras de tejidos > Productos y configuración.',
    ];
    foreach ($defaults as $key => $default) {
        if (Configuration::get('FABRICS_SAMPLES_' . $key) === false) {
            $values = []; foreach ($languages as $language) { $values[(int)$language['id_lang']] = $default; }
            Configuration::updateValue('FABRICS_SAMPLES_' . $key, $values, in_array($key, ['CARD_EXPLAINER_HTML'], true));
        }
    }
    Configuration::updateValue('FABRICS_SAMPLES_PAGE_COLUMNS_DESKTOP', (int)(Configuration::get('FABRICS_SAMPLES_PAGE_COLUMNS') ?: 4));
    Configuration::updateValue('FABRICS_SAMPLES_PAGE_COLUMNS_TABLET', 3);
    Configuration::updateValue('FABRICS_SAMPLES_PAGE_COLUMNS_MOBILE', 1);
    return true;
}
