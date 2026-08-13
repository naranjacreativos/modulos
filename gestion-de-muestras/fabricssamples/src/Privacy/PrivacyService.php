<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Privacy;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Infrastructure\DatabaseLock;

final class PrivacyService
{
    public function __construct(private ModuleConfiguration $configuration)
    {
    }

    public function exportCustomer(int $idCustomer): string
    {
        if ($idCustomer <= 0) {
            return '[]';
        }

        $db = \Db::getInstance();
        $data = [
            'module' => 'fabricssamples',
            'customer_id' => $idCustomer,
            'order_samples' => $this->rows($db, 'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_order` WHERE id_customer=' . $idCustomer),
            'coupons' => $this->rows($db, 'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` WHERE id_customer=' . $idCustomer),
            'coupon_reissues' => $this->rows($db, 'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` WHERE id_customer=' . $idCustomer),
            'conversions' => $this->rows($db, 'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_conversion` WHERE id_customer=' . $idCustomer),
            'limit_exceptions' => $this->rows($db, "SELECT * FROM `" . _DB_PREFIX_ . "fabricssamples_limit_exception` WHERE target_type='customer' AND target_id=" . $idCustomer),
            'limit_resets' => $this->rows($db, 'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_reset` WHERE id_customer=' . $idCustomer),
            'limit_events' => $this->rows($db, 'SELECT * FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_event` WHERE id_customer=' . $idCustomer),
        ];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return is_string($json) ? $json : '[]';
    }

