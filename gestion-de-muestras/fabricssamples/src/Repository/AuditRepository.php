<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class AuditRepository
{
    public function insert(array $data): bool
    {
        return (bool) \Db::getInstance()->insert('fabricssamples_audit', [
            'id_shop' => max(0, (int) ($data['id_shop'] ?? 0)),
            'id_employee' => max(0, (int) ($data['id_employee'] ?? 0)),
            'employee_name' => pSQL((string) ($data['employee_name'] ?? '')),
            'action' => pSQL((string) ($data['action'] ?? 'unknown')),
            'entity_type' => pSQL((string) ($data['entity_type'] ?? '')),
            'entity_id' => pSQL((string) ($data['entity_id'] ?? '')),
            'old_value_json' => $this->encodeJson($data['old_value'] ?? []),
            'new_value_json' => $this->encodeJson($data['new_value'] ?? []),
            'note' => pSQL((string) ($data['note'] ?? ''), true),
            'ip_address' => pSQL((string) ($data['ip_address'] ?? '')),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }

    private function encodeJson(mixed $value): string
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return pSQL(is_string($json) ? $json : '{}', true);
    }

    /** @return array{total:int,rows:list<array<string,mixed>>} */
    public function search(int $idShop, string $action, string $search, int $offset, int $limit): array
    {
        $where = $idShop > 0 ? 'a.id_shop=' . $idShop : '1';
        if ($action !== '') {
            $where .= " AND a.action='" . pSQL($action) . "'";
        }
        if ($search !== '') {
            $escaped = pSQL($search);
            $where .= " AND (a.employee_name LIKE '%" . $escaped . "%' OR a.entity_type LIKE '%" . $escaped . "%'"
                . " OR a.entity_id LIKE '%" . $escaped . "%' OR a.note LIKE '%" . $escaped . "%')";
        }
        $total = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'fabricssamples_audit` a WHERE ' . $where
        );
        $rows = \Db::getInstance()->executeS(
            'SELECT a.* FROM `' . _DB_PREFIX_ . 'fabricssamples_audit` a WHERE ' . $where
            . ' ORDER BY a.id_fabricssamples_audit DESC LIMIT ' . max(0, $offset) . ',' . max(1, $limit)
        );
        return ['total' => $total, 'rows' => is_array($rows) ? array_values($rows) : []];
    }

    /** @return list<string> */
    public function actions(int $idShop = 0): array
    {
        $where = $idShop > 0 ? ' WHERE id_shop=' . $idShop : '';
        $rows = \Db::getInstance()->executeS(
            'SELECT DISTINCT action FROM `' . _DB_PREFIX_ . 'fabricssamples_audit`' . $where . ' ORDER BY action ASC'
        );
        return array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['action'] ?? ''), is_array($rows) ? $rows : [])));
    }

    /** @param list<int> $ids */
    public function deleteByIds(array $ids, int $idShop = 0): int
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, 5000);
        if ($ids === []) {
            return 0;
        }

        $where = 'id_fabricssamples_audit IN (' . implode(',', $ids) . ')';
        if ($idShop > 0) {
            $where .= ' AND id_shop=' . $idShop;
        }
        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'fabricssamples_audit` WHERE ' . $where
        );
        if ($count <= 0) {
            return 0;
        }
        if (!\Db::getInstance()->delete('fabricssamples_audit', $where)) {
            throw new \RuntimeException('No se pudieron eliminar los registros de auditoría seleccionados.');
        }

        return $count;
    }
}
