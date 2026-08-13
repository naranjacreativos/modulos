<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Diagnostic;

final class DiagnosticService
{
    public function __construct(
        private \Fabricssamples $module,
        private SchemaInspector $schemaInspector
    ) {
    }

    /** @return array{sections:array<string,array{title:string,items:list<array<string,mixed>>}>,summary:array<string,int>,history_pending:list<array<string,mixed>>,history_ignored:int,generated_at:string} */
    public function run(int $idShop): array
    {
        $sections = [
            'system' => ['title' => 'Servidor y compatibilidad', 'items' => []],
            'database' => ['title' => 'Base de datos', 'items' => []],
            'integration' => ['title' => 'Hooks, pestañas y rutas', 'items' => []],
            'data' => ['title' => 'Integridad de datos', 'items' => []],
        ];

        $this->systemChecks($sections['system']['items']);
        $this->databaseChecks($sections['database']['items']);
        $this->integrationChecks($sections['integration']['items'], $idShop);
        $this->dataChecks($sections['data']['items'], $idShop);

        $summary = ['ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0];
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $status = (string) ($item['status'] ?? 'info');
                if (!isset($summary[$status])) {
                    $status = 'info';
                }
                ++$summary[$status];
            }
        }

        return [
            'sections' => $sections,
            'summary' => $summary,
            'history_pending' => $this->module->diagnosticMissingOrderHistoryRows($idShop, 100),
            'history_ignored' => $this->module->countIgnoredOrderHistory($idShop),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /** @param list<array<string,mixed>> $items */
    private function systemChecks(array &$items): void
    {
        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        $this->add($items, 'php', 'Versión de PHP', $phpOk ? 'ok' : 'error', PHP_VERSION, $phpOk ? 'Compatible.' : 'Se requiere PHP 8.1 o superior.');

        $schemaVersion = (string) \Configuration::get('FABRICS_SAMPLES_SCHEMA_VERSION');
        $this->add($items, 'module_version', 'Versión del módulo / esquema', 'info', $this->module->version . ' / ' . ($schemaVersion !== '' ? $schemaVersion : 'sin registrar'), 'La versión de esquema se actualiza al instalar o migrar el módulo.');

        $psVersion = defined('_PS_VERSION_') ? (string) _PS_VERSION_ : 'desconocida';
        $psOk = version_compare($psVersion, '8.1.0', '>=') && version_compare($psVersion, '10.0.0', '<');
        $this->add($items, 'prestashop', 'Versión de PrestaShop', $psOk ? 'ok' : 'warning', $psVersion, $psOk ? 'Dentro del rango declarado por el módulo.' : 'No está dentro del rango probado/declarado.');

        $dbVersionRows = $this->query('SELECT VERSION() AS version');
        $dbVersion = (string) ($dbVersionRows[0]['version'] ?? 'desconocida');
        $this->add($items, 'database_version', 'Servidor SQL', 'info', $dbVersion, 'Se comprueba compatibilidad con MySQL/MariaDB sin utilizar information_schema.');

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $procOpenAvailable = function_exists('proc_open') && !in_array('proc_open', $disabled, true);
        $smtpServer = trim((string) (\Configuration::get('PS_MAIL_SERVER') ?: \Configuration::get('PS_MAIL_SMTP_SERVER')));
        $mailMethod = (string) \Configuration::get('PS_MAIL_METHOD');
        $mailStatus = $procOpenAvailable || $smtpServer !== '' || $mailMethod === '2' ? 'ok' : 'warning';
        $mailDetail = 'PS_MAIL_METHOD=' . ($mailMethod === '' ? 'no definido' : $mailMethod)
            . '; SMTP=' . ($smtpServer !== '' ? $smtpServer : 'no configurado')
            . '; proc_open=' . ($procOpenAvailable ? 'disponible' : 'no disponible');
        $this->add($items, 'mail', 'Correo del servidor', $mailStatus, $smtpServer !== '' ? 'SMTP configurado' : 'Sin SMTP detectado', $mailDetail);

        foreach ([
            'module' => $this->module->getLocalPath(),
            'order_images' => rtrim((string) _PS_ROOT_DIR_, '/\\') . '/var/fabricssamples/orders/',
            'custom_images' => $this->module->getLocalPath() . 'views/img/custom/',
        ] as $key => $path) {
            $directory = is_dir($path) ? $path : dirname($path);
            $writable = is_dir($directory) && is_writable($directory);
            $this->add($items, 'writable_' . $key, 'Permisos: ' . $key, $writable ? 'ok' : 'warning', $path, $writable ? 'Directorio escribible.' : 'El módulo puede no poder guardar imágenes o archivos temporales.');
        }
    }

    /** @param list<array<string,mixed>> $items */
    private function databaseChecks(array &$items): void
    {
        $inspection = $this->schemaInspector->inspect();
        $missingTableCount = count($inspection['missing_tables']);
        $missingColumnCount = array_sum(array_map('count', $inspection['missing_columns']));
        $missingIndexCount = array_sum(array_map('count', $inspection['missing_indexes']));

        $this->add(
            $items,
            'tables',
            'Tablas del módulo',
            $missingTableCount === 0 ? 'ok' : 'error',
            (string) $inspection['table_count'],
            $missingTableCount === 0 ? 'Todas las tablas existen.' : 'Faltan: ' . implode(', ', $inspection['missing_tables']),
            'schema'
        );
        $columnDetails = [];
        foreach ($inspection['missing_columns'] as $table => $columns) {
            $columnDetails[] = $table . ': ' . implode(', ', $columns);
        }
        $this->add(
            $items,
            'columns',
            'Columnas obligatorias',
            $missingColumnCount === 0 ? 'ok' : 'error',
            (string) $inspection['column_count'],
            $missingColumnCount === 0 ? 'No faltan columnas.' : implode(' | ', $columnDetails),
            'schema'
        );
        $indexDetails = [];
        foreach ($inspection['missing_indexes'] as $table => $indexes) {
            $indexDetails[] = $table . ': ' . implode(', ', $indexes);
        }
        $this->add(
            $items,
            'indexes',
            'Índices obligatorios',
            $missingIndexCount === 0 ? 'ok' : 'warning',
            (string) $inspection['index_count'],
            $missingIndexCount === 0 ? 'No faltan índices.' : implode(' | ', $indexDetails),
            'schema'
        );

        $migrationTable = _DB_PREFIX_ . 'fabricssamples_schema_migration';
        if (!$this->schemaInspector->tableExists($migrationTable)) {
            $this->add($items, 'migration', 'Última migración', 'error', 'Sin registro', 'Falta la tabla de control de migraciones.', 'schema');
        } else {
            $rows = $this->query(
                'SELECT migration_version,status,attempts,started_at,finished_at,error_message'
                . ' FROM `' . $migrationTable . '` ORDER BY id_fabricssamples_schema_migration DESC'
            );
            $latest = $rows[0] ?? [];
            $status = (string) ($latest['status'] ?? 'none');
            $diagnosticStatus = $status === 'success' ? 'ok' : ($status === 'failed' ? 'error' : 'warning');
            $value = $latest === [] ? 'Sin ejecuciones' : (string) ($latest['migration_version'] ?? '') . ' / ' . $status;
            $details = $latest === []
                ? 'La instalación no ha registrado todavía una migración.'
                : 'Intentos: ' . (int) ($latest['attempts'] ?? 0)
                    . '; inicio: ' . (string) ($latest['started_at'] ?? '')
                    . '; fin: ' . (string) ($latest['finished_at'] ?? '')
                    . ((string) ($latest['error_message'] ?? '') !== '' ? '; error: ' . (string) $latest['error_message'] : '');
            $this->add($items, 'migration', 'Última migración', $diagnosticStatus, $value, $details, 'schema');
        }
    }

    /** @param list<array<string,mixed>> $items */
    private function integrationChecks(array &$items, int $idShop): void
    {
        $registeredHooks = [];
        $hookRows = $this->query(
            'SELECT DISTINCT h.name FROM `' . _DB_PREFIX_ . 'hook` h'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'hook_module` hm ON hm.id_hook=h.id_hook'
            . ' WHERE hm.id_module=' . (int) $this->module->id
            . ($idShop > 0 ? ' AND hm.id_shop=' . $idShop : '')
        );
        foreach ($hookRows as $row) {
            $registeredHooks[(string) ($row['name'] ?? '')] = true;
        }
        $missingHooks = [];
        foreach ($this->module->getRequiredHooks() as $hook) {
            if (!isset($registeredHooks[$hook])) {
                $missingHooks[] = $hook;
            }
        }
        $this->add(
            $items,
            'hooks',
            'Hooks obligatorios',
            $missingHooks === [] ? 'ok' : 'error',
            (string) (count($this->module->getRequiredHooks()) - count($missingHooks)) . '/' . count($this->module->getRequiredHooks()),
            $missingHooks === [] ? 'Todos los hooks están registrados.' : 'Faltan: ' . implode(', ', $missingHooks),
            'hooks'
        );

        $expectedTabs = [
            'AdminFabricSamplesParent' => 0,
            'AdminFabricSamples' => 1,
            'AdminFabricSamplesCoupons' => 1,
            'AdminFabricSamplesStock' => 1,
            'AdminFabricSamplesLimits' => 1,
            'AdminFabricSamplesAudit' => 1,
            'AdminFabricSamplesDiagnostics' => 1,
        ];
        $tabRows = $this->query(
            'SELECT id_tab,id_parent,class_name,module,active FROM `' . _DB_PREFIX_ . 'tab`'
            . " WHERE class_name IN ('AdminFabricSamplesParent','AdminFabricSamples','AdminFabricSamplesCoupons','AdminFabricSamplesStock','AdminFabricSamplesLimits','AdminFabricSamplesAudit','AdminFabricSamplesDiagnostics')"
        );
        $tabs = [];
        foreach ($tabRows as $row) {
            $tabs[(string) ($row['class_name'] ?? '')] = $row;
        }
        $missingTabs = array_values(array_diff(array_keys($expectedTabs), array_keys($tabs)));
        $this->add(
            $items,
            'tabs',
            'Pestañas del back office',
            $missingTabs === [] ? 'ok' : 'warning',
            (string) count($tabs) . '/' . count($expectedTabs),
            $missingTabs === [] ? 'Todas las pestañas existen.' : 'Faltan: ' . implode(', ', $missingTabs),
            'tabs'
        );

        $slug = trim((string) \Configuration::get('FABRICS_SAMPLES_FRIENDLY_URL'));
        $routeStatus = isset($registeredHooks['moduleRoutes']) && $slug !== '' ? 'ok' : 'warning';
        $technicalUrl = \Context::getContext()->link->getModuleLink('fabricssamples', 'samples', [], true);
        $this->add(
            $items,
            'routes',
            'Rutas del módulo',
            $routeStatus,
            $slug !== '' ? '/' . trim($slug, '/') : 'sin URL amigable',
            'URL técnica: ' . $technicalUrl,
            'routes'
        );
    }

    /** @param list<array<string,mixed>> $items */
    private function dataChecks(array &$items, int $idShop): void
    {
        $requiredTables = [
            _DB_PREFIX_ . 'fabricssamples_cart',
            _DB_PREFIX_ . 'fabricssamples_order',
            _DB_PREFIX_ . 'fabricssamples_history_exclusion',
            _DB_PREFIX_ . 'fabricssamples_coupon',
            _DB_PREFIX_ . 'fabricssamples_coupon_reissue',
            _DB_PREFIX_ . 'fabricssamples_product',
            _DB_PREFIX_ . 'fabricssamples_stock_movement',
            _DB_PREFIX_ . 'fabricssamples_limit_exception',
            _DB_PREFIX_ . 'fabricssamples_limit_reset',
            _DB_PREFIX_ . 'fabricssamples_limit_event',
            _DB_PREFIX_ . 'fabricssamples_audit',
        ];
        $missingTables = array_values(array_filter($requiredTables, fn (string $table): bool => !$this->schemaInspector->tableExists($table)));
        if ($missingTables !== []) {
            $this->add($items, 'data_unavailable', 'Integridad de datos', 'error', 'No disponible', 'Faltan tablas: ' . implode(', ', $missingTables) . '.', 'schema');
            return;
        }

        $shopFilter = $idShop > 0 ? ' AND fsc.id_shop=' . $idShop : '';
        $cartIssues = $this->scalar(
            'SELECT COUNT(*) AS total FROM ('
            . ' SELECT fsc.id_fabricssamples_cart'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'customization` c ON c.id_customization=fsc.id_customization'
            . ' LEFT JOIN ('
            . '  SELECT id_cart,id_product,id_product_attribute,id_customization,SUM(quantity) native_quantity'
            . '  FROM `' . _DB_PREFIX_ . 'cart_product`'
            . '  GROUP BY id_cart,id_product,id_product_attribute,id_customization'
            . ' ) cp ON cp.id_cart=fsc.id_cart AND cp.id_product=fsc.id_product'
            . ' AND cp.id_product_attribute=fsc.id_product_attribute AND cp.id_customization=fsc.id_customization'
            . ' WHERE (c.id_customization IS NULL OR cp.id_cart IS NULL OR c.quantity<>fsc.quantity OR cp.native_quantity<>fsc.quantity)'
            . $shopFilter
            . ' GROUP BY fsc.id_fabricssamples_cart'
            . ') x'
        );
        $this->add($items, 'cart_integrity', 'Líneas de carrito incoherentes', $cartIssues === 0 ? 'ok' : 'error', (string) $cartIssues, $cartIssues === 0 ? 'No se detectan diferencias de cantidad o personalización.' : 'Hay líneas que necesitan reparación.', 'carts');

        $missingHistory = $this->module->countMissingOrderHistory($idShop);
        $ignoredHistory = $this->module->countIgnoredOrderHistory($idShop);
        $this->add(
            $items,
            'history',
            'Líneas de muestra sin histórico enlazado',
            $missingHistory === 0 ? 'ok' : 'warning',
            (string) $missingHistory,
            $missingHistory === 0
                ? 'Todas las líneas confirmadas están vinculadas. Exclusiones manuales: ' . $ignoredHistory . '.'
                : 'Hay líneas antiguas sin vínculo. Use Reconstruir históricos y revise el detalle inferior. Las líneas que no pertenezcan al módulo pueden descartarse sin borrar el pedido.',
            'history'
        );
        $this->add(
            $items,
            'history_ignored',
            'Líneas históricas descartadas manualmente',
            'info',
            (string) $ignoredHistory,
            $ignoredHistory === 0
                ? 'No hay exclusiones manuales.'
                : 'Se omiten del aviso, pero los pedidos y sus líneas nativas permanecen intactos.',
            'history_restore'
        );

        $couponShopFilter = $idShop > 0 ? ' AND fsc.id_shop=' . $idShop : '';
        $missingCartRules = $this->scalar(
            'SELECT COUNT(*) AS total FROM ('
            . ' SELECT fsc.id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.id_cart_rule=fsc.id_cart_rule'
            . ' WHERE fsc.deleted_permanently=0 AND cr.id_cart_rule IS NULL' . $couponShopFilter
            . ' UNION ALL'
            . ' SELECT fsr.id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` fsr'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr2 ON cr2.id_cart_rule=fsr.id_cart_rule'
            . " WHERE fsr.state<>'deleted_permanently' AND cr2.id_cart_rule IS NULL"
            . ($idShop > 0 ? ' AND fsr.id_shop=' . $idShop : '')
            . ') missing_rules'
        );
        $this->add($items, 'coupons', 'Cupones sin regla nativa', $missingCartRules === 0 ? 'ok' : 'warning', (string) $missingCartRules, $missingCartRules === 0 ? 'Todos los cupones tienen regla de carrito.' : 'Se recomienda sincronizar cupones.', 'coupons');

        $negativeStock = $this->scalar(
            'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
            . " WHERE stock_mode='independent' AND sample_stock<0"
            . ($idShop > 0 ? ' AND id_shop=' . $idShop : '')
        );
        $this->add($items, 'negative_stock', 'Muestras con stock negativo', $negativeStock === 0 ? 'ok' : 'error', (string) $negativeStock, $negativeStock === 0 ? 'No hay stock negativo.' : 'Se debe reconciliar el stock.', 'stock');

        $duplicateMovements = $this->scalar(
            'SELECT COUNT(*) AS total FROM ('
            . ' SELECT movement_reference FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement`'
            . ($idShop > 0 ? ' WHERE id_shop=' . $idShop : '')
            . ' GROUP BY movement_reference HAVING COUNT(*)>1'
            . ') x'
        );
        $this->add($items, 'stock_movements', 'Movimientos de stock duplicados', $duplicateMovements === 0 ? 'ok' : 'error', (string) $duplicateMovements, $duplicateMovements === 0 ? 'No se detectan referencias duplicadas.' : 'Revise el historial de stock.', 'stock');

        $orphanOrders = $this->scalar(
            'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order=fso.id_order'
            . ' WHERE o.id_order IS NULL' . ($idShop > 0 ? ' AND fso.id_shop=' . $idShop : '')
        );
        $orphanCarts = $this->scalar(
            'SELECT COUNT(*) AS total FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'cart` c ON c.id_cart=fsc.id_cart'
            . ' WHERE c.id_cart IS NULL' . ($idShop > 0 ? ' AND fsc.id_shop=' . $idShop : '')
        );
        $orphanTotal = $orphanOrders + $orphanCarts;
        $this->add($items, 'orphans', 'Registros huérfanos', $orphanTotal === 0 ? 'ok' : 'warning', (string) $orphanTotal, 'Históricos sin pedido: ' . $orphanOrders . '; líneas sin carrito: ' . $orphanCarts . '.', 'orphans');
    }

    /** @param list<array<string,mixed>> $items */
    private function add(array &$items, string $key, string $label, string $status, string $value, string $details, string $repairAction = ''): void
    {
        $items[] = [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'value' => $value,
            'details' => $details,
            'repair_action' => $repairAction,
        ];
    }

    private function scalar(string $sql): int
    {
        $rows = $this->query($sql);
        return (int) ($rows[0]['total'] ?? 0);
    }

    /** @return list<array<string,mixed>> */
    private function query(string $sql): array
    {
        $rows = \Db::getInstance()->executeS($sql);
        return is_array($rows) ? $rows : [];
    }
}
