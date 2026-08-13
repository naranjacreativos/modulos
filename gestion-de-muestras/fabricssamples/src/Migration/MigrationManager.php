<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Migration;

use NaranjaCreativos\FabricSamples\Diagnostic\SchemaInspector;

final class MigrationManager
{
    private const TABLE = 'fabricssamples_schema_migration';

    public function __construct(
        private \Fabricssamples $module,
        private SchemaInspector $schemaInspector
    ) {
    }

    /**
     * Executes an idempotent migration and records every attempt.
     *
     * DDL statements are intentionally not wrapped in a transaction because MySQL and
     * MariaDB implicitly commit many ALTER/CREATE operations. Recovery is achieved by
     * recording the failed step and safely re-running the idempotent migration.
     */
    public function migrate(string $targetVersion, callable $migration, string $sourceFile = ''): bool
    {
        if (!$this->ensureTable()) {
            $this->log('No se pudo crear la tabla de control de migraciones.', 3);
            return false;
        }

        $checksum = $this->checksum($targetVersion, $sourceFile);
        $existing = $this->find($targetVersion);
        if (($existing['status'] ?? '') === 'success'
            && ($existing['checksum'] ?? '') === $checksum
            && $this->isSchemaHealthy()) {
            \Configuration::updateValue('FABRICS_SAMPLES_SCHEMA_VERSION', $targetVersion);
            return true;
        }

        $startedAt = microtime(true);
        $started = false;

        try {
            $this->markRunning($targetVersion, $checksum);
            $started = true;
            $result = $migration();
            if ($result === false) {
                throw new \RuntimeException('La tarea de migración devolvió false.');
            }

            $repair = $this->schemaInspector->repair();
            if ($repair['errors'] !== []) {
                throw new \RuntimeException(implode(' | ', $repair['errors']));
            }

            $inspection = $this->schemaInspector->inspect();
            $missingColumns = array_sum(array_map('count', $inspection['missing_columns']));
            $missingIndexes = array_sum(array_map('count', $inspection['missing_indexes']));
            if ($inspection['missing_tables'] !== [] || $missingColumns > 0 || $missingIndexes > 0) {
                throw new \RuntimeException('El esquema sigue incompleto después de la reparación.');
            }

            $details = [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'created_tables' => $repair['created_tables'],
                'added_columns' => $repair['added_columns'],
                'added_indexes' => $repair['added_indexes'],
            ];
            if (!$this->markFinished($targetVersion, 'success', '', $details)) {
                throw new \RuntimeException('No se pudo registrar el resultado de la migración.');
            }
            if (!\Configuration::updateValue('FABRICS_SAMPLES_SCHEMA_VERSION', $targetVersion)) {
                throw new \RuntimeException('No se pudo actualizar la versión del esquema.');
            }

            return true;
        } catch (\Throwable $exception) {
            $details = [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => get_class($exception),
            ];
            if ($started) {
                $this->markFinished($targetVersion, 'failed', $exception->getMessage(), $details);
            }
            $this->log('Migración ' . $targetVersion . ' fallida: ' . $exception->getMessage(), 3);

            return false;
        }
    }

    public function recordFreshInstall(string $version): bool
    {
        return $this->migrate($version, static fn (): bool => true, __FILE__);
    }

    /** @return array<string,mixed> */
    public function latest(): array
    {
        if (!$this->ensureTable()) {
            return [];
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT migration_version,checksum,status,attempts,started_at,finished_at,error_message,details_json'
            . ' FROM `' . _DB_PREFIX_ . self::TABLE . '` ORDER BY id_fabricssamples_schema_migration DESC'
        );

        return is_array($rows) && isset($rows[0]) ? $rows[0] : [];
    }

    public function ensureTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` ('
            . '`id_fabricssamples_schema_migration` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`migration_version` VARCHAR(32) NOT NULL,'
            . '`checksum` CHAR(64) NOT NULL DEFAULT \'\','
            . '`status` VARCHAR(16) NOT NULL DEFAULT \'pending\','
            . '`attempts` INT UNSIGNED NOT NULL DEFAULT 0,'
            . '`started_at` DATETIME NOT NULL,'
            . '`finished_at` DATETIME NULL,'
            . '`error_message` TEXT NULL,'
            . '`details_json` MEDIUMTEXT NULL,'
            . 'PRIMARY KEY (`id_fabricssamples_schema_migration`),'
            . 'UNIQUE KEY `uniq_migration_version` (`migration_version`),'
            . 'KEY `idx_migration_status` (`status`,`started_at`)'
            . ') ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return (bool) \Db::getInstance()->execute($sql);
    }

    /** @return array<string,mixed> */
    private function find(string $version): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT migration_version,checksum,status,attempts,started_at,finished_at,error_message,details_json'
            . ' FROM `' . _DB_PREFIX_ . self::TABLE . '`'
            . ' WHERE migration_version=\'' . pSQL($version) . '\''
        );

        return is_array($rows) && isset($rows[0]) ? $rows[0] : [];
    }

    private function markRunning(string $version, string $checksum): void
    {
        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . self::TABLE . '`'
            . ' (`migration_version`,`checksum`,`status`,`attempts`,`started_at`,`finished_at`,`error_message`,`details_json`) VALUES ('
            . '\'' . pSQL($version) . '\',\'' . pSQL($checksum) . '\',\'running\',1,\'' . pSQL($now) . '\',NULL,NULL,NULL)'
            . ' ON DUPLICATE KEY UPDATE checksum=VALUES(checksum),status=\'running\','
            . ' attempts=attempts+1,started_at=VALUES(started_at),finished_at=NULL,error_message=NULL,details_json=NULL';
        if (!\Db::getInstance()->execute($sql)) {
            throw new \RuntimeException('No se pudo registrar el inicio de la migración: ' . \Db::getInstance()->getMsgError());
        }
    }

    /** @param array<string,mixed> $details */
    private function markFinished(string $version, string $status, string $error, array $details): bool
    {
        $data = [
            'status' => pSQL($status),
            'finished_at' => date('Y-m-d H:i:s'),
            'error_message' => pSQL($error, true),
            'details_json' => pSQL(
                json_encode(
                    $details,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                ) ?: '{}',
                true
            ),
        ];
        return (bool) \Db::getInstance()->update(
            self::TABLE,
            $data,
            'migration_version=\'' . pSQL($version) . '\''
        );
    }

    private function isSchemaHealthy(): bool
    {
        $inspection = $this->schemaInspector->inspect();
        return $inspection['missing_tables'] === []
            && array_sum(array_map('count', $inspection['missing_columns'])) === 0
            && array_sum(array_map('count', $inspection['missing_indexes'])) === 0;
    }

    private function checksum(string $version, string $sourceFile): string
    {
        $parts = [$version, $this->module->version];
        foreach ([$sourceFile, $this->module->getLocalPath() . 'sql/install.sql'] as $file) {
            if ($file !== '' && is_file($file)) {
                $parts[] = hash_file('sha256', $file) ?: '';
            }
        }

        return hash('sha256', implode('|', $parts));
    }

    private function log(string $message, int $severity): void
    {
        if (class_exists('PrestaShopLogger')) {
            \PrestaShopLogger::addLog(
                'fabricssamples: ' . $message,
                $severity,
                null,
                'Module',
                (int) $this->module->id,
                true
            );
        }
    }
}
