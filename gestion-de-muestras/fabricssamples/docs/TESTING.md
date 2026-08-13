# Testing and stabilization

Run the packaged checks with:

```sh
./bin/validate-module.sh
```

The packaged checks cover PHP syntax, JavaScript syntax, required files, hook-method consistency, SQL contracts, version consistency, migration definitions and the integration-harness contract.

## Automated real-environment matrix

Run the Docker lifecycle locally with:

```sh
./tests/integration/run.sh
```

Los ejecutores generan contraseñas temporales nuevas mediante OpenSSL para cada entorno y no guardan credenciales en el repositorio. No utilice credenciales de producción para estas pruebas.

GitHub Actions executes the lifecycle on PrestaShop 8.1/PHP 8.1, PrestaShop 8.2/PHP 8.1 and PrestaShop 9.1/PHP 8.3, 8.4 and 8.5 against MariaDB and MySQL. These PHP versions match the currently published official Docker images. The lifecycle covers module installation, upgrade, HTTP boot, encrypted reset backup, round-trip restoration, uninstall and a second clean install/uninstall cycle.

El entorno espera por separado a que la consola y Apache estén disponibles. Cada trabajo instala sobre una base de datos nueva, sin solicitar al contenedor que elimine el esquema. MariaDB usa su `healthcheck.sh` oficial y un script previo a la instalación conserva `var/cache`, la asigna a `www-data` y concede permisos de escritura al propietario para PrestaShop 9. El script tolera únicamente entradas transitorias que Symfony reemplaza durante el recorrido y verifica después el propietario y el modo del directorio estable de caché. Todas las órdenes PHP del arnés se ejecutan también como `www-data`, evitando que una orden de consola recree caché que Apache no pueda modificar. La reinicialización y la restauración eliminan la configuración mediante la API de PrestaShop para invalidar también su caché interna.

The dependency-free package checks run independently on PHP 8.1, 8.2, 8.3, 8.4 and 8.5. The real lifecycle simulates an upgrade from 2.15.0, verifies the mandatory encrypted backup and restores its module data, native rows and images.

## Navegador real y recorridos E2E (2.15.13)

Ejecute:

```sh
./tests/integration/run-e2e.sh
```

La suite usa Chromium y una instalación real, no HTML simulado. El fixture incluye el tamaño de página `2` entre las opciones permitidas para que la prueba de paginación ejercite dos páginas reales, configura la clave efectiva que permite quitar muestras y habilita `ps_wirepayment` para el país, moneda, grupos y transportistas del escenario. Playwright accede mediante `localhost`, el mismo dominio configurado en PrestaShop, para conservar la cookie administrativa durante las redirecciones. Los filtros esperan a que concluyan la respuesta AJAX, la actualización del historial y el estado de carga; las altas cierran el modal nativo del carrito antes de continuar. El carrito cuenta una única línea lógica con los selectores de los temas de PrestaShop 8 y 9, y el pago se abre mediante el enlace canónico generado por el tema. Los productos normales esperan la respuesta AJAX del carrito antes de navegar y el checkout comprueba el cierre de cada etapa usando las clases de PrestaShop 8 y 9; selecciona Francia, elige una provincia habilitada si el país la exige y solo inspecciona métodos cuando el paso de pago está activo. El cliente registrado resuelve la URL canónica y localizada de autenticación proporcionada por PrestaShop —con respaldo en el enlace real del tema—, usa exclusivamente `form#login-form` y verifica la salida de la ruta junto con el control de cierre de sesión. La confirmación de cada pedido exige la URL y un encabezado específico único. Si un formulario no avanza, el error conserva únicamente mensajes y campos inválidos visibles. La suite abre todas las pestañas —incluida la carga directa compatible con PrestaShop 9.1—, fuerza la paginación del catálogo, prueba altas y bajas de muestras, carritos exclusivos y mixtos, compras como invitado y cliente, cuatro anchos responsive, reemisión de cupón, borrado masivo de Auditoría y el ciclo completo reinicio-copia-restauración. Al terminar, un verificador dentro de PrestaShop confirma los resultados en base de datos y ejecuta transiciones reales de cancelación y reembolso.

