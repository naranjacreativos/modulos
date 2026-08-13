<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Backup;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;

final class RestoreBackupService
{
    private const MODULE_TABLES = [
        'fabricssamples_product', 'fabricssamples_product_lang', 'fabricssamples_cart',
        'fabricssamples_order', 'fabricssamples_history_exclusion', 'fabricssamples_coupon',
        'fabricssamples_coupon_reissue',
        'fabricssamples_coupon_suppression', 'fabricssamples_stock_movement',
        'fabricssamples_conversion', 'fabricssamples_limit_exception', 'fabricssamples_limit_reset',
        'fabricssamples_limit_event', 'fabricssamples_audit', 'fabricssamples_schema_migration',
        'fabricssamples_rate_limit',
    ];
    private const CONFIG_TABLES = ['configuration', 'configuration_lang', 'configuration_shop'];
    private const NATIVE_TABLES = [
        'cart_product', 'customization', 'customized_data', 'cart_rule', 'cart_cart_rule',
        'cart_rule_carrier', 'cart_rule_country', 'cart_rule_group', 'cart_rule_lang',
        'cart_rule_shop', 'cart_rule_product_rule_group', 'cart_rule_product_rule',
        'cart_rule_product_rule_value', 'cart_rule_combination',
    ];

    public function __construct(private \Fabricssamples $module)
    {
    }

    /** @return array{manifest:array<string,mixed>,summary:array<string,mixed>,lines:int} */
    public function validate(string $filename): array
    {
        $plainPath = $this->decryptToTemporary($filename);
        try {
            return (new ResetBackupService($this->module))->validatePlaintext($plainPath);
        } finally {
            @unlink($plainPath);
        }
    }

    /** @return array{success:bool,safety_backup:string,restored_rows:int,restored_files:int,manifest:array<string,mixed>} */
    public function restore(string $filename): array
    {
        $backup = new ResetBackupService($this->module);
        $plainPath = $this->decryptToTemporary($filename);
        $validation = $backup->validatePlaintext($plainPath);

        $reset = $this->module->diagnosticResetModule();
        $safetyFilename = (string) ($reset['backup']['filename'] ?? '');
        if (empty($reset['success'])) {
            @unlink($plainPath);
            throw new \RuntimeException(
                'No se pudo preparar la restauración. La copia de seguridad previa es ' . ($safetyFilename !== '' ? $safetyFilename : 'desconocida') . '.'
            );
        }

        $staging = $backup->backupDirectory() . DIRECTORY_SEPARATOR . '.restore-' . bin2hex(random_bytes(8));
        if (!@mkdir($staging, 0700, true) && !is_dir($staging)) {
            @unlink($plainPath);
            throw new \RuntimeException('No se pudo crear el área protegida de restauración.');
        }

        $db = \Db::getInstance();
        $rows = 0;
        $files = 0;
        $transactionStarted = false;
        $preparedFiles = [];
        try {
            if (!$db->execute('START TRANSACTION')) {
                throw new \RuntimeException('No se pudo iniciar la transacción de restauración.');
            }
            $transactionStarted = true;
            $this->clearFreshData($db);

            $handle = @gzopen($plainPath, 'rb');
            if ($handle === false) {
                throw new \RuntimeException('No se pudo leer la copia validada.');
            }
            try {
                while (!gzeof($handle)) {
                    $line = gzgets($handle, 67108864);
                    if ($line === false) {
                        break;
                    }
                    $record = json_decode(trim($line), true, 64, JSON_THROW_ON_ERROR);
                    if (!is_array($record)) {
                        throw new \RuntimeException('La copia contiene un registro no válido.');
                    }
                    $type = (string) ($record['type'] ?? '');
                    if (in_array($type, ['module_table', 'configuration_table', 'native_table'], true)) {
                        $table = (string) ($record['table'] ?? '');
                        $this->assertTableAllowed($type, $table);
                        if ($type === 'configuration_table' && !$this->tableExists(_DB_PREFIX_ . $table)) {
                            continue;
                        }
                        $data = $record['data'] ?? null;
                        if (!is_array($data) || !$db->insert($table, $data, true, false, \Db::REPLACE)) {
                            throw new \RuntimeException('No se pudo restaurar una fila de ' . $table . '.');
                        }
                        ++$rows;
                    } elseif ($type === 'file') {
                        $this->stageFile($record, $staging);
                        ++$files;
                    }
                }
            } finally {
                gzclose($handle);
            }

            $preparedFiles = $this->prepareFilesForPublication($staging);
            if (!$db->execute('COMMIT')) {
                throw new \RuntimeException('No se pudo confirmar la restauración.');
            }
            $transactionStarted = false;
            $this->publishPreparedFiles($preparedFiles);
            $preparedFiles = [];
            if (method_exists('Tools', 'clearAllCache')) {
                \Tools::clearAllCache();
            }
            if (class_exists('Cache')) {
                \Cache::clean('*');
            }
        } catch (\Throwable $exception) {
            if ($transactionStarted) {
                $db->execute('ROLLBACK');
            }
            $this->discardPreparedFiles($preparedFiles);
            throw new \RuntimeException(
                $exception->getMessage() . ($safetyFilename !== '' ? ' Puede recuperar el estado previo con ' . $safetyFilename . '.' : ''),
                0,
                $exception
            );
        } finally {
            @unlink($plainPath);
            $this->removeDirectory($staging);
        }

        return [
            'success' => true,
            'safety_backup' => $safetyFilename,
            'restored_rows' => $rows,
            'restored_files' => $files,
            'manifest' => $validation['manifest'],
        ];
    }

