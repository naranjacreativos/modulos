<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_1_6($module)
{
    $defaults = [
        'PAGE_TITLE' => 'Solicita muestras antes de comprar tu tejido',
        'PAGE_INTRO_HTML' => '<p>Comprueba el color, la textura y el acabado del tejido antes de realizar tu pedido.</p>',
        'PAGE_WARNING' => 'Cada artículo de esta página es una muestra aproximada de 10 × 10 cm, no un metro de tejido. El color y la posición del estampado pueden variar.',
        'PAGE_ACCENT_COLOR' => '#202020',
        'PAGE_BACKGROUND_COLOR' => '#ffffff',
        'PAGE_COLUMNS' => '4',
        'PAGE_CUSTOM_CSS' => '',
        'META_TITLE' => 'Solicitar muestras de tejidos',
        'META_DESCRIPTION' => 'Solicita muestras de tejidos antes de comprar por metros y comprueba el color, la textura y el estampado.',
    ];
    foreach ($defaults as $key => $value) {
        $name = 'FABRICS_SAMPLES_' . $key;
        if (Configuration::get($name) === false) {
            Configuration::updateValue($name, $value, true);
        }
    }
    return $module->registerHook('moduleRoutes')
        && $module->registerHook('actionPresentProductListing')
        && $module->registerHook('actionPresentCart');
}