    public function deleteCustomer(int $idCustomer): string
    {
        if ($idCustomer <= 0) {
            return json_encode(false);
        }

        $db = \Db::getInstance();
        try {
            return (new DatabaseLock())->synchronized('gdpr-customer:' . $idCustomer, function () use ($db, $idCustomer): string {
                if (!$db->execute('START TRANSACTION')) {
                    throw new \RuntimeException('No se pudo iniciar la anonimización.');
                }
                $rules = $this->rows(
                    $db,
                    'SELECT id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` WHERE id_customer=' . $idCustomer
                    . ' UNION SELECT id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` WHERE id_customer=' . $idCustomer
                );
                $ruleIds = array_values(array_unique(array_filter(array_map(
                    'intval',
                    array_column($rules, 'id_cart_rule')
                ))));
                if ($ruleIds !== [] && !$db->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'cart_rule` SET id_customer=0,active=0,code=\'\''
                    . ' WHERE id_cart_rule IN (' . implode(',', $ruleIds) . ')'
                )) {
                    throw new \RuntimeException('No se pudieron anonimizar las reglas de carrito.');
                }

            $operations = [
                'DELETE fsc FROM `' . _DB_PREFIX_ . 'fabricssamples_cart` fsc INNER JOIN `' . _DB_PREFIX_ . 'cart` c ON c.id_cart=fsc.id_cart WHERE c.id_customer=' . $idCustomer,
                'DELETE FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_event` WHERE id_customer=' . $idCustomer,
                'DELETE FROM `' . _DB_PREFIX_ . 'fabricssamples_limit_reset` WHERE id_customer=' . $idCustomer,
                "DELETE FROM `" . _DB_PREFIX_ . "fabricssamples_limit_exception` WHERE target_type='customer' AND target_id=" . $idCustomer,
                'UPDATE `' . _DB_PREFIX_ . 'fabricssamples_order` SET id_customer=0 WHERE id_customer=' . $idCustomer,
                "UPDATE `" . _DB_PREFIX_ . "fabricssamples_coupon` SET id_customer=0, code='', email_sent=0, state='deleted_permanently', state_reason='gdpr_erasure', deleted_permanently=1, date_deleted=NOW(), date_upd=NOW() WHERE id_customer=" . $idCustomer,
                "UPDATE `" . _DB_PREFIX_ . "fabricssamples_coupon_reissue` SET id_customer=0, original_code='', code='', state='deleted_permanently', pending_guard=NULL, email_requested=0, email_sent=0, date_upd=NOW() WHERE id_customer=" . $idCustomer,
                'UPDATE `' . _DB_PREFIX_ . 'fabricssamples_coupon_suppression` SET id_customer=0 WHERE id_customer=' . $idCustomer,
                'UPDATE `' . _DB_PREFIX_ . 'fabricssamples_conversion` SET id_customer=0 WHERE id_customer=' . $idCustomer,
            ];
            foreach ($operations as $sql) {
                if (!$db->execute($sql)) {
                    throw new \RuntimeException('No se pudo anonimizar una tabla del módulo.');
                }
            }
                $this->anonymizeAuditRows($db, $idCustomer);
                if (!$db->execute('COMMIT')) {
                    throw new \RuntimeException('No se pudo confirmar la anonimización.');
                }
                return json_encode(true);
            });
        } catch (\Throwable $exception) {
            $db->execute('ROLLBACK');
            \PrestaShopLogger::addLog('fabricssamples RGPD: ' . $exception->getMessage(), 3);
            return json_encode(false);
        }
    }

    /** @return array{audit:int,limit_events:int,limit_resets:int} */
    public function purgeExpired(): array
    {
        $db = \Db::getInstance();
        $result = ['audit' => 0, 'limit_events' => 0, 'limit_resets' => 0];
        $policies = [
            'audit' => ['table' => 'fabricssamples_audit', 'column' => 'date_add', 'days' => $this->configuration->getInt('RETENTION_AUDIT_DAYS', 365)],
            'limit_events' => ['table' => 'fabricssamples_limit_event', 'column' => 'date_add', 'days' => $this->configuration->getInt('RETENTION_LIMIT_EVENT_DAYS', 365)],
            'limit_resets' => ['table' => 'fabricssamples_limit_reset', 'column' => 'date_add', 'days' => $this->configuration->getInt('RETENTION_LIMIT_RESET_DAYS', 730)],
        ];
        foreach ($policies as $key => $policy) {
            $days = min(3650, max(30, (int) $policy['days']));
            $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
            if (!$db->delete((string) $policy['table'], bqSQL((string) $policy['column']) . "<'" . pSQL($cutoff) . "'")) {
                throw new \RuntimeException('No se pudo aplicar la retención de ' . $key . '.');
            }
            $result[$key] = method_exists($db, 'Affected_Rows') ? (int) $db->Affected_Rows() : 0;
        }

        return $result;
    }

    public function runDaily(): void
    {
        $today = date('Y-m-d');
        if ((string) \Configuration::get(ModuleConfiguration::PREFIX . 'RETENTION_LAST_RUN') === $today) {
            return;
        }
        try {
            $this->purgeExpired();
            \Configuration::updateValue(ModuleConfiguration::PREFIX . 'RETENTION_LAST_RUN', $today);
        } catch (\Throwable $exception) {
            \PrestaShopLogger::addLog('fabricssamples retention: ' . $exception->getMessage(), 2);
        }
    }

    /** @return list<array<string,mixed>> */
    private function rows(\Db $db, string $sql): array
    {
        $rows = $db->executeS($sql);
        return is_array($rows) ? array_values($rows) : [];
    }

    private function anonymizeAuditRows(\Db $db, int $idCustomer): void
    {
        $rows = $this->rows(
            $db,
            'SELECT id_fabricssamples_audit,entity_type,entity_id,old_value_json,new_value_json'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_audit`'
        );
        foreach ($rows as $row) {
            $matches = ((string) ($row['entity_type'] ?? '') === 'customer'
                    && (int) ($row['entity_id'] ?? 0) === $idCustomer)
                || $this->jsonContainsCustomer((string) ($row['old_value_json'] ?? ''), $idCustomer)
                || $this->jsonContainsCustomer((string) ($row['new_value_json'] ?? ''), $idCustomer);
            if (!$matches) {
                continue;
            }
            if (!$db->update('fabricssamples_audit', [
                'entity_id' => '',
                'old_value_json' => '',
                'new_value_json' => '',
                'note' => 'RGPD: datos anonimizados',
                'ip_address' => '',
            ], 'id_fabricssamples_audit=' . (int) $row['id_fabricssamples_audit'])) {
                throw new \RuntimeException('No se pudo anonimizar la auditoría del cliente.');
            }
        }
    }

    private function jsonContainsCustomer(string $json, int $idCustomer): bool
    {
        if ($json === '') {
            return false;
        }
        $value = json_decode($json, true);
        return is_array($value) && $this->containsCustomerId($value, $idCustomer);
    }

    private function containsCustomerId(array $value, int $idCustomer): bool
    {
        foreach ($value as $key => $item) {
            if ((string) $key === 'id_customer' && (int) $item === $idCustomer) {
                return true;
            }
            if (is_array($item) && $this->containsCustomerId($item, $idCustomer)) {
                return true;
            }
        }
        return false;
    }
}
