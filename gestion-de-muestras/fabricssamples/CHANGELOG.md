# Changelog

## 2.15.20 - Correo profesional de cupones

- Rediseña el correo del cupón con una plantilla responsive compatible con clientes de correo, código destacado, resumen de valor, compra mínima y caducidad.
- Añade el nombre de la tienda y un botón directo para volver a comprar.
- Muestra las condiciones reales del cupón: si está limitado a los tejidos muestreados o si puede aplicarse al catálogo general.
- Reutiliza el color principal configurado en la pestaña Diseño como color de acento del correo, con validación, valor seguro de respaldo y contraste automático del texto del botón.
- Mejora también el correo de reemisión para mantener una identidad visual y unas condiciones coherentes.
- Mantiene versiones HTML y texto plano en español e inglés.
- No cambia la lógica de cálculo, creación, validez ni consumo de los cupones.

## 2.15.19 - Corrección de estadísticas del panel

- Corrige el panel de `Muestras vendidas`, `Ingresos` y `Pedidos con muestras` cuando el pedido contiene muestras pero falta el histórico auxiliar en `fabricssamples_order`.
- Las estadísticas reconocen ahora las muestras directamente desde los `order_detail` nativos de PrestaShop y conservan el histórico auxiliar únicamente como respaldo para datos antiguos.
- Evita dobles conteos cuando una misma muestra existe tanto en el pedido nativo como en el histórico del módulo.
- Respeta las exclusiones de histórico configuradas desde diagnóstico al calcular el respaldo nativo.

## 2.15.18 - Identidad visible del módulo

- Cambia el nombre visible del módulo a **Gestión de muestras** manteniendo el nombre técnico `fabricssamples` para conservar compatibilidad con instalaciones anteriores.
- Actualiza la descripción a: “Gestión de muestras de tejido de los productos de la tienda y cupones descuento”.
- Mantiene el autor como **Naranja Creativos** y añade la web oficial **www.naranjacreativos.es** mediante `author_uri` y descripción adicional.
- Sincroniza la versión del módulo, esquema, `config.xml`, documentación y migración a 2.15.18.

## 2.15.17 - RC3 visibilidad de botones y simplificación del catálogo

- Recupera en la pestaña Diseño los interruptores para mostrar u ocultar el botón de muestras en la ficha de producto y en categorías/listados compatibles.
- Mantiene ambos controles independientes y conserva los valores ya configurados en instalaciones existentes.
- Elimina de la página de muestras el filtro por fabricante, incluido el selector "Todos los fabricantes".
- Las peticiones y la paginación del catálogo dejan de transportar el parámetro `id_manufacturer`; las URLs antiguas con ese parámetro se ignoran.
- Retira del panel de textos la opción de personalizar "Todos los fabricantes", ya que deja de utilizarse en el frontal.
- Limpia al actualizar la configuración obsoleta `FABRICS_SAMPLES_FILTER_ALL_MANUFACTURERS`, evitando dejar datos sin uso de versiones anteriores.
- Actualiza la documentación de pruebas y rendimiento para reflejar que el filtro de fabricantes ya no existe.
- Corrige la cuadrícula de filtros en escritorio para ocupar cinco columnas tras retirar el selector de fabricante, evitando dejar un hueco visual.
- Sincroniza el encabezado de la documentación técnica con la versión 2.15.17.
- Repara la migración histórica vacía `upgrade-2.6.13.php` con una función idempotente, evitando que actualizaciones desde versiones 2.6.x puedan detenerse al recorrer ese archivo.

## 2.15.16 - RC1 protección PRG en ajustes de stock

- Corrige la repetición de ajustes manuales al refrescar la página del stock de muestras mediante el patrón POST → Redirect → GET.
- Aplica la misma protección a los ajustes masivos, evitando volver a ejecutar operaciones ya realizadas tras F5 o recarga del navegador.
- Conserva filtros, búsqueda, paginación y mensajes de resultado después de la redirección.
- No modifica la lógica validada de compra, compensación del stock nativo ni consumo del stock independiente de muestras.

## 2.15.15 - RC1 stock finalization

- Aplaza toda reconciliación inicial de stock hasta `actionValidateOrder`, cuando PrestaShop ya ha terminado la creación nativa del pedido.
- Evita compensaciones prematuras desde `actionObjectOrderDetailAddAfter` y desde los hooks de estado iniciales del checkout.
- Mantiene separados los dos movimientos: compensación del stock vendible del producto original y consumo del stock independiente de muestras.
- El stock independiente se contabiliza por `id_order_detail` aunque el histórico enriquecido todavía no exista.
- El ledger de movimientos deja de tener efectos secundarios ocultos sobre `sample_stock`.
- Los fallos de imagen, URL o histórico no bloquean la reconciliación crítica de stock.
- La actualización vuelve a registrar los hooks necesarios, incluido `actionValidateOrderBefore` cuando el núcleo lo ofrece.

## [2.15.13] - 2026-08-11

