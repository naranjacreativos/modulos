<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Domain\StockAdjustmentPolicy;
use NaranjaCreativos\FabricSamples\Repository\StockAdminRepository;
use NaranjaCreativos\FabricSamples\Repository\StockMovementRepository;

final class StockAdjustmentService
{
    public function __construct(
        private StockAdminRepository $stockRepository,
        private StockMovementRepository $movementRepository,
        private AuditService $auditService,
        private StockAdjustmentPolicy $adjustmentPolicy = new StockAdjustmentPolicy()
    ) {
    }

    /** @return array{success:bool,message:string,before:int,after:int} */
    public function adjust(int $idProduct, int $idShop, int $delta, string $reason): array
    {
        if (trim($reason) === '') {
            return ['success' => false, 'message' => 'Debe indicar el motivo del ajuste.', 'before' => 0, 'after' => 0];
        }

        $db = \Db::getInstance();
        $db->execute('START TRANSACTION');
        try {
            $rows = $db->executeS(
                'SELECT id_fabricssamples_product,sample_stock,stock_mode FROM `' . _DB_PREFIX_ . 'fabricssamples_product`'
                . ' WHERE id_product=' . $idProduct . ' AND id_shop=' . $idShop . ' FOR UPDATE',
                true,
                false
            );
            $row = is_array($rows) && isset($rows[0]) ? $rows[0] : [];
            if ($row === [] || (string) ($row['stock_mode'] ?? '') !== 'independent') {
                throw new \RuntimeException('El producto no utiliza stock independiente de muestras.');
            }
            $before = (int) $row['sample_stock'];
            $after = $this->adjustmentPolicy->targetStock($before, $delta);
            if (!$db->update('fabricssamples_product', [
                'sample_stock' => $after,
                'date_upd' => date('Y-m-d H:i:s'),
            ], 'id_fabricssamples_product=' . (int) $row['id_fabricssamples_product'])) {
                throw new \RuntimeException('No se pudo actualizar el stock.');
            }
            $employeeId = (int) (\Context::getContext()->employee->id ?? 0);
            $reference = 'manual:' . $idShop . ':' . $idProduct . ':' . date('YmdHis') . ':' . bin2hex(random_bytes(4));
            if (!$this->movementRepository->insert([
                'id_product' => $idProduct,
                'id_shop' => $idShop,
                'id_order' => 0,
                'id_order_detail' => 0,
                'id_fabricssamples_order' => 0,
                'movement_type' => 'manual_adjustment',
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'movement_reference' => pSQL($reference),
                'id_employee' => $employeeId,
                'note' => pSQL($reason, true),
                'date_add' => date('Y-m-d H:i:s'),
            ])) {
                throw new \RuntimeException('No se pudo registrar el movimiento.');
            }
            $db->execute('COMMIT');
            $this->auditService->log('stock_adjustment', 'product', $idProduct, ['stock' => $before], ['stock' => $after, 'delta' => $delta], $reason, $idShop);
            return ['success' => true, 'message' => 'Stock actualizado correctamente.', 'before' => $before, 'after' => $after];
        } catch (\Throwable $exception) {
            $db->execute('ROLLBACK');
            return ['success' => false, 'message' => $exception->getMessage(), 'before' => 0, 'after' => 0];
        }
    }
}