CI ejecuta el navegador en PrestaShop 8.1/MySQL, 8.2/MariaDB y 9.1/MySQL. Ante un fallo conserva captura, vídeo, traza Playwright e informe HTML durante 14 días. El ZIP de producción no se publica si falla cualquiera de estos recorridos.

## Prueba específica de la versión 2.15.4

La regresión de reinicialización comprueba que la copia previa solo consulta tablas de configuración existentes, que una copia procedente de otra instalación tolera tablas auxiliares opcionales ausentes y que los resultados de diagnóstico con apóstrofos se almacenan de forma segura en Auditoría. El fallo del registro de auditoría se trata como una incidencia secundaria y no convierte el resultado de la operación en un error HTTP 500.

## Prueba específica de la versión 2.15.3

1. Actualice desde 2.15.2, vacíe la caché de PrestaShop y abra **Muestras > Auditoría**. La página debe mostrarse sin errores de compilación Smarty.
2. Marque **Seleccionar todos** y confirme que se seleccionan únicamente las filas visibles de la página actual.
3. Desmarque una fila y confirme que el selector general pasa al estado indeterminado; vuelva a marcarla y confirme que queda seleccionado.
4. Ejecute el borrado individual y el borrado masivo, comprobando que la confirmación, los permisos y el contexto de tienda continúan funcionando.
5. Pruebe los filtros, la paginación y la exportación CSV para descartar regresiones en las demás operaciones de Auditoría.

## Prueba específica de la versión 2.15.1

1. Mida la altura exterior del botón nativo **Comprar** con las herramientas del navegador.
2. Introduzca ese valor en **Diseño de botones > Botón en categorías y listados > Alto exacto**.
3. Vacíe la caché de PrestaShop y confirme que ambos botones tienen la misma altura exterior.
4. Pruebe un valor inferior a 24 px y confirme que se conserva, sin que el mínimo de 42 px de la hoja pública lo vuelva a ampliar.

Version 2.15.0 also runs PHPUnit, PHPStan level 5 and PHPCS/PSR-12 on the dependency-free domain and invariant layer. The production ZIP is published only after package, quality and full lifecycle jobs complete; CI emits SHA-256 and a build-provenance attestation.

## Pruebas específicas de la versión 2.15.2

1. Use un cupón original y pulse **Emitir nuevo cupón** con la casilla de correo desmarcada. Compruebe que el código original y su pedido no cambian, que aparece la reemisión #1 y que la nueva regla tiene un solo uso.
2. Sin usar la reemisión #1, intente emitir otra y compruebe que la interfaz lo impide y que la base de datos conserva una sola reemisión pendiente.
3. Use la reemisión #1 en un pedido, vuelva al listado y confirme que pasa a **Usado**. Emita la reemisión #2 mediante una nueva acción y verifique la secuencia completa.
4. Repita la emisión con **Enviar por correo** activado y compruebe la plantilla recibida, el código nuevo y el estado **Enviado** del historial.
5. Simule un fallo entre la creación de la regla nativa y el registro del historial. Confirme que no queda ninguna regla ni fila parcial.
6. Compruebe que las reemisiones aparecen en **Mis cupones de muestras**, se incluyen en exportación/borrado RGPD y sobreviven a una copia y restauración completa.

## Pruebas específicas de la versión 2.15.0

1. Lance dos peticiones simultáneas para añadir o incrementar la misma muestra y confirme que las cantidades nativa e interna coinciden.
2. Lance dos pagos simultáneos del mismo cliente cerca de su límite histórico; solo el que mantiene el límite debe completarse.
3. Configure ancho `85` y alto `34` en el botón de listados —o las medidas reales de su tema— y compárelo con el botón nativo.
4. Compruebe que las imágenes históricas están en `var/fabricssamples/orders`, no son accesibles directamente y sí aparecen en cuenta, pedido y PDF.
5. Ejecute dos operaciones de mantenimiento simultáneas y confirme que la segunda se rechaza sin modificar datos.
6. Borre por RGPD los clientes `12` y `123` por separado y confirme que no se anonimiza el cliente equivocado.

The following scenarios remain production-specific and should still be verified manually because they depend on the installed theme and payment/carrier modules:

- Classic theme and the production theme.
- Guest and registered customers.
- Cart with samples only, mixed cart and regular products only.
- Add, increment, decrement and remove from full cart and mini-cart.
- Tax included/excluded displays and store-specific tax rules.
- Every production carrier and payment module.
- SMTP success/failure and production email templates.
- Multishop contexts and shop-specific configuration.

A server-side limit must be tested with manual HTTP requests as well as the interface. Front-end disabling is informative only; the controller is the source of truth.

## Pruebas específicas de integridad del carrito 2.7.0

Ejecute cada escenario en PrestaShop 8 y 9:

1. Añadir una muestra con cantidad 1 y comprobar igualdad en las tres tablas.
2. Añadir la misma muestra otra vez y comprobar que las tres cantidades aumentan por igual.
3. Usar los botones `+` y `-` del carrito completo y del minicarrito.
4. Modificar la cantidad mediante el controlador nativo del tema y comprobar que `fabricssamples_cart` se sincroniza con `cart_product`.
5. Eliminar una muestra y comprobar que no quedan filas huérfanas en `cart_product`, `customization` ni `fabricssamples_cart`.
6. Cambiar la dirección de entrega y comprobar que `cart_product.id_address_delivery` y `customization.id_address_delivery` coinciden.
7. Alterar manualmente en una copia de pruebas una de las cantidades y abrir el carrito: debe repararse automáticamente.
8. Eliminar manualmente una personalización en una copia de pruebas y abrir el carrito: debe recrearse y vincularse.
9. Simular una corrupción no reparable y confirmar que el módulo bloquea el acceso al pago.
10. Completar pedidos con transferencia y todos los módulos de pago activos.

Consultas de comprobación para una línea concreta:

```sql
SELECT quantity,id_customization FROM ps_fabricssamples_cart WHERE id_cart = ?;
SELECT quantity,id_customization,id_address_delivery FROM ps_cart_product WHERE id_cart = ?;
SELECT quantity,id_customization,id_address_delivery FROM ps_customization WHERE id_cart = ?;
```


## Prueba de reinicialización y restauración 2.15.0

La suite automática crea datos propios, una imagen personalizada, una imagen histórica y una regla de carrito nativa. Después ejecuta `diagnosticResetModule()` y comprueba que:

1. las tablas se recrean vacías;
2. la migración 2.15.0 queda registrada como correcta;
3. vuelve el precio predeterminado;
4. desaparecen las imágenes de prueba;
5. se elimina la regla de carrito nativa;
6. el módulo sigue instalado.

## Prueba de copia cifrada 2.14.0

1. Añada datos a cada tabla del módulo y una imagen en cada carpeta gestionada.
2. Ejecute la reinicialización desde Diagnóstico.
3. Compruebe que el proceso informa del nombre de la copia antes de borrar datos.
4. Descargue el archivo desde **Copias cifradas y restauración** y confirme que usa extensión `.fsb` y no contiene texto JSON visible.
5. Restaure el archivo y compruebe que recupera filas `module_table`, `configuration_table`, `native_table` e imágenes.
6. Quite temporalmente el permiso de escritura del directorio de copias y confirme que la reinicialización se cancela sin borrar datos.
7. Modifique el límite y la antigüedad de retención y confirme que la poda respeta ambos valores.

## Pruebas de seguridad y RGPD 2.14.0

1. Pruebe cada acción con perfiles que tengan solo lectura, edición o borrado y confirme que el permiso coincide con la operación.
2. Repita cada mutación mediante GET, sin token y con token de otro controlador; ninguna debe modificar datos.
3. Guarde scripts, atributos `on*`, protocolos `javascript:` y CSS con `url()` o selectores globales; el front no debe conservarlos.
4. Suba imágenes válidas, sobredimensionadas, truncadas y archivos que solo imiten una extensión; únicamente las imágenes decodificables deben guardarse y deben volver a codificarse.
5. Exporte valores que comiencen por `=`, `+`, `-` y `@`; al abrir el CSV no deben ejecutarse como fórmulas.
6. Supere el límite AJAX por cinco segundos y por minuto, y envíe lotes de más de 50 productos; el controlador debe rechazar las peticiones.
7. Desde el módulo oficial RGPD, exporte un cliente con muestras y compruebe pedidos, cupones, conversiones y límites.
8. Ejecute el borrado RGPD y verifique la eliminación de datos operativos, la anonimización de referencias históricas y la inutilización de cupones.

