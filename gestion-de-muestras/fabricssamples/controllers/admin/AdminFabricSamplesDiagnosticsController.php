<?php

require_once dirname(__DIR__, 2) . '/config/autoload.php';

use NaranjaCreativos\FabricSamples\Diagnostic\DiagnosticService;
use NaranjaCreativos\FabricSamples\Diagnostic\RepairService;
use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;
use NaranjaCreativos\FabricSamples\Backup\ResetBackupService;
use NaranjaCreativos\FabricSamples\Backup\RestoreBackupService;
use NaranjaCreativos\FabricSamples\Repository\AuditRepository;
use NaranjaCreativos\FabricSamples\Service\AuditService;
use NaranjaCreativos\FabricSamples\Security\AdminControllerSecurityTrait;
use NaranjaCreativos\FabricSamples\Infrastructure\DatabaseLock;

class AdminFabricSamplesDiagnosticsController extends ModuleAdminController
{
    use AdminControllerSecurityTrait;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('Diagnóstico del módulo');
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/diagnostics.css');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('downloadFabricSamplesBackup')) {
            if ($this->guardAdminAction('view', false)) {
                $this->downloadBackup();
            }
        }
        if (Tools::isSubmit('restoreFabricSamplesBackup')) {
            if ($this->guardAdminAction('delete')) {
                $this->restoreBackup();
            }
        }
        if (Tools::isSubmit('submitFabricSamplesDiagnosticRepair')) {
            $action = (string) Tools::getValue('diagnostic_action');
            $permission = in_array($action, ['reset', 'history_ignore', 'orphans'], true) ? 'delete' : 'edit';
            if (!$this->guardAdminAction($permission)) {
                return parent::postProcess();
            }
            if ($action === 'reset'
                && (string) Tools::getValue('diagnostic_reset_confirmation') !== 'DELETE_ALL_FABRIC_SAMPLES_DATA') {
                $this->errors[] = $this->module->l('No se recibió la confirmación obligatoria para reinicializar el módulo.');
            } elseif ($action === 'history_ignore'
                && (string) Tools::getValue('diagnostic_history_confirmation') !== 'IGNORE_UNRESOLVED_HISTORY') {
                $this->errors[] = $this->module->l('No se recibió la confirmación obligatoria para descartar los avisos históricos.');
            } else {
                try {
                    $result = (new DatabaseLock())->synchronized(
                        'maintenance',
                        fn (): array => $this->repairService()->execute($action, $this->contextShopId()),
                        2
                    );
                } catch (Throwable $exception) {
                    $this->errors[] = $this->module->l('Ya hay otra reparación, reinicialización o restauración en curso.');
                    return parent::postProcess();
                }
                if ($result['success']) {
                    $this->confirmations[] = $result['message'];
                } else {
                    $this->errors[] = $result['message'];
                }
                $this->auditDiagnosticResult($action, $result);
                if (!empty($result['details'])) {
                    PrestaShopLogger::addLog(
                        'fabricssamples diagnostic ' . $action . ': ' . json_encode($result['details'], JSON_UNESCAPED_UNICODE),
                        $result['success'] ? 1 : 2,
                        null,
                        'Module',
                        (int) $this->module->id,
                        true
                    );
                }
            }
        }
        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();

        $report = $this->diagnosticService()->run($this->contextShopId());
        $token = Tools::getAdminTokenLite($this->controller_name);
        $backups = (new ResetBackupService($this->module))->listAvailable();
        foreach ($backups as &$backup) {
            $backup['size_formatted'] = number_format(((int) $backup['size']) / 1024, 1, ',', '.') . ' KB';
        }
        unset($backup);
        $this->context->smarty->assign([
            'fs_diagnostic_report' => $report,
            'fs_diagnostic_action' => self::$currentIndex . '&token=' . $token,
            'fs_config_link' => $this->context->link->getAdminLink('AdminFabricSamples'),
            'fs_coupon_link' => $this->context->link->getAdminLink('AdminFabricSamplesCoupons'),
            'fs_shop_context' => Shop::getContext(),
            'fs_shop_id' => $this->contextShopId(),
            'fs_reset_backups' => $backups,
        ]);
        $this->content .= $this->context->smarty->fetch(
            $this->module->getLocalPath() . 'views/templates/admin/diagnostics.tpl'
        );
        $this->context->smarty->assign(['content' => $this->content]);
    }

    private function contextShopId(): int
    {
        return (int) Shop::getContextShopID();
    }

    private function schemaInspector(): SchemaInspector
    {
        return new SchemaInspector($this->module->getLocalPath() . 'sql/install.sql');
    }

    private function diagnosticService(): DiagnosticService
    {
        return new DiagnosticService($this->module, $this->schemaInspector());
    }

    private function repairService(): RepairService
    {
        return new RepairService($this->module, $this->schemaInspector());
    }

    /** @param array{success:bool,message:string,details:array<string,mixed>} $result */
    private function auditDiagnosticResult(string $action, array $result): void
    {
        try {
            (new AuditService(new AuditRepository()))->log(
                'diagnostic_' . $action,
                'module',
                'fabricssamples',
                [],
                ['success' => $result['success'], 'details' => $result['details']],
                $result['message'],
                $this->contextShopId()
            );
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                'fabricssamples diagnostic audit: ' . $exception->getMessage(),
                2,
                null,
                'Module',
                (int) $this->module->id,
                true
            );
            $this->warnings[] = $this->module->l('La operación terminó, pero su resultado no pudo guardarse en Auditoría.');
        }
    }

    private function downloadBackup(): never
    {
        $filename = basename((string) Tools::getValue('downloadFabricSamplesBackup'));
        $path = (new ResetBackupService($this->module))->resolve($filename);
        if ($path === '') {
            http_response_code(404);
            exit;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . (str_ends_with($filename, '.fsb') ? 'application/octet-stream' : 'application/gzip'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function restoreBackup(): void
    {
        $filename = basename((string) Tools::getValue('restoreFabricSamplesBackup'));
        if ((string) Tools::getValue('restore_backup_confirmation') !== 'RESTORE_FABRIC_SAMPLES_BACKUP') {
            $this->errors[] = $this->module->l('No se recibió la confirmación obligatoria para restaurar la copia.');
            return;
        }
        try {
            $result = (new DatabaseLock())->synchronized(
                'maintenance',
                fn (): array => (new RestoreBackupService($this->module))->restore($filename),
                2
            );
            (new AuditService(new AuditRepository()))->log(
                'backup_restore',
                'module',
                'fabricssamples',
                [],
                $result,
                'Restauración cifrada completada.',
                $this->contextShopId()
            );
            $this->confirmations[] = sprintf(
                $this->module->l('Copia restaurada: %d filas y %d archivos. Copia de seguridad previa: %s.'),
                (int) $result['restored_rows'],
                (int) $result['restored_files'],
                (string) $result['safety_backup']
            );
        } catch (Throwable $exception) {
            $reference = bin2hex(random_bytes(6));
            PrestaShopLogger::addLog('fabricssamples restore [' . $reference . ']: ' . $exception->getMessage(), 3);
            $this->errors[] = sprintf(
                $this->module->l('La restauración no pudo completarse. Revise las copias disponibles. Referencia: %s.'),
                $reference
            );
        }
    }
}
