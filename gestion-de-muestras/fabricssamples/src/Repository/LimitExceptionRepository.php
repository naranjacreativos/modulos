<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Repository;

final class LimitExceptionRepository
{
    /** @return array{customer:array<string,mixed>,groups:list<array<string,mixed>>} */
    public function findRules(int $idCustomer, int $idShop, array $groupIds): array
    {
        $customer = [];
        if ($idCustomer > 0) {
            $rows = \Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_exception`'
                . ' WHERE id_shop=' . $idShop . " AND target_type='customer' AND target_id=" . $idCustomer
                . ' ORDER BY id_fabricssamples_limit_exception DESC'
            );
            $customer = is_array($rows) && isset($rows[0]) ? $rows[0] : [];
        }

        $groups = [];
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
        if ($groupIds !== []) {
            $rows = \Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_exception`'
                . ' WHERE id_shop=' . $idShop . " AND target_type='group'"
                . ' AND target_id IN (' . implode(',', $groupIds) . ')'
                . ' ORDER BY mode DESC,id_fabricssamples_limit_exception ASC'
            );
            $groups = is_array($rows) ? array_values($rows) : [];
        }

        return ['customer' => $customer, 'groups' => $groups];
    }

    /** @return list<array<string,mixed>> */
    public function list(int $idShop, int $offset, int $limit, string $search = ''): array
    {
        $where = 'le.id_shop=' . $idShop;
        if ($search !== '') {
            $escaped = pSQL($search);
            $where .= " AND (le.target_id=" . (int) $search
                . " OR c.firstname LIKE '%" . $escaped . "%' OR c.lastname LIKE '%" . $escaped . "%'"
                . " OR c.email LIKE '%" . $escaped . "%' OR gl.name LIKE '%" . $escaped . "%')";
        }
        $rows = \Db::getInstance()->executeS(
            'SELECT le.*,c.firstname,c.lastname,c.email,gl.name group_name,e.firstname employee_firstname,e.lastname employee_lastname'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_exception` le'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON le.target_type=\'customer\' AND c.id_customer=le.target_id'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'group_lang` gl ON le.target_type=\'group\' AND gl.id_group=le.target_id'
            . ' AND gl.id_lang=' . (int) \Context::getContext()->language->id
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.id_employee=le.id_employee'
            . ' WHERE ' . $where
            . ' ORDER BY le.date_upd DESC LIMIT ' . max(0, $offset) . ',' . max(1, $limit)
        );
        return is_array($rows) ? array_values($rows) : [];
    }

    public function count(int $idShop, string $search = ''): int
    {
        $where = 'le.id_shop=' . $idShop;
        if ($search !== '') {
            $escaped = pSQL($search);
            $where .= " AND (le.target_id=" . (int) $search
                . " OR c.firstname LIKE '%" . $escaped . "%' OR c.lastname LIKE '%" . $escaped . "%'"
                . " OR c.email LIKE '%" . $escaped . "%' OR gl.name LIKE '%" . $escaped . "%')";
        }
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_exception` le'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON le.target_type=\'customer\' AND c.id_customer=le.target_id'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'group_lang` gl ON le.target_type=\'group\' AND gl.id_group=le.target_id'
            . ' AND gl.id_lang=' . (int) \Context::getContext()->language->id
            . ' WHERE ' . $where
        );
    }

    public function findById(int $id): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_exception`'
            . ' WHERE id_fabricssamples_limit_exception=' . $id
        );
        return is_array($rows) && isset($rows[0]) ? $rows[0] : [];
    }

    public function upsert(array $data): bool
    {
        $idShop = (int) $data['id_shop'];
        $targetType = (string) $data['target_type'];
        $targetId = (int) $data['target_id'];
        $rows = \Db::getInstance()->executeS(
            'SELECT id_fabricssamples_limit_exception FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_exception`'
            . ' WHERE id_shop=' . $idShop . " AND target_type='" . pSQL($targetType) . "' AND target_id=" . $targetId
        );
        $id = (int) ($rows[0]['id_fabricssamples_limit_exception'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $payload = [
            'id_shop' => $idShop,
            'target_type' => pSQL($targetType),
            'target_id' => $targetId,
            'mode' => pSQL((string) $data['mode']),
            'max_total_period' => max(0, (int) $data['max_total_period']),
            'max_product_period' => max(0, (int) $data['max_product_period']),
            'period_days' => max(0, (int) $data['period_days']),
            'active' => (int) !empty($data['active']),
            'note' => pSQL((string) ($data['note'] ?? ''), true),
            'id_employee' => max(0, (int) ($data['id_employee'] ?? 0)),
            'date_upd' => $now,
        ];
        if ($id > 0) {
            return (bool) \Db::getInstance()->update('fabricssamples_limit_exception', $payload, 'id_fabricssamples_limit_exception=' . $id);
        }
        $payload['date_add'] = $now;
        return (bool) \Db::getInstance()->insert('fabricssamples_limit_exception', $payload);
    }

    public function delete(int $id, int $idShop): bool
    {
        return (bool) \Db::getInstance()->delete(
            'fabricssamples_limit_exception',
            'id_fabricssamples_limit_exception=' . $id . ' AND id_shop=' . $idShop
        );
    }

    public function latestResetDate(int $idCustomer, int $idShop): ?string
    {
        if ($idCustomer <= 0) {
            return null;
        }
        $value = \Db::getInstance()->getValue(
            'SELECT MAX(reset_at) FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_reset`'
            . ' WHERE id_shop=' . $idShop . ' AND id_customer=' . $idCustomer
        );
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function resetCustomer(int $idCustomer, int $idShop, int $idEmployee, string $note): bool
    {
        $now = date('Y-m-d H:i:s');
        return (bool) \Db::getInstance()->insert('fabricssamples_limit_reset', [
            'id_shop' => $idShop,
            'id_customer' => $idCustomer,
            'reset_at' => $now,
            'id_employee' => $idEmployee,
            'note' => pSQL($note, true),
            'date_add' => $now,
        ]);
    }

    public function logEvent(array $data): bool
    {
        return (bool) \Db::getInstance()->insert('fabricssamples_limit_event', [
            'id_shop' => max(0, (int) ($data['id_shop'] ?? 0)),
            'id_customer' => max(0, (int) ($data['id_customer'] ?? 0)),
            'id_guest' => max(0, (int) ($data['id_guest'] ?? 0)),
            'id_cart' => max(0, (int) ($data['id_cart'] ?? 0)),
            'id_product' => max(0, (int) ($data['id_product'] ?? 0)),
            'event_type' => pSQL((string) ($data['event_type'] ?? 'blocked')),
            'limit_code' => pSQL((string) ($data['limit_code'] ?? '')),
            'limit_value' => (int) ($data['limit_value'] ?? 0),
            'observed_value' => (int) ($data['observed_value'] ?? 0),
            'source_type' => pSQL((string) ($data['source_type'] ?? 'default')),
            'source_id' => max(0, (int) ($data['source_id'] ?? 0)),
            'message' => pSQL((string) ($data['message'] ?? '')),
            'metadata_json' => pSQL(
                json_encode(
                    $data['metadata'] ?? [],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                ) ?: '{}',
                true
            ),
            'id_employee' => max(0, (int) ($data['id_employee'] ?? 0)),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function recentEvents(int $idShop, int $limit = 100): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT le.*,c.firstname,c.lastname,c.email,pl.name product_name'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_event` le'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer=le.id_customer'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product=le.id_product'
            . ' AND pl.id_shop=le.id_shop AND pl.id_lang=' . (int) \Context::getContext()->language->id
            . ' WHERE le.id_shop=' . $idShop
            . ' ORDER BY le.id_fabricssamples_limit_event DESC LIMIT ' . max(1, min(1000, $limit))
        );
        return is_array($rows) ? array_values($rows) : [];
    }
}
