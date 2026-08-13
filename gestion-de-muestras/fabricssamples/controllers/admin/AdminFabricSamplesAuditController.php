<?php

require_once dirname(__DIR__, 2) . '/config/autoload.php';

use NaranjaCreativos\FabricSamples\Repository\AuditRepository;
use NaranjaCreativos\FabricSamples\Presentation\PaginationWindow;
use NaranjaCreativos\FabricSamples\Security\AdminControllerSecurityTrait;
use NaranjaCreativos\FabricSamples\Security\CsvSafeWriter;

class AdminFabricSamplesAuditController extends ModuleAdminController
{
    use AdminControllerSecurityTrait;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('Auditoría del módulo');
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/operations.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin-audit.js');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('deleteFabricSamplesAudit')) {
            if ($this->guardAdminAction('delete')) {
                $this->deleteAuditRows([(int) Tools::getValue('deleteFabricSamplesAudit')]);
            }
        }
        if (Tools::isSubmit('bulkDeleteFabricSamplesAudit')) {
            $ids = Tools::getValue('auditBox', []);
            if ($this->guardAdminAction('delete')) {
                $this->deleteAuditRows(is_array($ids) ? array_map('intval', $ids) : []);
            }
        }
        if (Tools::isSubmit('exportFabricSamplesAudit')) {
            if ($this->guardAdminAction('view', false)) {
                $this->exportAudit();
            }
        }
        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();
        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = min(200, max(20, (int) Tools::getValue('limit', 50)));
        $action = trim((string) Tools::getValue('audit_action'));
        $search = trim((string) Tools::getValue('audit_search'));
        $repository = new AuditRepository();
        $idShop = (int) Shop::getContextShopID();
        $result = $repository->search($idShop, $action, $search, ($page - 1) * $limit, $limit);
        $this->context->smarty->assign([
            'fs_audit_rows' => $result['rows'],
            'fs_audit_actions' => $repository->actions($idShop),
            'fs_audit_total' => $result['total'],
            'fs_audit_page' => $page,
            'fs_audit_pages' => max(1, (int) ceil($result['total'] / $limit)),
            'fs_audit_pagination_window' => PaginationWindow::build(
                $page,
                max(1, (int) ceil($result['total'] / $limit))
            ),
            'fs_audit_limit' => $limit,
            'fs_audit_action_filter' => $action,
            'fs_audit_search' => $search,
            'fs_audit_url' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite($this->controller_name),
        ]);
        $this->content .= $this->context->smarty->fetch(
            $this->module->getLocalPath() . 'views/templates/admin/audit.tpl'
        );
        $this->context->smarty->assign(['content' => $this->content]);
    }

    private function exportAudit(): never
    {
        $action = trim((string) Tools::getValue('audit_action'));
        $search = trim((string) Tools::getValue('audit_search'));
        $rows = (new AuditRepository())->search((int) Shop::getContextShopID(), $action, $search, 0, 5000)['rows'];
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="fabricssamples-audit-' . date('Ymd-His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        CsvSafeWriter::write($out, ['ID', 'Fecha', 'Tienda', 'Empleado ID', 'Empleado', 'Acción', 'Entidad', 'Entidad ID', 'Valor anterior', 'Valor nuevo', 'Nota', 'IP']);
        foreach ($rows as $row) {
            CsvSafeWriter::write($out, [
                $row['id_fabricssamples_audit'] ?? '', $row['date_add'] ?? '', $row['id_shop'] ?? '',
                $row['id_employee'] ?? '', $row['employee_name'] ?? '', $row['action'] ?? '',
                $row['entity_type'] ?? '', $row['entity_id'] ?? '', $row['old_value_json'] ?? '',
                $row['new_value_json'] ?? '', $row['note'] ?? '', $row['ip_address'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    /** @param list<int> $ids */
    private function deleteAuditRows(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            $this->errors[] = $this->module->l('Seleccione al menos un registro de auditoría.');
            return;
        }

        try {
            $deleted = (new AuditRepository())->deleteByIds($ids, (int) Shop::getContextShopID());
            if ($deleted <= 0) {
                $this->errors[] = $this->module->l('No se encontró ningún registro eliminable en el contexto actual.');
                return;
            }
            PrestaShopLogger::addLog(
                sprintf(
                    'fabricssamples audit cleanup: employee=%d deleted=%d ids=%s',
                    (int) ($this->context->employee->id ?? 0),
                    $deleted,
                    implode(',', $ids)
                ),
                1,
                null,
                'Module',
                (int) $this->module->id,
                true
            );
            $this->confirmations[] = sprintf(
                $this->module->l('%d registro(s) de auditoría eliminado(s).'),
                $deleted
            );
        } catch (Throwable $exception) {
            $reference = bin2hex(random_bytes(6));
            PrestaShopLogger::addLog('fabricssamples audit [' . $reference . ']: ' . $exception->getMessage(), 3);
            $this->errors[] = sprintf($this->module->l('No se pudo completar la limpieza. Referencia: %s.'), $reference);
        }
    }
}
