<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class StockMovementRepository
{
    private const NATIVE_COMPENSATION_TYPE = 'native_stock_compensation';

    public function existsByReference(string $reference): bool
    {
        if ($reference === '') {
            return false;
        }

        return (bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement`'
            . " WHERE movement_reference='" . pSQL($reference) . "'"
        );
    }

    public function netDeltaForHistory(int $idHistory): int
    {
        if ($idHistory <= 0) {
            return 0;
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(sm.quantity_delta),0)'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement` sm'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'fabricssamples_order` fso'
            . ' ON fso.id_fabricssamples_order=' . $idHistory
            . ' WHERE (sm.id_fabricssamples_order=' . $idHistory
            . ' OR (fso.id_order_detail>0 AND sm.id_order_detail=fso.id_order_detail))'
            . " AND sm.movement_type<>'" . self::NATIVE_COMPENSATION_TYPE . "'"
        );
    }

    public function netDeltaForOrderDetail(int $idOrderDetail): int
    {
        if ($idOrderDetail <= 0) {
            return 0;
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(quantity_delta),0) FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement`'
            . ' WHERE id_order_detail=' . $idOrderDetail
            . " AND movement_type<>'" . self::NATIVE_COMPENSATION_TYPE . "'"
        );
    }

    public function nativeCompensationForHistory(int $idHistory): int
    {
        if ($idHistory <= 0) {
            return 0;
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(quantity_delta),0) FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement`'
            . ' WHERE id_fabricssamples_order=' . $idHistory
            . " AND movement_type='" . self::NATIVE_COMPENSATION_TYPE . "'"
        );
    }

    public function nativeCompensationForOrderDetail(int $idOrderDetail): int
    {
        if ($idOrderDetail <= 0) {
            return 0;
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(quantity_delta),0) FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement`'
            . ' WHERE id_order_detail=' . $idOrderDetail
            . " AND movement_type='" . self::NATIVE_COMPENSATION_TYPE . "'"
        );
    }

    public function insert(array $data): bool
    {
        // Movement persistence must be side-effect free. Native product compensation and
        // independent sample consumption are separate transactions coordinated by
        // StockLifecycleService, so one cannot silently roll back or mask the other.
        return \Db::getInstance()->insert('fabricssamples_stock_movement', $data, true);
    }

    /** @return list<array<string,mixed>> */
    public function findByOrder(int $idOrder): array
    {
        if ($idOrder <= 0) {
            return [];
        }
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_stock_movement`'
            . ' WHERE id_order=' . $idOrder
            . ' ORDER BY id_fabricssamples_stock_movement ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    public function deleteByOrder(int $idOrder): bool
    {
        return $idOrder <= 0
            || \Db::getInstance()->delete('fabricssamples_stock_movement', 'id_order=' . $idOrder);
    }
}