### Corregido
- El stock independiente de muestras se descuenta de forma idempotente por `id_order_detail` incluso si el histórico enriquecido de la muestra todavía no se ha persistido.
- El ledger reconoce los movimientos iniciales asociados al `order_detail` al reconciliar posteriormente el histórico, evitando dobles descuentos.
- Las líneas de muestra dejan de alterar el stock vendible del producto original: el módulo compensa de forma idempotente la reducción nativa que PrestaShop aplica a la línea personalizada y retira esa compensación cuando el núcleo reintegra el stock por cancelación, error o reinyección.
- Los movimientos `native_stock_compensation` quedan separados del saldo de stock independiente de muestras, evitando que la reconciliación de un sistema contamine el otro.
- El checkout E2E resuelve la URL de autenticación canónica y localizada, admite direcciones guardadas, revalida la dirección tras actualizaciones dinámicas y acepta la confirmación administrativa en español.
- Las transiciones E2E de cancelación y reembolso se persisten sin intentar enviar correo, de modo que la prueba ejercita el cambio de estado y los hooks del módulo sin depender del subsistema de correo.

### Calidad
- Añadidas regresiones unitarias e integración real para comprobar el aislamiento del stock nativo antes y después de cancelaciones/reembolsos.
- Actualizados los contratos estáticos del arnés para login localizado, checkout, confirmación de pedido y aislamiento de stock.
- Migración idempotente `upgrade-2.15.13.php` desde 2.15.12, sin cambios destructivos de esquema.

## [2.15.12] - 2026-08-10

- El acceso del cliente E2E acota correo, contraseña y envío a `form#login-form`, evitando el campo de boletín del pie de página.
- La autenticación solo se acepta tras abandonar `/login` y mostrar un enlace real de cierre de sesión.
- La búsqueda de medios de pago exige que la etapa de pago esté visible y activa antes de inspeccionar sus opciones.
- La creación del pedido se valida mediante la URL de confirmación y un único encabezado específico, sin selectores ambiguos.
- Añadidos contratos preventivos para el formulario de acceso, la sesión real, la etapa de pago activa y la confirmación inequívoca.
- Migración idempotente `upgrade-2.15.12.php` desde 2.15.11.

## [2.15.11] - 2026-08-10

- El checkout E2E selecciona Francia explícitamente antes de enviar la dirección, evitando contextos extranjeros sin transferencia bancaria.
- El fixture configura y verifica de forma idempotente las restricciones de país, moneda, grupo y transportista de `ps_wirepayment`.
- La ausencia de medios de pago falla de forma anticipada e incluye el texto visible del paso para facilitar el diagnóstico.
- El cliente registrado abre el formulario mediante la ruta moderna `/login`, compatible con PrestaShop 8 y 9.
- Añadidos contratos preventivos para el país, las restricciones de pago, el diagnóstico anticipado y la ruta de autenticación.
- Migración idempotente `upgrade-2.15.11.php` desde 2.15.10.

## [2.15.10] - 2026-08-10

- El E2E reconoce las clases de transición `-complete/-current` de PrestaShop 8 y `step--complete/step--current` de PrestaShop 9.
- El formulario de dirección selecciona una provincia habilitada cuando `id_state` es visible y obligatorio.
- Los diagnósticos ignoran campos inválidos ocultos dentro de etapas ya completadas.
- Añadidos contratos preventivos para las clases de checkout, la provincia obligatoria y la visibilidad de los campos inválidos.
- Migración idempotente `upgrade-2.15.10.php` desde 2.15.9.

## [2.15.9] - 2026-08-10

- El checkout E2E usa un nombre de invitado válido compuesto solo por letras y verifica explícitamente que información personal, dirección y transporte terminan antes de buscar el medio de pago.
- Los fallos de transición del checkout incluyen ahora los mensajes visibles de validación y los campos inválidos, evitando diagnósticos erróneos sobre el paso de pago.
- Los dos recorridos de carrito mixto esperan la respuesta AJAX completa al añadir el producto normal antes de navegar y cierran el modal nativo si aparece.
- Añadidos contratos preventivos para el nombre de invitado, las transiciones verificadas y la espera de carrito.
- Migración idempotente `upgrade-2.15.9.php` desde 2.15.8.

## [2.15.8] - 2026-08-09

### Corregido
- Las muestras de productos con combinaciones usan la combinación solicitada cuando es válida o la combinación predeterminada del producto, evitando líneas con atributo `0` que bloqueaban el carrito y el pago.
- La reparación de integridad y la migración de versión corrigen las líneas abiertas antiguas con atributo `0` en el registro del módulo, `cart_product` y `customization`.
- El E2E reconoce las líneas lógicas de los temas de PrestaShop 8 y 9 sin duplicar nodos y abre el pago mediante el enlace canónico generado por el tema.

### Calidad
- Añadidas pruebas unitarias del selector de combinaciones y contratos preventivos para la reparación de carritos, los selectores de tema y la navegación al checkout.
- Migración idempotente `upgrade-2.15.8.php` desde 2.15.7.

## [2.15.7] - 2026-08-09