    private function decryptToTemporary(string $filename): string
    {
        if (!str_ends_with($filename, '.fsb')) {
            throw new \RuntimeException('Solo se pueden restaurar copias cifradas creadas por la versión 2.14.0 o posterior.');
        }
        $backup = new ResetBackupService($this->module);
        $source = $backup->resolve(basename($filename));
        if ($source === '') {
            throw new \RuntimeException('La copia seleccionada no existe.');
        }
        $plainPath = $backup->backupDirectory() . DIRECTORY_SEPARATOR . '.decrypt-' . bin2hex(random_bytes(8)) . '.jsonl.gz';
        (new BackupCipher())->decrypt($source, $plainPath);
        return $plainPath;
    }

    private function clearFreshData(\Db $db): void
    {
        foreach (array_reverse(self::MODULE_TABLES) as $table) {
            if (!$db->delete($table, '1')) {
                throw new \RuntimeException('No se pudo vaciar ' . $table . ' antes de restaurar.');
            }
        }
        $prefix = pSQL(ModuleConfiguration::PREFIX);
        $length = strlen(ModuleConfiguration::PREFIX);
        $rows = $db->executeS(
            'SELECT DISTINCT name FROM `' . _DB_PREFIX_ . 'configuration`'
            . " WHERE LEFT(name," . $length . ")='" . $prefix . "'"
        );
        if (!is_array($rows)) {
            throw new \RuntimeException('No se pudo localizar la configuración antes de restaurar.');
        }
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '' && !\Configuration::deleteByName($name)) {
                throw new \RuntimeException('No se pudo preparar la configuración ' . $name . ' para restaurar.');
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $escaped = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $table);
        $rows = \Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($escaped) . "'");

        return is_array($rows) && $rows !== [];
    }

    private function assertTableAllowed(string $type, string $table): void
    {
        $allowed = match ($type) {
            'module_table' => self::MODULE_TABLES,
            'configuration_table' => self::CONFIG_TABLES,
            'native_table' => self::NATIVE_TABLES,
            default => [],
        };
        if (!in_array($table, $allowed, true)) {
            throw new \RuntimeException('La copia intenta restaurar una tabla no autorizada.');
        }
    }

    /** @param array<string,mixed> $record */
    private function stageFile(array $record, string $staging): void
    {
        $relative = str_replace('\\', '/', (string) ($record['path'] ?? ''));
        if (!preg_match('~^(?:views/img/(?:custom|orders)|private/orders)/[a-zA-Z0-9._-]+$~', $relative)) {
            throw new \RuntimeException('La copia contiene una ruta de archivo no autorizada.');
        }
        $contents = base64_decode((string) ($record['contents_base64'] ?? ''), true);
        if (!is_string($contents)
            || strlen($contents) !== (int) ($record['size'] ?? -1)
            || !hash_equals((string) ($record['sha256'] ?? ''), hash('sha256', $contents))) {
            throw new \RuntimeException('Un archivo restaurado no supera la verificación de integridad.');
        }
        $destination = $staging . DIRECTORY_SEPARATOR . $relative;
        $directory = dirname($destination);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar un directorio de imágenes restauradas.');
        }
        if (file_put_contents($destination, $contents, LOCK_EX) !== strlen($contents)) {
            throw new \RuntimeException('No se pudo preparar una imagen restaurada.');
        }
        @chmod($destination, 0600);
    }

    /** @return list<array{temporary:string,destination:string,mode:int}> */
    private function prepareFilesForPublication(string $staging): array
    {
        $prepared = [];
        foreach (['views/img/custom', 'views/img/orders', 'private/orders'] as $relativeDirectory) {
            $sourceDirectory = $staging . DIRECTORY_SEPARATOR . $relativeDirectory;
            if (!is_dir($sourceDirectory)) {
                continue;
            }
            $destinationDirectory = $relativeDirectory === 'private/orders'
                ? (new \NaranjaCreativos\FabricSamples\Service\ImageSnapshotService($this->module))->storageDirectory()
                : $this->module->getLocalPath() . $relativeDirectory;
            if (!is_dir($destinationDirectory) && !@mkdir($destinationDirectory, 0755, true) && !is_dir($destinationDirectory)) {
                throw new \RuntimeException('No se pudo publicar el directorio de imágenes restauradas.');
            }
            foreach (glob($sourceDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $source) {
                $destination = $destinationDirectory . DIRECTORY_SEPARATOR . basename($source);
                $temporary = $destination . '.restore-' . bin2hex(random_bytes(6));
                if (!@copy($source, $temporary)) {
                    $this->discardPreparedFiles($prepared);
                    throw new \RuntimeException('No se pudo preparar una imagen restaurada para su publicación atómica.');
                }
                $mode = $relativeDirectory === 'private/orders' ? 0600 : 0644;
                @chmod($temporary, $mode);
                $prepared[] = ['temporary' => $temporary, 'destination' => $destination, 'mode' => $mode];
            }
        }

        return $prepared;
    }

    /** @param list<array{temporary:string,destination:string,mode:int}> $prepared */
    private function publishPreparedFiles(array $prepared): void
    {
        foreach ($prepared as $file) {
            if (!@rename($file['temporary'], $file['destination'])) {
                throw new \RuntimeException('La base de datos se restauró, pero no se pudo publicar una imagen preparada. Use la copia de seguridad previa.');
            }
            @chmod($file['destination'], $file['mode']);
        }
    }

    /** @param list<array{temporary:string,destination:string,mode:int}> $prepared */
    private function discardPreparedFiles(array $prepared): void
    {
        foreach ($prepared as $file) {
            @unlink($file['temporary']);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
