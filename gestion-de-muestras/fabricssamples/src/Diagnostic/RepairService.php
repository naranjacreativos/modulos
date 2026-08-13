<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Diagnostic;

final class RepairService
{
    public function __construct(
        private \Fabricssamples $module,
        private SchemaInspector $schemaInspector
    ) {
    }

    /** @return array{success:bool,message:string,details:array<string,mixed>} */
    public function execute(string $action, int $idShop): array
    {
        return match ($action) {
            'schema' => $this->repairSchema(),
            'hooks' => $this->repairHooks(),
            'tabs' => $this->repairTabs(),
            'routes' => $this->repairRoutes(),
            'carts' => $this->repairCarts($idShop),
            'history' => $this->repairHistory($idShop),
            'history_ignore' => $this->ignoreHistoryWarnings($idShop),
            'history_restore' => $this->restoreHistoryWarnings($idShop),
            'stock' => $this->repairStock($idShop),
            'coupons' => $this->repairCoupons($idShop),
            'orphans' => $this->cleanOrphans($idShop),
            'all' => $this->repairAll($idShop),
            'reset' => $this->resetModule(),
            default => ['success' => false, 'message' => 'Acción de reparación desconocida.', 'details' => []],
        };
    }

    private function repairSchema(): array
    {
        $result = $this->schemaInspector->repair();
        $success = $result['errors'] === [];
        if ($success) {
            $inspection = $this->schemaInspector->inspect();
            $success = $inspection['missing_tables'] === []
                && array_sum(array_map('count', $inspection['missing_columns'])) === 0
                && array_sum(array_map('count', $inspection['missing_indexes'])) === 0;
            if ($success) {
                \Configuration::updateValue('FABRICS_SAMPLES_SCHEMA_VERSION', \Fabricssamples::VERSION);
            }
        }
        return [
            'success' => $success,
            'message' => sprintf(
                'Esquema revisado: %d tablas creadas, %d columnas añadidas y %d índices añadidos.',
                $result['created_tables'],
                $result['added_columns'],
                $result['added_indexes']
            ),
            'details' => $result,
        ];
    }

    private function repairHooks(): array
    {
        $registered = 0;
        $errors = [];
        foreach (array_merge($this->module->getRequiredHooks(), ['actionValidateOrderBefore']) as $hookName) {
            $hookRows = \Db::getInstance()->executeS(
                'SELECT id_hook FROM `' . _DB_PREFIX_ . 'hook` WHERE name=\'' . pSQL($hookName) . '\''
            );
            $idHook = (int) ($hookRows[0]['id_hook'] ?? 0);
            if ($idHook <= 0) {
                continue;
            }
            $moduleRows = \Db::getInstance()->executeS(
                'SELECT id_module FROM `' . _DB_PREFIX_ . 'hook_module`'
                . ' WHERE id_module=' . (int) $this->module->id . ' AND id_hook=' . $idHook
            );
            if ($moduleRows !== []) {
                continue;
            }
            if ($this->module->registerHook($hookName)) {
                ++$registered;
            } else {
                $errors[] = $hookName;
            }
        }

        return [
            'success' => $errors === [],
            'message' => $errors === []
                ? sprintf('%d hooks registrados o reparados.', $registered)
                : 'No se pudieron registrar: ' . implode(', ', $errors),
            'details' => ['registered' => $registered, 'errors' => $errors],
        ];
    }

    private function repairTabs(): array
    {
        $db = \Db::getInstance();
        $definitions = [
            'AdminFabricSamplesParent' => ['label' => 'Muestras de tejidos', 'parent' => 0],
            'AdminFabricSamples' => ['label' => 'Productos y configuración', 'parent' => 'AdminFabricSamplesParent'],
            'AdminFabricSamplesCoupons' => ['label' => 'Cupones de muestras', 'parent' => 'AdminFabricSamplesParent'],
            'AdminFabricSamplesStock' => ['label' => 'Stock de muestras', 'parent' => 'AdminFabricSamplesParent'],
            'AdminFabricSamplesLimits' => ['label' => 'Límites y excepciones', 'parent' => 'AdminFabricSamplesParent'],
            'AdminFabricSamplesAudit' => ['label' => 'Auditoría', 'parent' => 'AdminFabricSamplesParent'],
            'AdminFabricSamplesDiagnostics' => ['label' => 'Diagnóstico', 'parent' => 'AdminFabricSamplesParent'],
        ];
        $ids = [];
        $created = 0;
        $updated = 0;

        foreach ($definitions as $className => $definition) {
            $rows = $db->executeS(
                'SELECT id_tab,id_parent,module,active FROM `' . _DB_PREFIX_ . 'tab`'
                . ' WHERE class_name=\'' . pSQL($className) . '\''
            );
            $idTab = (int) ($rows[0]['id_tab'] ?? 0);
            $parentId = is_string($definition['parent']) ? (int) ($ids[$definition['parent']] ?? 0) : 0;
            if ($idTab <= 0) {
                $tab = new \Tab();
                $tab->active = 1;
                $tab->class_name = $className;
                $tab->module = $this->module->name;
                $tab->id_parent = $parentId;
                foreach (\Language::getLanguages(false) as $language) {
                    $tab->name[(int) $language['id_lang']] = $definition['label'];
                }
                if (!$tab->add()) {
                    return ['success' => false, 'message' => 'No se pudo crear la pestaña ' . $className . '.', 'details' => []];
                }
                $idTab = (int) $tab->id;
                ++$created;
            } else {
                $needsUpdate = (int) ($rows[0]['id_parent'] ?? -1) !== $parentId
                    || (string) ($rows[0]['module'] ?? '') !== $this->module->name
                    || (int) ($rows[0]['active'] ?? 0) !== 1;
                if ($needsUpdate) {
                    $db->update('tab', [
                        'id_parent' => $parentId,
                        'module' => pSQL($this->module->name),
                        'active' => 1,
                    ], 'id_tab=' . $idTab);
                    ++$updated;
                }
            }
            $ids[$className] = $idTab;
        }

        return [
            'success' => true,
            'message' => sprintf('%d pestañas creadas y %d actualizadas.', $created, $updated),
            'details' => ['created' => $created, 'updated' => $updated],
        ];
    }