### Corregido
- Sustituidas las 18 llamadas a `Tools::displayPrice()` por un formateador del módulo basado en la API de localización disponible tanto en PrestaShop 8.1/8.2 como en 9.1, evitando HTTP 500 en administración, cuenta del cliente, catálogo y correos de cupones.
- La prueba E2E del carrito cuenta únicamente las líneas lógicas `.cart-item` y deja de duplicarlas al incluir también el contenedor interno `.product-line-grid`.

### Calidad
- Añadidas pruebas unitarias del formateador para moneda de contexto, objeto, array e identificador, además de contratos preventivos contra la API eliminada y el selector E2E duplicado.
- Migración idempotente `upgrade-2.15.7.php` desde 2.15.6.

## [2.15.6] - 2026-08-09

### Corregido
- Los seis controladores administrativos registran explícitamente el autoloader del módulo antes de resolver el trait de seguridad, evitando el HTTP 500 cuando PrestaShop 9.1 carga un controlador directamente.
- Las pruebas de filtros esperan a que finalicen la petición AJAX, la actualización de URL y el estado de carga antes de iniciar otra navegación.
- Las pruebas de carrito cierran el modal nativo `#blockcart-modal` antes de la siguiente interacción.

### Calidad
- Añadidos contratos preventivos para la carga directa de controladores, la sincronización de filtros y el cierre compatible del modal nativo.
- Migración idempotente `upgrade-2.15.6.php` desde 2.15.5.

## [2.15.5] - 2026-08-08

### Estabilización
- Añadida una suite E2E con Playwright contra instalaciones reales de PrestaShop para abrir todas las pestañas administrativas, recorrer catálogo, filtros, paginación y carrito, y comprobar el diseño responsive en 320, 390, 768 y 1440 px.
- Automatizados los pedidos de muestras para invitado y cliente registrado, incluido el carrito mixto con producto normal.
- Automatizadas la reemisión única de un cupón usado, la selección y limpieza masiva de Auditoría, la reinicialización con copia cifrada y la restauración desde la interfaz.
- Añadidas comprobaciones internas posteriores para garantizar que el cupón original permanece intacto, existe un único reemplazo pendiente, las muestras restauradas son completas y las transiciones nativas de cancelación y reembolso conservan el histórico.

### CI y distribución
- La entrega de producción depende ahora de pruebas de navegador en PrestaShop 8.1/MySQL, 8.2/MariaDB y 9.1/MySQL, con capturas, vídeo, trazas e informe HTML cuando falla una prueba.
- Corregidos los nombres obsoletos 2.15.3 del trabajo de publicación; el artefacto, su SHA-256 y la procedencia corresponden a 2.15.5.
- Migración idempotente `upgrade-2.15.5.php` desde 2.15.4.

## [2.15.4] - 2026-08-07

### Corregido
- La copia previa a **Reinicializar el módulo** deja de consultar incondicionalmente la tabla inexistente `configuration_shop`; las tablas auxiliares de configuración se incluyen solo cuando existen realmente.
- La restauración omite de forma compatible las tablas auxiliares opcionales ausentes en la tienda de destino.
- Los campos JSON de Auditoría escapan correctamente apóstrofos, barras y texto no UTF-8; mensajes como `doesn't exist` ya no pueden romper el `INSERT`.
- Un fallo secundario al registrar una operación de diagnóstico deja un aviso y un registro técnico, pero ya no provoca una página HTTP 500.

### Calidad
- Añadidas regresiones para la detección de tablas opcionales, el JSON seguro de Auditoría y la tolerancia a fallos secundarios de registro.
- Migración idempotente `upgrade-2.15.4.php` desde 2.15.3.

## [2.15.3] - 2026-08-07

### Corregido
- La pestaña **Auditoría** vuelve a compilar correctamente en Smarty: el JavaScript del selector masivo deja de estar incrustado en `audit.tpl` y se carga desde un recurso administrativo independiente.
- El selector «Seleccionar todos» mantiene sincronizados los estados marcado e indeterminado al cambiar casillas individuales.

### Calidad
- Añadida una regresión estática que impide reintroducir JavaScript inline incompatible con Smarty en la plantilla de Auditoría.
- Migración idempotente `upgrade-2.15.3.php` desde 2.15.2.

## [2.15.2] - 2026-08-07

### Corregido
- La elegibilidad de un cupón usado se determina mediante los usos nativos de PrestaShop y deja de depender de un estado interno que podía quedar desactualizado.
- Eliminada la reactivación en el mismo código, que podía desincronizar los límites nativos `quantity` y `quantity_per_user`.

### Añadido
- Acción administrativa **Emitir nuevo cupón**, protegida por POST, token, permiso de edición, confirmación y bloqueo compartido.
- Nueva regla de carrito independiente por reemisión, con código único, un solo uso, nueva validez y copia de las condiciones del cupón original.
- Historial secuencial de reemisiones con cupón original, número, código reemplazante, estado, fecha, empleado y resultado del correo.
- Restricción única que impide más de un reemplazo pendiente por cupón original, incluso ante solicitudes simultáneas.
- Envío opcional al cliente mediante plantillas de correo en español e inglés.
- Los cupones de reemplazo aparecen también en **Mis cupones de muestras** y se atribuyen a la conversión del cupón original.

