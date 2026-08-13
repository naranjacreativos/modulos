<?php
function upgrade_module_2_0_0($module)
{
    // La versión 2 conserva configuraciones, productos activados e historial.
    // Los scripts AJAX antiguos dejan de cargarse; la solicitud se procesa por POST y redirección al carrito.
    return $module->registerHook('moduleRoutes');
}
