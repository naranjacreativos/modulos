<?php

require_once dirname(__DIR__, 2) . '/config/autoload.php';

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Domain\LimitExceptionPolicy;
use NaranjaCreativos\FabricSamples\Repository\AuditRepository;
use NaranjaCreativos\FabricSamples\Repository\CustomerLimitRepository;
use NaranjaCreativos\FabricSamples\Repository\LimitExceptionRepository;
use NaranjaCreativos\FabricSamples\Service\AuditService;
use NaranjaCreativos\FabricSamples\Security\AdminControllerSecurityTrait;
use NaranjaCreativos\FabricSamples\Security\CsvSafeWriter;

class AdminFabricSamplesLimitsController extends ModuleAdminController
{
    use AdminControllerSecurityTrait;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('Límites y excepciones');
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/operations.css');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('exportFabricSamplesLimitEvents')) {
            if ($this->guardAdminAction('view', false)) {
                $this->exportEvents();
            }
        }
        if (Tools::isSubmit('submitFabricSamplesLimitException')) {
            if ($this->guardAdminAction('edit')) {
                $this->saveException();
            }
        }
        if (Tools::isSubmit('delete_limit_exception')) {
            if ($this->guardAdminAction('delete')) {
                $this->deleteException();
            }
        }
        if (Tools::isSubmit('submitFabricSamplesLimitReset')) {
            if ($this->guardAdminAction('delete')) {
                $this->resetCustomer();
            }
        }
        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();
        $idShop = (int) $this->context->shop->id;
        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = 50;
        $search = trim((string) Tools::getValue('limit_search'));
        $repository = $this->repository();
        $total = $repository->count($idShop, $search);
        $edit = $repository->findById((int) Tools::getValue('edit_exception'));
        if ($edit !== [] && (int) $edit['id_shop'] !== $idShop) {
            $edit = [];
        }
        $usageCustomerId = max(0, (int) Tools::getValue('usage_customer_id'));
        $usage = $usageCustomerId > 0 ? $this->buildCustomerUsage($usageCustomerId, $idShop) : [];

        $this->context->smarty->assign([
            'fs_limit_exceptions' => $repository->list($idShop, ($page - 1) * $limit, $limit, $search),
            'fs_limit_events' => $repository->recentEvents($idShop, 100),
            'fs_limit_total' => $total,
            'fs_limit_page' => $page,
            'fs_limit_pages' => max(1, (int) ceil($total / $limit)),
            'fs_limit_search' => $search,
            'fs_limit_action' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite($this->controller_name),
            'fs_limit_edit' => $edit,
            'fs_customer_link' => $this->context->link->getAdminLink('AdminCustomers'),
            'fs_group_link' => $this->context->link->getAdminLink('AdminGroups'),
            'fs_limit_usage_customer_id' => $usageCustomerId,
            'fs_limit_usage' => $usage,
        ]);
        $this->content .= $this->context->smarty->fetch(
            $this->module->getLocalPath() . 'views/templates/admin/limits.tpl'
        );
        $this->context->smarty->assign(['content' => $this->content]);
    }

    private function saveException(): void
    {
        $targetType = (string) Tools::getValue('target_type');
        $targetId = (int) Tools::getValue('target_id');
        $mode = (string) Tools::getValue('exception_mode');
        if (!in_array($targetType, ['customer', 'group'], true) || $targetId <= 0) {
            $this->errors[] = $this->module->l('Indique un cliente o grupo válido.');
            return;
        }
        if (!in_array($mode, ['exempt', 'custom'], true)) {
            $this->errors[] = $this->module->l('Modo de excepción no válido.');
            return;
        }
        $entity = $targetType === 'customer' ? new Customer($targetId) : new Group($targetId);
        if (!Validate::isLoadedObject($entity)) {
            $this->errors[] = $this->module->l('El cliente o grupo indicado no existe.');
            return;
        }

        $repository = $this->repository();
        $rules = $repository->findRules($targetType === 'customer' ? $targetId : 0, (int) $this->context->shop->id, $targetType === 'group' ? [$targetId] : []);
        $old = $targetType === 'customer' ? $rules['customer'] : ($rules['groups'][0] ?? []);
        $data = [
            'id_shop' => (int) $this->context->shop->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'mode' => $mode,
            'max_total_period' => max(0, (int) Tools::getValue('max_total_period')),
            'max_product_period' => max(0, (int) Tools::getValue('max_product_period')),
            'period_days' => min(3650, max(0, (int) Tools::getValue('period_days'))),
            'active' => (int) Tools::getValue('exception_active', 0),
            'note' => trim((string) Tools::getValue('exception_note')),
            'id_employee' => (int) ($this->context->employee->id ?? 0),
        ];
        if (!$repository->upsert($data)) {
            $this->errors[] = $this->module->l('No se pudo guardar la excepción.');
            return;
        }
        $repository->logEvent([
            'id_shop' => (int) $this->context->shop->id,
            'id_customer' => $targetType === 'customer' ? $targetId : 0,
            'event_type' => 'exception_saved',
            'source_type' => $targetType,
            'source_id' => $targetId,
            'message' => 'Excepción de límites guardada.',
            'id_employee' => (int) ($this->context->employee->id ?? 0),
            'metadata' => $data,
        ]);
        $this->auditService()->log('limit_exception_update', $targetType, $targetId, $old, $data, $data['note'], (int) $this->context->shop->id);
        $this->confirmations[] = $this->module->l('Excepción guardada correctamente.');
    }

    private function deleteException(): void
    {
        $id = (int) Tools::getValue('delete_limit_exception');
        $old = $this->repository()->findById($id);
        if ($old === [] || (int) $old['id_shop'] !== (int) $this->context->shop->id) {
            $this->errors[] = $this->module->l('La excepción no existe.');
            return;
        }
        if (!$this->repository()->delete($id, (int) $this->context->shop->id)) {
            $this->errors[] = $this->module->l('No se pudo eliminar la excepción.');
            return;
        }
        $this->auditService()->log('limit_exception_delete', (string) $old['target_type'], (int) $old['target_id'], $old, [], 'Excepción eliminada.', (int) $this->context->shop->id);
        $this->confirmations[] = $this->module->l('Excepción eliminada.');
    }

    private function resetCustomer(): void
    {
        $idCustomer = (int) Tools::getValue('reset_customer_id');
        $note = trim((string) Tools::getValue('reset_note'));
        $customer = new Customer($idCustomer);
        if (!Validate::isLoadedObject($customer)) {
            $this->errors[] = $this->module->l('Cliente no válido.');
            return;
        }
        if ($note === '') {
            $this->errors[] = $this->module->l('Debe indicar el motivo del reinicio.');
            return;
        }
        if (!$this->repository()->resetCustomer($idCustomer, (int) $this->context->shop->id, (int) ($this->context->employee->id ?? 0), $note)) {
            $this->errors[] = $this->module->l('No se pudo reiniciar el historial del cliente.');
            return;
        }
        $this->repository()->logEvent([
            'id_shop' => (int) $this->context->shop->id,
            'id_customer' => $idCustomer,
            'event_type' => 'manual_reset',
            'message' => 'Historial de límites reiniciado.',
            'id_employee' => (int) ($this->context->employee->id ?? 0),
            'metadata' => ['note' => $note],
        ]);
        $this->auditService()->log('limit_history_reset', 'customer', $idCustomer, [], ['reset_at' => date('Y-m-d H:i:s')], $note, (int) $this->context->shop->id);
        $this->confirmations[] = $this->module->l('El contador histórico del cliente se ha reiniciado desde este momento.');
    }

    /** @return array<string,mixed> */
    private function buildCustomerUsage(int $idCustomer, int $idShop): array
    {
        $customer = new Customer($idCustomer);
        if (!Validate::isLoadedObject($customer)) {
            $this->warnings[] = $this->module->l('No se encontró el cliente indicado para consultar su consumo.');
            return [];
        }

        $configuration = new ModuleConfiguration();
        $groups = array_map('intval', Customer::getGroupsStatic($idCustomer));
        $rules = $this->repository()->findRules($idCustomer, $idShop, $groups);
        $resolution = (new LimitExceptionPolicy())->resolve(
            $rules['customer'],
            $rules['groups'],
            [
                'max_total' => $configuration->getInt('MAX_CUSTOMER_TOTAL_PERIOD'),
                'max_product' => $configuration->getInt('MAX_CUSTOMER_PRODUCT_PERIOD'),
                'period_days' => max(1, $configuration->getInt('CUSTOMER_PERIOD_DAYS', 30)),
            ]
        );
        $dateFrom = (new DateTimeImmutable('now'))
            ->modify('-' . max(1, (int) $resolution['period_days']) . ' days')
            ->format('Y-m-d H:i:s');
        $resetDate = $this->repository()->latestResetDate($idCustomer, $idShop);
        $states = $this->countedOrderStateIds();
        $usageRepository = new CustomerLimitRepository();
        $total = $usageRepository->quantitySince($idCustomer, $idShop, $dateFrom, null, $states, $resetDate);
        $products = $usageRepository->productBreakdownSince($idCustomer, $idShop, $dateFrom, $states, $resetDate, 100);

        return [
            'id_customer' => $idCustomer,
            'customer_name' => trim((string) $customer->firstname . ' ' . (string) $customer->lastname),
            'email' => (string) $customer->email,
            'date_from' => $dateFrom,
            'reset_date' => $resetDate,
            'states' => $this->orderStateLabels($states),
            'total' => $total,
            'products' => $products,
            'resolution' => $resolution,
        ];
    }

    /** @return list<int> */
    private function countedOrderStateIds(): array
    {
        $raw = trim((string) Configuration::get('FABRICS_SAMPLES_LIMIT_ORDER_STATES'));
        if ($raw === 'none') {
            return [-1];
        }
        $states = array_values(array_unique(array_filter(array_map(
            'intval',
            preg_split('/[^0-9]+/', $raw) ?: []
        ))));
        if ($states !== []) {
            return $states;
        }

        return array_values(array_unique(array_filter(array_map('intval', [
            Configuration::get('PS_OS_PAYMENT'),
            Configuration::get('PS_OS_PREPARATION'),
            Configuration::get('PS_OS_SHIPPING'),
            Configuration::get('PS_OS_DELIVERED'),
        ]))));
    }

    /** @param list<int> $stateIds @return list<string> */
    private function orderStateLabels(array $stateIds): array
    {
        if ($stateIds === [-1]) {
            return [$this->module->l('Ningún estado seleccionado')];
        }
        $wanted = array_fill_keys($stateIds, true);
        $labels = [];
        foreach (OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            $id = (int) ($state['id_order_state'] ?? 0);
            if (isset($wanted[$id])) {
                $labels[] = (string) ($state['name'] ?? ('#' . $id)) . ' (#' . $id . ')';
            }
        }
        return $labels;
    }

    private function exportEvents(): never
    {
        $rows = $this->repository()->recentEvents((int) $this->context->shop->id, 5000);
        $this->startCsv('fabricssamples-limits-' . date('Ymd-His') . '.csv');
        $out = fopen('php://output', 'wb');
        CsvSafeWriter::write($out, ['ID', 'Fecha', 'Tipo', 'Código', 'Cliente ID', 'Cliente', 'Email', 'Producto ID', 'Producto', 'Límite', 'Valor observado', 'Origen', 'Origen ID', 'Mensaje']);
        foreach ($rows as $row) {
            CsvSafeWriter::write($out, [
                $row['id_fabricssamples_limit_event'] ?? '',
                $row['date_add'] ?? '',
                $row['event_type'] ?? '',
                $row['limit_code'] ?? '',
                $row['id_customer'] ?? '',
                trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['lastname'] ?? '')),
                $row['email'] ?? '',
                $row['id_product'] ?? '',
                $row['product_name'] ?? '',
                $row['limit_value'] ?? '',
                $row['observed_value'] ?? '',
                $row['source_type'] ?? '',
                $row['source_id'] ?? '',
                $row['message'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    private function startCsv(string $filename): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
    }

    private function repository(): LimitExceptionRepository
    {
        return new LimitExceptionRepository();
    }

    private function auditService(): AuditService
    {
        return new AuditService(new AuditRepository());
    }
}