### Fiabilidad y privacidad
- Emisión transaccional con reversión y limpieza defensiva de la regla nativa si falla cualquier paso.
- Reemisiones incluidas en copias, restauración, reinicialización, desinstalación, diagnóstico, RGPD y limpieza de datos huérfanos.
- Migración idempotente `upgrade-2.15.2.php` desde 2.15.1.

## [2.15.1] - 2026-08-07

### Corregido
- El alto exacto de los botones ahora prevalece sobre el `min-height` de 42 px de la hoja pública y sobre los mínimos definidos por el tema.
- El relleno vertical deja de aumentar o desbordar el botón cuando se configura una altura exacta; el texto permanece centrado mediante flexbox.

### Mejorado
- El alto configurable admite valores entre 16 y 300 px; `0` mantiene el comportamiento automático.

## [2.15.0] - 2026-08-07

### Funcionalidad
- Reactivación individual de cupones usados desde el back office, conservando el histórico y concediendo exactamente un uso adicional.
- Ancho y alto exactos configurables por separado para cada perfil de botón; `0` mantiene el tamaño automático.

### Fiabilidad y seguridad
- Bloqueos compartidos y transacciones para altas, bajas y cambios de cantidad de muestras en el carrito.
- Bloqueo por cliente durante la validación final del pago para evitar superar límites históricos con pedidos simultáneos.
- Exclusión mutua para reparaciones, reinicializaciones y restauraciones.
- Borrado RGPD completamente transaccional y coincidencia estructurada de identificadores dentro de JSON.
- Limitador AJAX compartido en base de datos con limpieza automática de ventanas caducadas.
- Instalación con reversión y desinstalación que se detiene si no puede verificar toda la limpieza.
- Imágenes históricas privadas, persistentes fuera de la carpeta del módulo y servidas mediante autorización temporal.
- Restauración de imágenes preparada antes del `COMMIT` y publicada mediante renombrado atómico.

### Calidad
- Migración idempotente `upgrade-2.15.0.php`, nuevas pruebas de reactivación y contratos estáticos de concurrencia, RGPD, dimensiones e imágenes privadas.
- La entrega de producción depende de toda la matriz de integración y genera checksum y procedencia verificable en CI.

## [2.14.0] - 2026-08-07

### Seguridad
- Permisos `view`, `edit` y `delete` comprobados explícitamente en todas las operaciones administrativas, además del token del controlador.
- Todas las mutaciones administrativas y públicas exigen POST; el borrado de excepciones deja de aceptar enlaces GET.
- Saneado con lista blanca del HTML configurable, eliminación de protocolos y atributos peligrosos y migración de los valores existentes.
- CSS personalizado limitado al catálogo, sin cargas remotas, expresiones, reglas globales ni directivas activas.
- Las imágenes configurables se decodifican y vuelven a generar, con límite de tamaño, dimensiones y megapíxeles.
- El controlador AJAX limita frecuencia, cantidad y tamaño de lotes, valida la pertenencia de la línea al carrito y no expone excepciones internas.
- Todas las exportaciones CSV neutralizan fórmulas para Excel y LibreOffice.

### Privacidad
- Integración con `actionExportGDPRData` y `actionDeleteGDPRCustomer` para exportar, eliminar o anonimizar datos del cliente.
- Retención configurable de auditoría, eventos y reinicios de límites, con limpieza diaria idempotente.
- Retención configurable de copias por número y antigüedad.

### Copias y recuperación
- Nuevo formato `.fsb` cifrado y autenticado por bloques con AES-256-GCM y clave derivada de la tienda.
- Verificación completa por descifrado, manifiesto, resumen, tamaño, SHA-256 y hashes de archivos antes de publicar cada copia.
- Restauración controlada desde Diagnóstico con permiso de borrado, confirmación reforzada, lista blanca de tablas y copia cifrada del estado previo.
- Prueba automática de ida y vuelta: reinicialización, descifrado, restauración de tablas/filas nativas/imágenes y nueva limpieza.

### Calidad y distribución
- Compatibilidad declarada limitada a PrestaShop 8.1–9.1, que es la línea estable cubierta por la matriz.
- Comprobaciones de paquete en PHP 8.1, 8.2, 8.3, 8.4 y 8.5; ciclos reales añadidos para PrestaShop 9.1 con PHP 8.4 y 8.5.
- Constructor de ZIP de producción sin pruebas, CI ni utilidades de desarrollo, con manifiesto SHA-256 interno.

## [2.13.0] - 2026-08-07

### Añadido
- Copia de seguridad obligatoria antes de cada reinicialización, comprimida, versionada y descargable desde Diagnóstico.
- La copia incluye tablas y configuración del módulo, registros nativos asociados a carritos y reglas de descuento, e imágenes personalizadas e históricas.
- Borrado individual y masivo de registros en Auditoría, limitado por permisos y contexto de tienda.
- Índices específicos para catálogo, popularidad y navegación de auditoría en instalaciones grandes.
- Pruebas unitarias de paginación compacta y comprobaciones de integración de índices y copia previa al reset.

