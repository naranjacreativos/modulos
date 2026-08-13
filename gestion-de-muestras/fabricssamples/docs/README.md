# Gestión de muestras 2.15.18

Módulo de Naranja Creativos para gestionar muestras de tejidos en PrestaShop 8.1–9.1 y PHP 8.1–8.5 según la combinación admitida por cada versión de PrestaShop.

## Cuadrícula adaptable de muestras

La página pública utiliza una única vista en cuadrícula. Desde **Página de muestras** se configura de forma independiente el número de productos por fila en ordenador, tableta y móvil. El botón **Quitar muestra** actualiza la tarjeta y el carrito mediante AJAX sin recargar la página.

## Arquitectura del carrito desde 2.7.0

La integración con el carrito nativo está separada por versión:

- `PrestaShop8CartAdapter`
- `PrestaShop9CartAdapter`

`SampleCartService` no modifica directamente `cart_product`, `customization` ni `customized_data`. Todas las operaciones nativas pasan por el adaptador seleccionado por `NativeCartAdapterFactory`.

Cada línea de muestra debe cumplir permanentemente esta igualdad:

```text
fabricssamples_cart.quantity
=
cart_product.quantity
=
customization.quantity
```

En productos con combinaciones, la línea conserva una combinación solicitada válida o utiliza la combinación predeterminada. La reparación de integridad mantiene también sincronizado `id_product_attribute` entre las tres tablas y corrige carritos abiertos antiguos con atributo `0`.

`CartIntegrityService` verifica y repara la igualdad al guardar el carrito y al inicializar el front office. En los núcleos que incluyen `actionValidateOrderBefore`, también se ejecuta una validación estricta justo antes de crear el pedido. Si una línea no puede repararse, el pago se bloquea y el cliente vuelve al carrito.

La cantidad de `cart_product` es la fuente nativa de verdad cuando el tema o PrestaShop modifican el carrito. Las operaciones internas del módulo suspenden temporalmente la reparación automática para evitar que `actionCartSave` intervenga en estados intermedios.

## Aislamiento del stock nativo desde 2.15.13

PrestaShop representa cada muestra mediante una línea personalizada del producto original y, por ello, puede descontar stock nativo del tejido al crear el pedido. `StockLifecycleService` mantiene una compensación separada en `fabricssamples_stock_movement` con el tipo `native_stock_compensation`. Esa compensación no forma parte del saldo de stock independiente de muestras y se reconcilia de forma idempotente según las cantidades reintegradas por PrestaShop y los estados nativos de cancelación/error.

La prueba de integración verifica el saldo de compensación antes y después de las transiciones de cancelación y reembolso, además de comprobar que cada movimiento conserva la igualdad `quantity_after - quantity_before = quantity_delta`.

## Precios

`CartSamplePriceProvider` centraliza el importe entregado a `actionProductPriceCalculation`:

- precio sin impuestos cuando `use_tax` es falso;
- precio con impuestos cuando `use_tax` es verdadero;
- eliminación de reducciones externas aplicadas sobre la línea de muestra;
- protección frente a importes negativos o no finitos.

## Histórico y cupones

Cada muestra de pedido se copia a `fabricssamples_order` con nombre, referencia, tamaño, precios, impuesto, moneda, idioma, URL, copia local de la imagen y un snapshot JSON. El módulo puede generar un cupón nativo de PrestaShop tras crear o pagar el pedido.

Consulte `TESTING.md` antes de desplegar en producción.

## Rendimiento para catálogos grandes desde 2.13.0

El catálogo obtiene la imagen de portada y toda la configuración de la muestra en la consulta paginada, sin consultas adicionales por tarjeta. La popularidad se calcula únicamente para la tienda actual y dispone de un índice específico. El filtro de categorías solo incluye categorías que contienen muestras activas; el catálogo no ofrece filtro por fabricante y la paginación muestra una ventana compacta.

La administración de productos y la auditoría utilizan la misma paginación compacta para evitar generar cientos o miles de enlaces.


## Esquema y migraciones

Desde la versión 2.10.0 las migraciones se registran en `fabricssamples_schema_migration`.
Cada actualización es idempotente, conserva el número de intentos y solo actualiza
`FABRICS_SAMPLES_SCHEMA_VERSION` después de verificar tablas, columnas e índices.
La pestaña Diagnóstico muestra el último resultado y permite reparar el esquema.


## Página de muestras desde 2.11.2

Los textos auxiliares del catálogo incluyen valores de ejemplo por idioma. La página pública utiliza exclusivamente una cuadrícula adaptable. En la pestaña **Página de muestras** se seleccionan los productos por fila para ordenador, tableta y móvil. La eliminación de una muestra actualiza la tarjeta y el carrito en el momento, sin recarga manual.

## Reinicialización completa

Desde Diagnóstico, el botón **Reinicializar el módulo** elimina definitivamente todos los datos gestionados por el módulo en todas las tiendas y lo recrea con la configuración predeterminada. La acción mantiene el módulo instalado, vuelve a comprobar pestañas, hooks y rutas, y exige una confirmación explícita en el navegador.

Antes de eliminar cualquier dato, la versión 2.14.0 crea obligatoriamente una copia cifrada y autenticada. Si la creación o su verificación completa falla, la reinicialización se cancela sin tocar datos. El archivo incluye tablas y configuración del módulo, filas nativas vinculadas a muestras y cupones, e imágenes. La retención se configura en **Privacidad y seguridad** y las copias se descargan o restauran desde Diagnóstico. El formato y el procedimiento técnico se describen en `RESET_BACKUPS.md`.

