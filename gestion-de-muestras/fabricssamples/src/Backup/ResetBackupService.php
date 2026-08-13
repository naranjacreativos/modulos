<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Backup;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;

final class ResetBackupService
{
    private const FORMAT = 'fabricssamples-reset-backup';
    private const FORMAT_VERSION = 2;
    private const CHUNK_SIZE = 500;

    public function __construct(private \Fabricssamples $module)
    {
    }

    /** @return array{filename:string,size:int,sha256:string,created_at:string} */
    public function create(): array
    {
        if (!function_exists('gzopen')) {
            throw new \RuntimeException('La extensión zlib de PHP es obligatoria para crear la copia de seguridad.');
        }

        $directory = $this->backupDirectory();
        $this->ensureDirectory($directory);
        $createdAt = date('Y-m-d H:i:s');
        $filename = 'fabricssamples-reset-' . date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.fsb';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        $plainPath = $directory . DIRECTORY_SEPARATOR . '.plain-' . bin2hex(random_bytes(8)) . '.jsonl.gz';
        $encryptedPath = $path . '.part';
        $handle = gzopen($plainPath, 'wb9');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el archivo temporal de copia de seguridad.');
        }
        @chmod($plainPath, 0600);

        $db = \Db::getInstance();
        $transactionStarted = false;
        $counts = [];
        try {
            if (!$db->execute('START TRANSACTION')) {
                throw new \RuntimeException('No se pudo iniciar una lectura consistente para la copia de seguridad.');
            }
            $transactionStarted = true;

            $this->writeLine($handle, [
                'type' => 'manifest',
                'format' => self::FORMAT,
                'format_version' => self::FORMAT_VERSION,
                'module_version' => \Fabricssamples::VERSION,
                'prestashop_version' => defined('_PS_VERSION_') ? (string) _PS_VERSION_ : '',
                'php_version' => PHP_VERSION,
                'created_at' => $createdAt,
                'database_prefix' => defined('_DB_PREFIX_') ? (string) _DB_PREFIX_ : '',
                'shop_context_id' => class_exists('Shop') ? (int) \Shop::getContextShopID() : 0,
            ]);

            foreach ($this->moduleTables() as $logicalTable) {
                $physicalTable = _DB_PREFIX_ . $logicalTable;
                if (!$this->tableExists($physicalTable)) {
                    continue;
                }
                $counts['module:' . $logicalTable] = $this->writeQuery(
                    $handle,
                    'module_table',
                    $logicalTable,
                    'SELECT * FROM `' . bqSQL($physicalTable) . '`'
                );
            }

            foreach ($this->configurationQueries() as $name => $query) {
                $counts['configuration:' . $name] = $this->writeQuery($handle, 'configuration_table', $name, $query);
            }
            foreach ($this->nativeQueries() as $name => $query) {
                $counts['native:' . $name] = $this->writeQuery($handle, 'native_table', $name, $query);
            }

            foreach ($this->imageFiles() as $relative => $imagePath) {
                $contents = file_get_contents($imagePath);
                if ($contents === false) {
                    throw new \RuntimeException('No se pudo leer la imagen ' . $relative . ' durante la copia.');
                }
                $this->writeLine($handle, [
                    'type' => 'file',
                    'path' => $relative,
                    'size' => strlen($contents),
                    'sha256' => hash('sha256', $contents),
                    'contents_base64' => base64_encode($contents),
                ]);
                unset($contents);
            }

            $this->writeLine($handle, ['type' => 'summary', 'row_counts' => $counts]);
            if (!$db->execute('COMMIT')) {
                throw new \RuntimeException('No se pudo cerrar la lectura consistente de la copia de seguridad.');
            }
            $transactionStarted = false;
        } catch (\Throwable $exception) {
            if ($transactionStarted) {
                $db->execute('ROLLBACK');
            }
            gzclose($handle);
            @unlink($plainPath);
            throw $exception;
        }