### Optimizado
- Eliminadas las consultas por tarjeta para obtener configuración e imagen de producto en el catálogo público.
- La popularidad se agrega solo para la tienda consultada y aprovecha un índice por tienda/producto.
- Los filtros cargan únicamente categorías y fabricantes presentes entre las muestras disponibles.
- Paginación compacta en catálogo, productos del back office y auditoría.

### CI
- Las comprobaciones empaquetadas se ejecutan en PHP 8.1, 8.2 y 8.3.
- La matriz real mantiene PrestaShop 8.1, 8.2 y 9.1 contra MariaDB y MySQL y verifica actualización desde 2.12.2.

## [2.12.2] - 2026-08-06

### Corregido
- La reconstrucción histórica elimina cualquier índice único heredado no previsto en `fabricssamples_order` antes de enlazar líneas antiguas.
- Añadido un segundo método de recuperación directa desde `order_detail` cuando no es posible reconstruir imágenes o datos auxiliares.
- Los fallos históricos ya no se ocultan: Diagnóstico muestra pedido, línea, producto, nombre y clasificación de cada incidencia pendiente.

### Añadido
- Nueva tabla `fabricssamples_history_exclusion` para descartar falsos positivos sin modificar pedidos, facturas ni líneas nativas.
- Botón «Descartar pendientes» con confirmación explícita y botón «Volver a revisar descartados».
- El contador distingue entre líneas pendientes y líneas descartadas manualmente.

## [2.12.1] - 2026-08-06

### Corregido
- Reparación inicial de históricos heredados con `id_order_detail = 0`.
- Eliminación del índice obsoleto `uniq_order_customization` y restauración del índice de búsqueda normal.
- Verificación final del número de líneas pendientes después de reconstruir históricos.

## [2.12.0] - 2026-08-06

### Añadido
- Límites históricos configurables por estado de pedido.
- Excepciones por cliente y grupo, reinicios e historial de bloqueos.
- Administración avanzada del stock, ajustes masivos, exportaciones CSV y auditoría administrativa.

## [2.11.2] - 2026-08-06

### Corregido
- El botón «Quitar muestra» actualiza inmediatamente la tarjeta, los contadores del carrito y el bloque de carrito sin recargar manualmente la página.
- Se ocultan los mensajes de confirmación anteriores y se reactiva el botón para volver a añadir la muestra.

### Cambiado
- La página pública de muestras usa exclusivamente una cuadrícula adaptable; se elimina el modo lista y su selector.
- Los selectores de productos por fila para ordenador, tableta y móvil se trasladan a la pestaña «Página de muestras» y muestran el rango de pantalla aplicado.
- Se eliminan durante la actualización las configuraciones antiguas del selector cuadrícula/lista.

## 2.11.0 - 2026-08-06
- Añadida una suite de integración reproducible con instalaciones reales de PrestaShop mediante Docker Compose.
- Matriz de CI para PrestaShop 8.1/PHP 8.1, PrestaShop 8.2/PHP 8.2 y PrestaShop 9.1/PHP 8.3 sobre MariaDB y MySQL.
- Las pruebas ejecutan instalación, actualización desde un estado 2.10.0, petición HTTP al controlador público, reinicialización destructiva, desinstalación y un segundo ciclo completo.
- Añadidas comprobaciones reales de tablas, migraciones, hooks, pestañas, configuración y eliminación de una regla de carrito nativa.
- Nuevo botón `Reinicializar el módulo` en Diagnóstico, situado junto a `Reparar todo`.
- La reinicialización muestra una confirmación irreversible y borra datos, configuración, muestras activadas, carritos, históricos, stock, movimientos, cupones, conversiones e imágenes antes de recrear el módulo con valores predeterminados.
- La reinicialización mantiene el módulo instalado y repara después pestañas, hooks y rutas.
- Corregida la limpieza de `cart_rule_combination`, que utiliza `id_cart_rule_1` e `id_cart_rule_2`.
- Las consultas de limpieza nativa ahora propagan los errores SQL para impedir reinicializaciones parciales silenciosas.

## 2.10.0 - 2026-08-06
- Eliminadas configuraciones obsoletas que ya no tenían efecto: resumen del carrito, AJAX antiguo, insignia, fabricante/formato en tarjetas, `KEEP_DATA` y opciones heredadas.
- Retirado código frontal, CSS, JavaScript y asignaciones Smarty asociados a funciones eliminadas.
- Simplificada la consulta del catálogo para no cargar fabricante ni tamaño cuando no se muestran en las tarjetas.
- La desinstalación mantiene el comportamiento solicitado: eliminación completa de datos y configuraciones del módulo.
- Añadida tabla de control de migraciones con versión, checksum, estado, intentos, fechas, error y detalles.
- Nueva ejecución de migraciones idempotente y recuperable: una migración fallida puede repetirse de forma segura.
- Verificación automática del esquema antes de marcar una migración como correcta.
- Registro de la última migración en la pestaña Diagnóstico.
- Limpieza automática de configuraciones y hooks obsoletos durante la actualización.
- Mejorado el ejecutor SQL para informar de la consulta fallida y respetar puntos y comas dentro de cadenas.
- Añadidas pruebas estáticas y unitarias para impedir que se reinstalen configuraciones obsoletas y validar el sistema de migraciones.

