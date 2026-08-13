# Copias cifradas y restauración

Desde 2.14.0, `diagnosticResetModule()` crea y verifica una copia cifrada antes de ejecutar cualquier borrado. Si falla la lectura consistente, la compresión, el cifrado o la verificación de ida y vuelta, la reinicialización se cancela.

## Formato y protección

Los archivos nuevos se llaman `fabricssamples-reset-*.fsb`. El contenido lógico continúa siendo JSON Lines comprimido, pero nunca se publica sin cifrar. El contenedor cifra bloques independientes con AES-256-GCM, autentica el orden de cada bloque y valida el tamaño y SHA-256 del flujo completo.

La clave se deriva de `_COOKIE_KEY_`, el módulo y el prefijo de base de datos. No se guarda junto a la copia. Si cambia `_COOKIE_KEY_`, las copias anteriores dejarán de poder descifrarse; conserve de forma segura la configuración criptográfica de la tienda junto con su plan de recuperación.

El flujo interno incluye:

- `manifest`: formato, versiones, fecha, prefijo SQL y contexto;
- `module_table`: filas de las tablas propias;
- `configuration_table`: configuración general, por idioma y tienda;
- `native_table`: personalizaciones y reglas de carrito vinculadas;
- `file`: ruta autorizada, tamaño, SHA-256 y contenido;
- `summary`: recuentos exportados.

## Restauración

Diagnóstico permite restaurar únicamente archivos `.fsb`. La operación exige permiso de borrado, POST, token y confirmación explícita. Antes de vaciar el estado actual se crea otra copia cifrada. La restauración valida el archivo completo, limita las tablas y rutas a listas blancas, restaura la base de datos bajo transacción y publica después las imágenes preparadas.

Los archivos `.jsonl.gz` creados por 2.13.0 permanecen disponibles solo para descarga técnica; no se restauran automáticamente.

## Retención y verificación

El número máximo y la antigüedad se configuran en **Productos y configuración → Privacidad y seguridad**. Las pruebas de integración ejecutan una recuperación completa de tablas propias, filas nativas e imágenes. Debe probarse también el procedimiento de recuperación en una copia de la tienda antes de depender de él en producción.