Después se eliminan también las personalizaciones activas de muestras, las reglas de carrito generadas, las imágenes históricas y la imagen personalizada del catálogo. No debe utilizarse como una reparación ordinaria; para eso permanece disponible **Reparar todo**.

## Integración automatizada

`tests/integration/run.sh` inicia PrestaShop y una base de datos reales con Docker Compose. `tests/integration/run-e2e.sh` añade Chromium para recorrer la interfaz, los carritos, las compras, los cupones, Auditoría y recuperación. La matriz de GitHub Actions cubre PrestaShop 8.1, 8.2 y 9.1 con PHP 8.1–8.5 en combinaciones compatibles, MariaDB y MySQL. Consulte `tests/integration/README.md` para ejecutar la suite localmente.

## Seguridad y privacidad desde 2.14.0

Las operaciones administrativas aplican permisos por acción, token y método HTTP. El HTML usa una lista blanca, el CSS queda limitado al catálogo, las imágenes se vuelven a codificar y los CSV neutralizan fórmulas. AJAX valida carrito, limita lotes y frecuencia y devuelve referencias públicas sin revelar excepciones internas.

El módulo responde a los hooks oficiales de exportación y borrado RGPD. Los datos operativos del cliente se eliminan o anonimizan; las relaciones necesarias para pedidos se conservan sin duplicar identidad personal. Auditoría y límites aplican una política de retención configurable.

## Reparación histórica desde 2.12.2

La herramienta **Reconstruir históricos** vincula las líneas nativas de pedido con registros antiguos que todavía tenían `id_order_detail = 0`. Antes de reparar elimina los índices únicos heredados que no forman parte del esquema actual. Si la reconstrucción completa no es posible, crea un histórico mínimo y seguro directamente desde la línea nativa del pedido.

Diagnóstico muestra las líneas todavía pendientes con su pedido, `id_order_detail`, producto, nombre y clasificación. Las líneas que realmente no pertenezcan al módulo pueden marcarse mediante **Descartar pendientes**. Esta acción solo elimina el aviso y registra una exclusión; no borra ni modifica pedidos, facturas o líneas de pedido. **Volver a revisar descartados** elimina esas exclusiones para volver a intentar la reparación.

## Operaciones y control desde 2.12.0

El menú **Muestras de tejidos** incorpora tres áreas nuevas:

- **Stock de muestras**: existencias actuales, reservas en carritos, disponibilidad tras reservas, consumo neto, filtros, ajustes individuales/masivos y exportaciones CSV.
- **Límites y excepciones**: estados de pedido contabilizados, consumo acumulado por cliente, excepciones por cliente o grupo, reinicios manuales e historial de bloqueos.
- **Auditoría**: registro de cambios administrativos con empleado, fecha, valores anterior/nuevo, nota e IP.

Los estados que contabilizan se seleccionan en **Productos y configuración → Límites**. Una selección vacía explícita no cuenta ningún pedido. Las reglas de cliente tienen prioridad sobre las reglas de grupo y los valores `0` de una regla personalizada conservan el valor general.

Todo ajuste manual de stock exige un motivo, se ejecuta bajo transacción y no puede dejar existencias negativas. Cada operación crea un movimiento de stock y una entrada de auditoría.

Desde 2.13.0, Auditoría permite seleccionar registros mediante casillas y borrarlos en bloque, además de borrar una fila concreta. La operación exige permiso de borrado del empleado y respeta el contexto de tienda activo.

## Fiabilidad de producción desde 2.15.0

Las mutaciones de carrito usan un bloqueo compartido por carrito y una transacción para mantener sincronizados PrestaShop y la tabla del módulo. Antes del pago se adquiere además un bloqueo por cliente/tienda, se vuelven a validar stock y límites históricos y se mantiene la exclusión hasta que se crea el pedido.

Las reparaciones, reinicializaciones y restauraciones no pueden solaparse. El borrado RGPD anonimiza las reglas nativas dentro de la misma transacción y localiza al cliente analizando JSON, sin coincidencias parciales. El limitador AJAX se almacena en base de datos, funciona con varios servidores y elimina automáticamente ventanas caducadas.

Las imágenes históricas se guardan en `var/fabricssamples/orders`, con permisos privados y nombres aleatorios, y se sirven mediante un controlador autorizado. La actualización migra las copias antiguas que todavía estén dentro del módulo.

Los cupones usados muestran la acción **Emitir nuevo cupón** en el back office. El cupón original y todos los pedidos que lo utilizaron permanecen intactos; el módulo crea una regla independiente con código nuevo, un solo uso y una nueva fecha de validez. La elegibilidad se basa en los usos reales registrados por PrestaShop, no en el estado interno almacenado.

Solo puede existir una reemisión pendiente por cupón original. Cuando el reemplazo se utiliza, caduca, se desactiva o desaparece, un empleado puede autorizar otra reemisión mediante una nueva acción. El historial muestra el código original, número de reemisión, código reemplazante, estado, fecha, empleado y resultado del correo. La casilla **Enviar por correo** permite comunicar el nuevo código al cliente; un fallo de correo no elimina un cupón creado correctamente y se muestra como advertencia.

En **Diseño de botones**, cada botón permite indicar ancho y alto exactos en píxeles. El valor `0` conserva el cálculo automático. Desde 2.15.1, el alto exacto representa la altura exterior final del botón, anula el mínimo impuesto por el tema y centra el texto sin añadir relleno vertical; admite valores de 16 a 300 px. Así puede medirse el botón nativo del tema y aplicar exactamente esa altura al botón de muestra.