## 2.9.0 - 2026-08-06
- Nueva pestaña de Diagnóstico técnico integrada en el back office.
- Comprobaciones de servidor, PHP, PrestaShop, MariaDB/MySQL, correo y permisos.
- Validación del esquema SQL sin depender de information_schema.
- Comprobación de tablas, columnas, índices, hooks, pestañas y rutas.
- Detección de carritos incoherentes, históricos ausentes, cupones sin regla, stock negativo y registros huérfanos.
- Herramientas para reparar esquema, hooks, pestañas, rutas, carritos, históricos, stock y cupones.
- Herramienta de limpieza segura de registros huérfanos.
- Acción Reparar todo con registro detallado en los logs de PrestaShop.

## 2.8.2 - 2026-08-06
- Corregida la instalación en MariaDB cuando una búsqueda de pestañas administrativas terminaba en `LIMIT 1 LIMIT 1`.
- La instalación elimina pestañas antiguas mediante consultas directas y las recrea sin `Tab::getIdFromClassName()`.
- Las comprobaciones de columnas e índices de la migración 2.8.0 usan `executeS()` y no añaden límites implícitos.
- Eliminado el `LIMIT 1` de la consulta bloqueante de stock; la clave única producto/tienda garantiza una única fila.
- Añadido diagnóstico por etapa de instalación para identificar con precisión cualquier fallo futuro.

## 2.8.1 - 2026-08-06
- Corregida la desinstalación en MariaDB cuando PrestaShop añadía un segundo `LIMIT 1` durante operaciones internas.
- Sustituida la limpieza basada en `ObjectModel` por consultas directas y compatibles, sin límites implícitos.
- La desinstalación elimina líneas nativas de muestras, reglas de carrito del módulo, pestañas, tablas, imágenes y configuraciones sin bloquear la retirada del módulo.
- Añadida limpieza tolerante a tablas ya inexistentes o instalaciones parcialmente actualizadas.

## 2.8.0 - 2026-08-06
- Añadido ciclo completo e idempotente del stock independiente de muestras.
- Nueva tabla de movimientos de stock con referencia única, cantidades anterior/posterior, pedido y detalle asociados.
- Descuento atómico del stock con bloqueo de fila para evitar ventas simultáneas por encima de existencias.
- Restauración automática de stock en cancelaciones, devoluciones, reembolsos, abonos y eliminación de pedidos.
- Reconsumo automático cuando un pedido cancelado vuelve a un estado válido.
- Migración segura del stock ya contabilizado por versiones anteriores sin descontarlo de nuevo.
- Añadido umbral configurable de aviso de stock bajo.
- Nueva máquina de estados centralizada para cupones: disponible, usado, caducado, inactivo, cancelado y eliminado permanentemente.
- Los cupones no usados se desactivan al cancelar o reembolsar completamente el pedido de muestras.
- Los reembolsos parciales recalculan el importe del cupón según las muestras que siguen siendo válidas.
- Los cupones cancelados pueden reactivarse si el pedido vuelve a un estado válido, sin duplicar reglas de carrito.
- Se respeta el importe manual editado desde el back office y el borrado permanente.
- Añadidos hooks de cancelación/reembolso y creación de abonos para PrestaShop 8 y 9.
- Añadidas pruebas unitarias para reconciliación de stock y transiciones de cupones.

## 2.7.1 - 2026-08-06
- Corregido el error SQL `LIMIT 1 LIMIT 1` al añadir una muestra al carrito.
- Eliminados los `LIMIT 1` explícitos en consultas enviadas a `Db::getRow()`, ya que PrestaShop añade ese límite internamente.
- Corregidas las consultas de integridad del carrito y las búsquedas históricas de pedidos para MariaDB/MySQL.
- Añadida una comprobación estática para impedir que vuelva a introducirse un límite duplicado en llamadas a `getRow()`.

## 2.7.0 - 2026-08-06
- Separada la integración nativa del carrito en adaptadores específicos para PrestaShop 8 y PrestaShop 9.
- La creación de personalizaciones parte siempre de cantidad cero y se normaliza después de `Cart::updateQty()`, evitando duplicidades en PrestaShop 8 y cantidades vacías en PrestaShop 9.
- Añadido un servicio de integridad que verifica y repara la igualdad entre `fabricssamples_cart`, `cart_product` y `customization`.
- `cart_product` pasa a ser la fuente nativa de verdad cuando una cantidad se modifica desde el tema o desde PrestaShop.
- Reparación automática al guardar el carrito y al inicializar el front office.
- Validación estricta antes de crear el pedido cuando el core dispone de `actionValidateOrderBefore`.
- Toda manipulación de tablas nativas del carrito se centraliza en los adaptadores; el repositorio del módulo solo gestiona tablas propias.
- Centralizado el precio de muestra en `CartSamplePriceProvider`, con selección fiscal segura y neutralización de reducciones externas.
- Añadidas pruebas unitarias para adaptadores, invariantes y precios, además de verificaciones estáticas de arquitectura.