## Prueba de auditoría 2.13.0

1. Genere al menos tres operaciones auditadas.
2. Borre una fila con su botón individual y confirme que desaparece.
3. Seleccione varias casillas, use **Borrar seleccionados** y confirme el número eliminado.
4. Compruebe que un empleado sin permiso de borrado recibe un error y que no se modifica ninguna fila.
5. Con muchos registros, navegue por la paginación compacta y confirme que conserva los filtros.

## Prueba de catálogo grande 2.13.0

1. Prepare al menos 10.000 productos y active una muestra en un subconjunto representativo.
2. Confirme con el perfilador SQL que la página no ejecuta `Product::getCover` ni una consulta de configuración por cada tarjeta.
3. Pruebe orden por popularidad y compruebe que la agregación filtra `fabricssamples_order.id_shop`.
4. Verifique que las categorías sin muestras activas no aparecen en el filtro y que no existe ningún filtro por fabricante.
5. Navegue a una página central y a la última página; la paginación debe mostrar una ventana con separadores, no todos los números.


## Pruebas operativas de la versión 2.12.0

1. Seleccione únicamente estados pagados/aceptados en **Productos y configuración → Límites**. Compruebe que pedidos cancelados o con error no incrementan el consumo histórico.
2. Cree una excepción personalizada para un grupo y otra para un cliente del mismo grupo. Verifique que la regla de cliente tiene prioridad.
3. Marque un grupo como exento y confirme que no se aplican límites históricos, pero sí el stock y los límites del carrito actual.
4. Use **Límites y excepciones → Consumo acumulado por cliente** y compare el total con `fabricssamples_order` filtrando los mismos estados y fechas.
5. Provoque un bloqueo por límite y compruebe que aparece en el historial y en la exportación CSV.
6. Realice ajustes individuales y masivos de stock. Confirme que no se aceptan ajustes cero, motivos vacíos ni resultados negativos.
7. Compruebe que cada ajuste crea una fila en `fabricssamples_stock_movement` y otra en `fabricssamples_audit`.
8. Edite y elimine permanentemente un cupón y confirme que ambas acciones quedan auditadas.
9. Ejecute una reparación de Diagnóstico y la reinicialización completa; ambas deben quedar registradas en Auditoría.
10. Exporte stock actual, movimientos, límites y auditoría, y abra los CSV con UTF-8 y separador punto y coma.


## Prueba de reconstrucción histórica 2.12.2

1. Cree una línea en `fabricssamples_order` para un pedido de muestra con `id_order_detail = 0`.
2. Mantenga la línea nativa correspondiente en `order_detail` con un nombre que empiece por `Muestra`.
3. Ejecute **Diagnóstico → Reconstruir históricos**.
4. Compruebe que la fila heredada se actualiza con el `id_order_detail` nativo y no se crea un duplicado.
5. Compruebe que el contador de líneas sin histórico queda a cero y que la acción solo se marca como correcta después de la comprobación final.
6. En una base heredada, confirme que ya no existe el índice `uniq_order_customization` y que sí existe `idx_order_customization`.


## Prueba de clasificación y descarte histórico 2.12.2

1. Deje una línea nativa cuyo nombre empiece por `Muestra` sin fila en `fabricssamples_order`.
2. Abra **Diagnóstico** y compruebe que aparece en la tabla de pendientes con pedido, línea y clasificación.
3. Pulse **Reconstruir históricos** y confirme que se enlaza mediante el flujo completo o mediante la recuperación mínima directa.
4. Para simular un falso positivo, deje otra línea que no pertenezca al módulo y pulse **Descartar pendientes**.
5. Compruebe que el pedido y `order_detail` permanecen intactos, que se crea una fila en `fabricssamples_history_exclusion` y que desaparece el aviso.
6. Pulse **Volver a revisar descartados** y confirme que la línea vuelve a aparecer como pendiente.
