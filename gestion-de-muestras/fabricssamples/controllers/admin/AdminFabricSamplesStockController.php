<?php

require_once dirname(__DIR__, 2) . '/config/autoload.php';

use NaranjaCreativos\FabricSamples\Repository\AuditRepository;
use NaranjaCreativos\FabricSamples\Repository\StockAdminRepository;
use NaranjaCreativos\FabricSamples\Repository\StockMovementRepository;
use NaranjaCreativos\FabricSamples\Service\AuditService;
use NaranjaCreativos\FabricSamples\Service\StockAdjustmentService;
use NaranjaCreativos\FabricSamples\Security\AdminControllerSecurityTrait;
use NaranjaCreativos\FabricSamples\Security\CsvSafeWriter;

class AdminFabricSamplesStockController extends ModuleAdminController
{
    use AdminControllerSecurityTrait;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('Stock de muestras');
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/operations.css');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('exportFabricSamplesStock')) {
            if ($this->guardAdminAction('view', false)) {
                $this->exportCurrentStock();
            }
        }
        if (Tools::isSubmit('exportFabricSamplesStockMovements')) {
            if ($this->guardAdminAction('view', false)) {
                $this->exportMovements();
            }
        }
        if (Tools::isSubmit('submitFabricSamplesStockAdjustment')) {
            if ($this->guardAdminAction('edit')) {
                $this->processAdjustment();
            }
        }
        if (Tools::isSubmit('submitFabricSamplesBulkStockAdjustment')) {
            if ($this->guardAdminAction('edit')) {
                $this->processBulkAdjustment();
            }
        }
        parent::postProcess();
    }

    public function initContent()
    {
        $this->consumeStockFlash();
        parent::initContent();
        $idShop = (int) $this->context->shop->id;
        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = min(200, max(20, (int) Tools::getValue('limit', 50)));
        $search = trim((string) Tools::getValue('stock_search'));
        $filter = (string) Tools::getValue('stock_filter');
        if (!in_array($filter, ['', 'low', 'out'], true)) {
            $filter = '';
        }
        $result = $this->repository()->search(
            $idShop,
            (int) $this->context->language->id,
            $search,
            $filter,
            ($page - 1) * $limit,
            $limit,
            max(0, (int) Configuration::get('FABRICS_SAMPLES_LOW_STOCK_THRESHOLD'))
        );
        $this->context->smarty->assign([
            'fs_stock_rows' => $result['rows'],
            'fs_stock_total' => $result['total'],
            'fs_stock_page' => $page,
            'fs_stock_pages' => max(1, (int) ceil($result['total'] / $limit)),
            'fs_stock_limit' => $limit,
            'fs_stock_search' => $search,
            'fs_stock_filter' => $filter,
            'fs_stock_movements' => $this->repository()->movements($idShop, 100),
            'fs_stock_action' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite($this->controller_name),
            'fs_low_threshold' => max(0, (int) Configuration::get('FABRICS_SAMPLES_LOW_STOCK_THRESHOLD')),
        ]);
        $this->content .= $this->context->smarty->fetch(
            $this->module->getLocalPath() . 'views/templates/admin/stock.tpl'
        );
        $this->context->smarty->assign(['content' => $this->content]);
    }

    private function processAdjustment(): void
    {
        $idProduct = (int) Tools::getValue('submitFabricSamplesStockAdjustment');
        $deltas = (array) Tools::getValue('stock_delta', []);
        $reasons = (array) Tools::getValue('stock_reason', []);
        $delta = (int) ($deltas[$idProduct] ?? 0);
        $reason = trim((string) ($reasons[$idProduct] ?? ''));
        $result = $this->adjustmentService()->adjust($idProduct, (int) $this->context->shop->id, $delta, $reason);
        if ($result['success']) {
            $this->storeStockFlash([sprintf(
                $this->module->l('Stock actualizado: %d → %d.'),
                $result['before'],
                $result['after']
            )]);
            $this->redirectAfterStockMutation();
        }

        $this->errors[] = $result['message'];
    }

    private function processBulkAdjustment(): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) Tools::getValue('stockBox', [])))));
        $delta = (int) Tools::getValue('bulk_stock_delta');
        $reason = trim((string) Tools::getValue('bulk_stock_reason'));
        if ($ids === []) {
            $this->errors[] = $this->module->l('Seleccione al menos un producto.');
            return;
        }
        $success = 0;
        $errors = [];
        foreach ($ids as $idProduct) {
            $result = $this->adjustmentService()->adjust($idProduct, (int) $this->context->shop->id, $delta, $reason);
            if ($result['success']) {
                ++$success;
            } else {
                $errors[] = '#' . $idProduct . ': ' . $result['message'];
            }
        }
        if ($success > 0) {
            $confirmations = [sprintf($this->module->l('Ajuste aplicado a %d producto(s).'), $success)];
            $this->storeStockFlash($confirmations, $errors !== [] ? [implode(' | ', $errors)] : []);
            $this->redirectAfterStockMutation();
        }
        if ($errors !== []) {
            $this->errors[] = implode(' | ', $errors);
        }
    }

    /** @param list<string> $confirmations @param list<string> $errors */
    private function storeStockFlash(array $confirmations, array $errors = []): void
    {
        $payload = json_encode([
            'confirmations' => array_values($confirmations),
            'errors' => array_values($errors),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return;
        }
        $this->context->cookie->fabricssamples_stock_flash = base64_encode($payload);
        $this->context->cookie->write();
    }

    private function consumeStockFlash(): void
    {
        $raw = (string) ($this->context->cookie->fabricssamples_stock_flash ?? '');
        if ($raw === '') {
            return;
        }
        unset($this->context->cookie->fabricssamples_stock_flash);
        $this->context->cookie->write();

        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            return;
        }
        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            return;
        }
        foreach ((array) ($payload['confirmations'] ?? []) as $message) {
            if (is_string($message) && $message !== '') {
                $this->confirmations[] = $message;
            }
        }
        foreach ((array) ($payload['errors'] ?? []) as $message) {
            if (is_string($message) && $message !== '') {
                $this->errors[] = $message;
            }
        }
    }

    private function redirectAfterStockMutation(): never
    {
        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = min(200, max(20, (int) Tools::getValue('limit', 50)));
        $search = trim((string) Tools::getValue('stock_search'));
        $filter = (string) Tools::getValue('stock_filter');
        if (!in_array($filter, ['', 'low', 'out'], true)) {
            $filter = '';
        }

        $url = self::$currentIndex
            . '&token=' . Tools::getAdminTokenLite($this->controller_name)
            . '&page=' . $page
            . '&limit=' . $limit
            . '&stock_search=' . rawurlencode($search)
            . '&stock_filter=' . rawurlencode($filter);
        Tools::redirectAdmin($url);
        exit;
    }

    private function exportCurrentStock(): never
    {
        $idShop = (int) $this->context->shop->id;
        $rows = $this->repository()->allStock(
            $idShop,
            (int) $this->context->language->id,
            max(0, (int) Configuration::get('FABRICS_SAMPLES_LOW_STOCK_THRESHOLD'))
        );
        $this->startCsv('fabricssamples-stock-actual-' . date('Ymd-His') . '.csv');
        $out = fopen('php://output', 'wb');
        CsvSafeWriter::write($out, ['Producto ID', 'Producto', 'Referencia', 'Existencias', 'Reservado en carritos', 'Disponible tras reservas', 'Consumido bruto', 'Restaurado', 'Consumo neto', 'Activo', 'Disponible']);
        foreach ($rows as $row) {
            CsvSafeWriter::write($out, [
                $row['id_product'] ?? '',
                $row['name'] ?? '',
                $row['reference'] ?? '',
                $row['sample_stock'] ?? '',
                $row['reserved'] ?? '',
                $row['available_after_reservations'] ?? '',
                $row['consumed'] ?? '',
                $row['restored'] ?? '',
                $row['net_consumed'] ?? '',
                $row['active'] ?? '',
                $row['available'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    private function exportMovements(): never
    {
        $rows = $this->repository()->movements((int) $this->context->shop->id, 5000);
        $this->startCsv('fabricssamples-stock-' . date('Ymd-His') . '.csv');
        $out = fopen('php://output', 'wb');
        CsvSafeWriter::write($out, ['ID', 'Fecha', 'Producto ID', 'Producto', 'Referencia', 'Tipo', 'Variación', 'Antes', 'Después', 'Pedido', 'Empleado', 'Motivo']);
        foreach ($rows as $row) {
            CsvSafeWriter::write($out, [
                $row['id_fabricssamples_stock_movement'] ?? '',
                $row['date_add'] ?? '',
                $row['id_product'] ?? '',
                $row['product_name'] ?? '',
                $row['reference'] ?? '',
                $row['movement_type'] ?? '',
                $row['quantity_delta'] ?? '',
                $row['quantity_before'] ?? '',
                $row['quantity_after'] ?? '',
                $row['id_order'] ?? '',
                trim((string) ($row['employee_firstname'] ?? '') . ' ' . (string) ($row['employee_lastname'] ?? '')),
                $row['note'] ?? '',
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

    private function repository(): StockAdminRepository
    {
        return new StockAdminRepository();
    }

    private function adjustmentService(): StockAdjustmentService
    {
        return new StockAdjustmentService(
            $this->repository(),
            new StockMovementRepository(),
            new AuditService(new AuditRepository())
        );
    }
}