## 2.6.14 - 2026-08-06
- Añadidos los modos de cupón `Muestra más económica` y `Muestra más cara`.
- Los nuevos modos descuentan una unidad de la muestra seleccionada por precio, aunque el pedido incluya varias muestras o cantidades.
- El cálculo utiliza precios unitarios con impuestos del histórico del pedido y queda limitado al total pagado por muestras.
- Ampliadas las pruebas unitarias del cálculo de cupones.

## 2.6.13 - 2026-08-06
- Eliminadas las columnas `Estado` y `Conversión` de la página `Mis muestras` del cliente.
- El botón `Reparar histórico y generar cupones` permanece ahora en `Productos y configuración`, sin ocultar el formulario inferior.
- Añadido acceso de retorno a `Productos y configuración` desde la pestaña independiente de cupones.

## 2.6.12 - 2026-08-06
- Corregida la visualización del estado del cupón en la cuenta del cliente.
- Unificada la resolución de estados entre front office y back office mediante un presentador compartido.
- Los estados ahora muestran exactamente los mismos valores: Disponible, Usado, Caducado o Inactivo.
- Añadidos estilos propios para impedir que el tema deje el texto del estado invisible.

## 2.6.11 - 2026-08-06
- Corregida la generación del cupón al cambiar manualmente un pedido por transferencia a `Pago aceptado`.
- Añadidos puntos de entrada redundantes en `actionOrderStatusUpdate`, `actionOrderStatusPostUpdate` y `actionObjectOrderHistoryAddAfter`.
- La generación es idempotente: los tres eventos pueden ejecutarse sin crear cupones duplicados.
- Se reconoce como pagado tanto un estado con la marca `paid` como el estado configurado en `PS_OS_PAYMENT`.
- Añadido registro de diagnóstico cuando un pedido pagado no contiene muestras o no puede generar el cupón.

## 2.6.10 - 2026-08-05
- Eliminada la generación automática de cupones al refrescar `Mis muestras` y `Mis cupones`.
- El borrado permanente conserva una marca interna oculta y una supresión por pedido para impedir cualquier regeneración futura.
- Añadida creación automática de la tabla de supresiones si faltaba en una actualización anterior.
- Los cupones eliminados quedan ocultos en el perfil del cliente y en los listados del back office.
- La edición de cupones continúa disponible desde `Muestras de tejidos > Cupones de muestras`.

## 2.6.9 - 2026-08-05
- Los cupones eliminados manualmente quedan suprimidos permanentemente por pedido y no se regeneran al entrar el cliente ni al ejecutar reparaciones.
- Añadida edición de código, importe, compra mínima, fechas y estado activo del cupón desde el back office.
- Añadida limpieza automática del histórico, conversiones y cupones cuando el pedido nativo ya no existe.
- Añadido hook de limpieza al eliminar pedidos.

## 2.6.8 - 2026-08-05
- Corregido el error de compilación Smarty en `coupon_list.tpl`.
- El botón de borrado individual envía directamente el identificador del cupón, sin JavaScript incrustado incompatible.
- Protegido el script de selección múltiple con `{literal}` para evitar que Smarty interprete sus llaves.
- Se mantiene el borrado individual y masivo de cupones y reglas de carrito.

## 2.6.7 - 2026-08-05
- Gestión visible de cupones en el back office, tanto en Productos y configuración como en la pestaña Cupones de muestras.
- Añadidos enlaces al cliente, pedido y regla de carrito.
- Añadido borrado individual y masivo de cupones.
- El borrado elimina la regla de carrito nativa y el registro interno, manteniendo intactos pedidos e histórico de muestras.
- Compatibilidad con contexto multitienda y reparación de la pestaña administrativa.

## 2.6.6 - 2026-08-05
- `Mis muestras` utiliza las líneas nativas del pedido como fuente de respaldo si falta el histórico del módulo.
- Los cupones pueden generarse directamente desde `order_detail`, sin depender de datos temporales del carrito ni de una inserción histórica previa.
- La detección combina el nombre `Muestra - ...` con la relación del carrito y la coincidencia de producto/precio.
- Las imágenes de la cuenta del cliente se recuperan del producto original cuando no existe una copia histórica.
- Los pedidos del cliente se incluyen aunque la tabla histórica esté vacía o incompleta.

## 2.6.5 - 2026-08-05
- Corregida la reconstrucción de `Mis muestras` directamente desde las líneas nativas `order_detail` cuyo nombre comienza por `Muestra`.
- Eliminada la restricción única por `id_customization`, que impedía guardar varias muestras cuando PrestaShop conservaba el valor `0` en pedidos confirmados.
- La copia de imágenes y la generación de enlaces ya no pueden abortar la reconstrucción completa del histórico.
- Sincronizados `id_customer` e `id_shop` históricos con la tabla nativa de pedidos.
- La actualización ejecuta una reparación automática y vuelve a intentar la generación de cupones.

