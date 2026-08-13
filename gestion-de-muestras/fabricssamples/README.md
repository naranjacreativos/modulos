# Gestión de muestras

Gestión de muestras de tejido de los productos de la tienda y cupones descuento.

Creado por Naranja Creativos · https://www.naranjacreativos.es

## Versión

Versión 2.15.20 del módulo Gestión de muestras para PrestaShop 8.1, 8.2 y 9.1.

## Validación

Este candidato se entrega con validación de sintaxis PHP, integridad mediante `MANIFEST.sha256` y comprobaciones de contrato del ciclo de stock que verifican:

- que los hooks tempranos no alteran stock;
- que al confirmar el pedido el stock nativo del producto se compensa;
- que el stock independiente de muestras se descuenta por separado;
- que una segunda reconciliación no duplica movimientos;
- que cancelación/reembolso mantiene ambos libros de stock coherentes.

La documentación técnica se encuentra en [`docs/`](docs/README.md) y las instrucciones de pruebas en [`docs/TESTING.md`](docs/TESTING.md).

## Distribución

El repositorio es la fuente de desarrollo. El ZIP instalable se genera mediante `bin/build-production.sh` y excluye pruebas, CI y herramientas de desarrollo.