        if (!gzclose($handle)) {
            @unlink($plainPath);
            throw new \RuntimeException('No se pudo finalizar la copia de seguridad comprimida.');
        }
        try {
            $this->validatePlaintext($plainPath);
            (new BackupCipher())->encrypt($plainPath, $encryptedPath);
            $verificationPath = $directory . DIRECTORY_SEPARATOR . '.verify-' . bin2hex(random_bytes(8)) . '.jsonl.gz';
            try {
                (new BackupCipher())->decrypt($encryptedPath, $verificationPath);
                $this->validatePlaintext($verificationPath);
            } finally {
                @unlink($verificationPath);
            }
        } catch (\Throwable $exception) {
            @unlink($plainPath);
            @unlink($encryptedPath);
            throw $exception;
        }
        @unlink($plainPath);
        if (!@rename($encryptedPath, $path)) {
            @unlink($encryptedPath);
            throw new \RuntimeException('No se pudo publicar la copia cifrada terminada.');
        }
        @chmod($path, 0600);
        $this->prune($filename);

        return [
            'filename' => $filename,
            'size' => (int) filesize($path),
            'sha256' => (string) hash_file('sha256', $path),
            'created_at' => $createdAt,
        ];
    }

    /** @return list<array{filename:string,size:int,created_at:string}> */
    public function listAvailable(): array
    {
        $directory = $this->backupDirectory();
        if (!is_dir($directory)) {
            return [];
        }
        $files = array_merge(
            glob($directory . DIRECTORY_SEPARATOR . 'fabricssamples-reset-*.fsb') ?: [],
            glob($directory . DIRECTORY_SEPARATOR . 'fabricssamples-reset-*.jsonl.gz') ?: []
        );
        usort($files, static fn (string $left, string $right): int => (int) filemtime($right) <=> (int) filemtime($left));
        $rows = [];
        $limit = min(50, max(1, (new ModuleConfiguration())->getInt('BACKUP_RETENTION_COUNT', 10)));
        foreach (array_slice($files, 0, $limit) as $path) {
            $rows[] = [
                'filename' => basename($path),
                'size' => (int) filesize($path),
                'created_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
                'encrypted' => str_ends_with($path, '.fsb'),
            ];
        }

        return $rows;
    }

    public function resolve(string $filename): string
    {
        if (!preg_match('/^fabricssamples-reset-[0-9]{8}-[0-9]{6}-[a-f0-9]{12}\.(?:fsb|jsonl\.gz)$/', $filename)) {
            return '';
        }
        $path = $this->backupDirectory() . DIRECTORY_SEPARATOR . $filename;
        return is_file($path) ? $path : '';
    }

    /** @return list<string> */
    private function moduleTables(): array
    {
        return [
            'fabricssamples_product',
            'fabricssamples_product_lang',
            'fabricssamples_cart',
            'fabricssamples_order',
            'fabricssamples_history_exclusion',
            'fabricssamples_coupon',
            'fabricssamples_coupon_reissue',
            'fabricssamples_coupon_suppression',
            'fabricssamples_stock_movement',
            'fabricssamples_conversion',
            'fabricssamples_limit_exception',
            'fabricssamples_limit_reset',
            'fabricssamples_limit_event',
            'fabricssamples_audit',
            'fabricssamples_schema_migration',
            'fabricssamples_rate_limit',
        ];
    }

    /** @return array<string,string> */
    private function configurationQueries(): array
    {
        $prefix = pSQL(\NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration::PREFIX);
        $prefixLength = strlen(\NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration::PREFIX);
        $queries = [
            'configuration' => 'SELECT * FROM `' . _DB_PREFIX_ . 'configuration` WHERE LEFT(name,' . $prefixLength . ')=\'' . $prefix . '\'',
            'configuration_lang' => 'SELECT cl.* FROM `' . _DB_PREFIX_ . 'configuration_lang` cl'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'configuration` c ON c.id_configuration=cl.id_configuration'
                . ' WHERE LEFT(c.name,' . $prefixLength . ')=\'' . $prefix . '\'',
            'configuration_shop' => 'SELECT cs.* FROM `' . _DB_PREFIX_ . 'configuration_shop` cs'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'configuration` c ON c.id_configuration=cs.id_configuration'
                . ' WHERE LEFT(c.name,' . $prefixLength . ')=\'' . $prefix . '\'',
        ];

        // PrestaShop stores shop scoping directly in `configuration` on current
        // versions. Some historical/custom installations may expose auxiliary
        // configuration tables, so include them only when they actually exist.
        return array_filter(
            $queries,
            fn (string $query, string $table): bool => $this->tableExists(_DB_PREFIX_ . $table),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** @return array<string,string> */
    private function nativeQueries(): array
    {
        $cartJoin = ' INNER JOIN `' . _DB_PREFIX_ . 'fabricssamples_cart` fs ON fs.id_customization=n.id_customization';
        $couponRules = '(SELECT id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon`'
            . ' UNION SELECT id_cart_rule FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue`)';
        $couponJoin = ' INNER JOIN ' . $couponRules . ' fs ON fs.id_cart_rule=n.id_cart_rule';
        $queries = [
            'cart_product' => 'SELECT DISTINCT n.* FROM `' . _DB_PREFIX_ . 'cart_product` n' . $cartJoin,
            'customization' => 'SELECT DISTINCT n.* FROM `' . _DB_PREFIX_ . 'customization` n' . $cartJoin,
            'customized_data' => 'SELECT DISTINCT n.* FROM `' . _DB_PREFIX_ . 'customized_data` n' . $cartJoin,
            'cart_rule' => 'SELECT DISTINCT n.* FROM `' . _DB_PREFIX_ . 'cart_rule` n' . $couponJoin,
        ];
        foreach (['cart_cart_rule', 'cart_rule_carrier', 'cart_rule_country', 'cart_rule_group', 'cart_rule_lang', 'cart_rule_shop'] as $table) {
            $queries[$table] = 'SELECT DISTINCT n.* FROM `' . _DB_PREFIX_ . bqSQL($table) . '` n' . $couponJoin;
        }
        $queries['cart_rule_product_rule_group'] = 'SELECT DISTINCT n.* FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule_group` n'
            . ' INNER JOIN ' . $couponRules . ' fs ON fs.id_cart_rule=n.id_cart_rule';
        $queries['cart_rule_product_rule'] = 'SELECT DISTINCT n.* FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule` n'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'cart_rule_product_rule_group` g ON g.id_product_rule_group=n.id_product_rule_group'
            . ' INNER JOIN ' . $couponRules . ' fs ON fs.id_cart_rule=g.id_cart_rule';
        $queries['cart_rule_product_rule_value'] = 'SELECT DISTINCT n.* FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule_value` n'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'cart_rule_product_rule` r ON r.id_product_rule=n.id_product_rule'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'cart_rule_product_rule_group` g ON g.id_product_rule_group=r.id_product_rule_group'
            . ' INNER JOIN ' . $couponRules . ' fs ON fs.id_cart_rule=g.id_cart_rule';
        $queries['cart_rule_combination'] = 'SELECT DISTINCT n.* FROM `' . _DB_PREFIX_ . 'cart_rule_combination` n'
            . ' INNER JOIN ' . $couponRules . ' fs'
            . ' ON fs.id_cart_rule=n.id_cart_rule_1 OR fs.id_cart_rule=n.id_cart_rule_2';

        return array_filter(
            $queries,
            fn (string $query, string $table): bool => $this->tableExists(_DB_PREFIX_ . $table),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function writeQuery($handle, string $type, string $table, string $query): int
    {
        $offset = 0;
        $count = 0;
        do {
            $rows = \Db::getInstance()->executeS(
                $query . ' LIMIT ' . $offset . ',' . self::CHUNK_SIZE
            );
            if (!is_array($rows)) {
                throw new \RuntimeException('No se pudo leer la tabla ' . $table . ' para la copia de seguridad.');
            }
            foreach ($rows as $row) {
                $this->writeLine($handle, ['type' => $type, 'table' => $table, 'data' => $row]);
                ++$count;
            }
            $read = count($rows);
            $offset += $read;
        } while ($read === self::CHUNK_SIZE);

        return $count;
    }

    private function writeLine($handle, array $data): void
    {
        $line = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($line) || gzwrite($handle, $line . "\n") === false) {
            throw new \RuntimeException('No se pudo escribir la copia de seguridad.');
        }
    }

    /** @return array<string,string> */
    private function imageFiles(): array
    {
        $files = [];
        foreach (['views/img/custom', 'views/img/orders'] as $relativeDirectory) {
            $directory = $this->module->getLocalPath() . $relativeDirectory;
            if (!is_dir($directory)) {
                continue;
            }
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path) && basename($path) !== 'index.php') {
                    $files[$relativeDirectory . '/' . basename($path)] = $path;
                }
            }
        }

        $privateDirectory = (new \NaranjaCreativos\FabricSamples\Service\ImageSnapshotService($this->module))->storageDirectory();
        if (is_dir($privateDirectory)) {
            foreach (glob($privateDirectory . DIRECTORY_SEPARATOR . 'fs-*.jpg') ?: [] as $path) {
                if (is_file($path)) {
                    $files['private/orders/' . basename($path)] = $path;
                }
            }
        }

        return $files;
    }

    private function tableExists(string $table): bool
    {
        $escaped = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $table);
        $rows = \Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($escaped) . "'");
        return is_array($rows) && $rows !== [];
    }

    public function backupDirectory(): string
    {
        if (!defined('_PS_ROOT_DIR_')) {
            throw new \RuntimeException('No se pudo determinar el directorio raíz de PrestaShop.');
        }
        return rtrim((string) _PS_ROOT_DIR_, '/\\') . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'fabricssamples';
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo crear el directorio protegido de copias de seguridad.');
        }
        @chmod($directory, 0700);
        if (!is_writable($directory)) {
            throw new \RuntimeException('El directorio de copias de seguridad no tiene permisos de escritura.');
        }
        $protection = $directory . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($protection)) {
            @file_put_contents($protection, "Require all denied\nDeny from all\n");
        }
        $index = $directory . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($index)) {
            @file_put_contents($index, "<?php exit;\n");
        }
    }

    private function prune(string $keepFilename): void
    {
        $files = array_merge(
            glob($this->backupDirectory() . DIRECTORY_SEPARATOR . 'fabricssamples-reset-*.fsb') ?: [],
            glob($this->backupDirectory() . DIRECTORY_SEPARATOR . 'fabricssamples-reset-*.jsonl.gz') ?: []
        );
        usort($files, static fn (string $left, string $right): int => (int) filemtime($right) <=> (int) filemtime($left));
        $configuration = new ModuleConfiguration();
        $keep = min(50, max(1, $configuration->getInt('BACKUP_RETENTION_COUNT', 10)));
        $maxAgeDays = min(3650, max(1, $configuration->getInt('BACKUP_RETENTION_DAYS', 90)));
        $cutoff = time() - ($maxAgeDays * 86400);
        foreach ($files as $index => $path) {
            if (basename($path) !== $keepFilename) {
                if ($index >= $keep || (int) filemtime($path) < $cutoff) {
                    @unlink($path);
                }
            }
        }
    }

    /** @return array{manifest:array<string,mixed>,summary:array<string,mixed>,lines:int} */
    public function validatePlaintext(string $path): array
    {
        $handle = @gzopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir la copia para verificarla.');
        }
        $manifest = [];
        $summary = [];
        $lines = 0;
        try {
            while (!gzeof($handle)) {
                $line = gzgets($handle, 67108864);
                if ($line === false) {
                    break;
                }
                if (strlen($line) >= 67108863) {
                    throw new \RuntimeException('La copia contiene una línea demasiado grande.');
                }
                $row = json_decode(trim($line), true, 64, JSON_THROW_ON_ERROR);
                if (!is_array($row) || empty($row['type'])) {
                    throw new \RuntimeException('La copia contiene un registro no válido.');
                }
                if ($lines === 0) {
                    $manifest = $row;
                    if (($manifest['type'] ?? '') !== 'manifest' || ($manifest['format'] ?? '') !== self::FORMAT) {
                        throw new \RuntimeException('El manifiesto de la copia no es compatible.');
                    }
                }
                if (($row['type'] ?? '') === 'file') {
                    $contents = base64_decode((string) ($row['contents_base64'] ?? ''), true);
                    if (!is_string($contents)
                        || strlen($contents) !== (int) ($row['size'] ?? -1)
                        || !hash_equals((string) ($row['sha256'] ?? ''), hash('sha256', $contents))) {
                        throw new \RuntimeException('Una imagen de la copia no supera la verificación de integridad.');
                    }
                }
                if (($row['type'] ?? '') === 'summary') {
                    $summary = $row;
                }
                ++$lines;
            }
        } finally {
            gzclose($handle);
        }
        if ($manifest === [] || $summary === [] || $lines < 2) {
            throw new \RuntimeException('La copia está incompleta: falta el manifiesto o el resumen final.');
        }
        return ['manifest' => $manifest, 'summary' => $summary, 'lines' => $lines];
    }
}