## 2.6.4 - 2026-08-05
- El histórico de muestras se guarda durante `actionObjectOrderDetailAddAfter`, antes de que PrestaShop o el módulo de pago limpien el carrito.
- `Mis muestras` recupera automáticamente pedidos cuyas líneas ya se llaman `Muestra - ...`, incluso si los registros temporales del carrito ya no existen.
- La consulta del cliente se basa en el propietario nativo del pedido, evitando históricos invisibles por un `id_customer` antiguo o incompleto.
- Se mantiene la sincronización posterior como respaldo y se evita duplicar stock o registros históricos.

## 2.6.3 - 2026-08-05
- Identificación nativa de muestras en el momento exacto de crear cada `order_detail`.
- Las líneas de pedido se guardan como `Muestra - Nombre del tejido` y conservan su `id_customization`.
- Reparación automática al abrir un pedido, cambiar su estado o generar factura/albarán.
- El PDF fuerza la sincronización histórica antes de renderizar la sección de muestras.

## 2.6.2 - 2026-08-05
- Corregida la generación de cupones: se activa por defecto y se crea al registrar el pedido, incluido el pago por transferencia pendiente.
- Corregida la asociación multitienda de las reglas de carrito y la recreación de cupones dañados o incompletos.
- Los cupones aparecen dentro de `Mis muestras`, en `Mis cupones de muestras` y en una nueva pestaña propia del back office.
- Añadido panel `Cupones de muestras` con cliente, correo, pedido, código, importe, caducidad y estado de canje.
- Corregido el nombre de las líneas históricas y nativas del pedido para que figure `Muestra - Nombre del tejido`.
- La reparación histórica actualiza también pedidos ya creados y vuelve a generar sus cupones pendientes.

## 2.6.1 - 2026-08-05
- Corregida la sincronización del histórico cuando `id_customization` no se copia al detalle del pedido.
- Reparación automática y bajo demanda de pedidos anteriores con muestras.
- Añadida página independiente `Mis cupones de muestras` en la cuenta del cliente.
- Añadido listado permanente de cupones, incluso cuando no hay códigos disponibles.
- Añadido panel de cupones pendientes, usados y caducados con datos de cliente en el back office.
- Añadida acción para reparar históricos y generar cupones pendientes.

## 2.6.0 - 2026-08-05
- Histórico inmutable ampliado por muestra: imagen copiada, moneda, idioma, impuesto, totales, URL original y snapshot JSON de configuración.
- Información histórica mostrada en back office, cuenta del cliente, facturas y albaranes PDF.
- Sistema opcional de cupones nativos de PrestaShop, de un solo uso y asignados al cliente.
- Cupones configurables por importe completo, porcentaje del coste de las muestras o importe fijo, con compra mínima, caducidad y restricción a los tejidos muestreados.
- Generación configurable al crear el pedido o cuando alcance un estado pagado.
- Correo opcional del cupón con plantillas ES/EN y protección frente a errores del sistema de correo.
- Registro de conversiones cuando un cliente compra posteriormente un tejido que había muestreado.
- Limpieza de cupones y copias de imágenes durante la desinstalación completa.

## 2.5.3 - 2026-08-05
- Eliminadas del back office las opciones `Mostrar fabricante` y `Mostrar formato / tamaño`, porque no tenían efecto real en la parte frontal.
- Se mantiene intacto el comportamiento público del módulo.

## 2.5.2 - 2026-08-05
- Añadido icono cuadrado del módulo con tijeras y tela en color naranja (`logo.png`).
- Eliminado el bloque de resumen `X muestras en el carrito` de la página pública de muestras.

## 2.5.1 - 2026-08-05

- La explicación de cada tarjeta se edita ahora individualmente por muestra.
- El campo general "Explicación dentro de la tarjeta" se ha eliminado de la configuración global.
- Las explicaciones individuales admiten HTML, imágenes y contenido por idioma.
- Se añade una tabla multidioma propia y una migración de los textos existentes.

## 2.5.0 - 2026-08-05

- Added dependency-free unit and integrity tests plus a validation script.
- Added advanced cart and historical customer limits with configurable period, guest handling, exempt customer groups and multilingual messages.
- Added server-side enforcement for all limit rules and independent stock.
- Added optional AJAX filtering with a non-JavaScript fallback.
- Added cart summary, in-cart quantities, remove-from-page action, result count, grid/list view, per-page selection and newest/popular sorting.
- Added compact accessible catalog controls and test documentation.


## 2.4.0 - 2026-08-05

- Añadida personalización independiente de todos los botones del módulo.
- El texto, fondo, color de letra, borde, radio, tamaño, grosor, relleno, márgenes y ancho se pueden configurar por botón.
- Perfiles separados para ficha de producto, categorías/listados, añadir muestra, ver tejido y filtros.
- Estilos aplicados en línea con prioridad para mejorar la compatibilidad con temas personalizados.
- CSS frontal consolidado y eliminado código de estilos acumulado de versiones anteriores.