    private function repairRoutes(): array
    {
        $hooks = $this->repairHooks();
        try {
            if (method_exists('Tools', 'clearAllCache')) {
                \Tools::clearAllCache();
            }
            if (class_exists('Cache')) {
                \Cache::clean('*');
            }
        } catch (\Throwable $exception) {
            return ['success' => false, 'message' => 'Hooks reparados, pero no se pudo limpiar la caché: ' . $exception->getMessage(), 'details' => $hooks['details']];
        }

        return ['success' => $hooks['success'], 'message' => 'Rutas registradas y caché del módulo limpiada.', 'details' => $hooks['details']];
    }

    private function repairCarts(int $idShop): array
    {
        $result = $this->module->diagnosticRepairSampleCarts($idShop);
        return [
            'success' => $result['errors'] === 0,
            'message' => sprintf('%d carritos revisados, %d líneas reparadas, %d eliminadas y %d errores.', $result['carts'], $result['repaired'], $result['removed'], $result['errors']),
            'details' => $result,
        ];
    }

    private function repairHistory(int $idShop): array
    {
        $result = $this->module->diagnosticRepairOrderHistory($idShop);
        $failureCount = count($result['failures'] ?? []);

        return [
            'success' => $result['success'],
            'message' => $result['success']
                ? sprintf('%d líneas históricas enlazadas o reconstruidas. No quedan incidencias.', $result['repaired'])
                : sprintf(
                    '%d líneas reparadas, pero quedan %d pendientes (%d fallos detallados). Puede revisar la tabla inferior o descartarlas como no pertenecientes al módulo.',
                    $result['repaired'],
                    $result['remaining'],
                    $failureCount
                ),
            'details' => $result,
        ];
    }

    private function ignoreHistoryWarnings(int $idShop): array
    {
        $result = $this->module->diagnosticIgnoreMissingOrderHistory($idShop);
        $success = (string) ($result['error'] ?? '') === '';

        return [
            'success' => $success,
            'message' => $success
                ? sprintf('%d líneas se han marcado como no pertenecientes al módulo. No se ha borrado ni modificado ningún pedido.', (int) ($result['ignored'] ?? 0))
                : 'No se pudieron descartar las líneas pendientes: ' . (string) ($result['error'] ?? ''),
            'details' => $result,
        ];
    }

    private function restoreHistoryWarnings(int $idShop): array
    {
        $restored = $this->module->diagnosticClearIgnoredOrderHistory($idShop);

        return [
            'success' => true,
            'message' => sprintf('%d exclusiones eliminadas. Esas líneas volverán a revisarse en el diagnóstico.', $restored),
            'details' => ['restored' => $restored],
        ];
    }

    private function repairStock(int $idShop): array
    {
        $count = $this->module->diagnosticReconcileAllStock($idShop);
        return ['success' => true, 'message' => sprintf('%d movimientos/ajustes de stock aplicados.', $count), 'details' => ['movements' => $count]];
    }

    private function repairCoupons(int $idShop): array
    {
        $count = $this->module->diagnosticSynchronizeAllCoupons($idShop);
        return ['success' => true, 'message' => sprintf('%d cupones creados, reparados o sincronizados.', $count), 'details' => ['coupons' => $count]];
    }

    private function cleanOrphans(int $idShop): array
    {
        $result = $this->module->diagnosticCleanOrphans($idShop);
        return [
            'success' => true,
            'message' => sprintf('%d registros huérfanos eliminados.', array_sum($result)),
            'details' => $result,
        ];
    }

    private function resetModule(): array
    {
        $reset = $this->module->diagnosticResetModule();
        if (!$reset['success']) {
            return [
                'success' => false,
                'message' => 'No se pudo completar la reinicialización. El módulo intentó conservar o reconstruir una instalación utilizable; revise el registro técnico.',
                'details' => $reset,
            ];
        }

        $tabs = $this->repairTabs();
        $hooks = $this->repairHooks();
        $routes = $this->repairRoutes();
        $success = $tabs['success'] && $hooks['success'] && $routes['success'];
        $backupFilename = (string) ($reset['backup']['filename'] ?? '');

        return [
            'success' => $success,
            'message' => $success
                ? 'El módulo se ha reinicializado después de crear la copia de seguridad '
                    . ($backupFilename !== '' ? $backupFilename . '. ' : '')
                    . 'Se han recreado sus datos y la configuración predeterminada.'
                : 'Los datos se reinicializaron, pero alguna pestaña, hook o ruta necesita revisión.',
            'details' => [
                'reset' => $reset,
                'tabs' => $tabs,
                'hooks' => $hooks,
                'routes' => $routes,
            ],
        ];
    }

    private function repairAll(int $idShop): array
    {
        $details = [];
        $success = true;
        foreach (['schema', 'hooks', 'tabs', 'routes', 'carts', 'history', 'stock', 'coupons', 'orphans'] as $action) {
            $result = $this->execute($action, $idShop);
            $details[$action] = $result;
            $success = $result['success'] && $success;
        }
        return [
            'success' => $success,
            'message' => $success ? 'Reparación completa finalizada.' : 'La reparación finalizó con incidencias. Revise los detalles y registros.',
            'details' => $details,
        ];
    }
}
