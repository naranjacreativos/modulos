<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Diagnostic;

final class SchemaInspector
{
    public function __construct(private string $installSqlPath)
    {
    }

    /** @return array<string,array{create:string,columns:array<string,string>,indexes:array<string,string>}> */
    public function expectedSchema(): array
    {
        $sql = @file_get_contents($this->installSqlPath);
        if ($sql === false) {
            return [];
        }
        $sql = str_replace(
            ['PREFIX_', 'ENGINE_TYPE'],
            [_DB_PREFIX_, _MYSQL_ENGINE_],
            $sql
        );

        preg_match_all(
            '/CREATE TABLE IF NOT EXISTS `([^`]+)`\s*\((.*?)\)\s*ENGINE=.*?;/si',
            $sql,
            $matches,
            PREG_SET_ORDER
        );

        $schema = [];
        foreach ($matches as $match) {
            $table = (string) $match[1];
            $body = (string) $match[2];
            $columns = [];
            $indexes = [];
            foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
                $line = rtrim(trim($line), ',');
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^`([^`]+)`\s+(.+)$/', $line, $columnMatch)) {
                    $columns[(string) $columnMatch[1]] = (string) $columnMatch[2];
                    continue;
                }
                if (preg_match('/^PRIMARY KEY\s*(\(.+\))$/i', $line, $indexMatch)) {
                    $indexes['PRIMARY'] = 'PRIMARY KEY ' . (string) $indexMatch[1];
                    continue;
                }
                if (preg_match('/^UNIQUE KEY\s+`([^`]+)`\s*(\(.+\))$/i', $line, $indexMatch)) {
                    $indexes[(string) $indexMatch[1]] = 'UNIQUE KEY `' . (string) $indexMatch[1] . '` ' . (string) $indexMatch[2];
                    continue;
                }
                if (preg_match('/^KEY\s+`([^`]+)`\s*(\(.+\))$/i', $line, $indexMatch)) {
                    $indexes[(string) $indexMatch[1]] = 'KEY `' . (string) $indexMatch[1] . '` ' . (string) $indexMatch[2];
                }
            }

            $schema[$table] = [
                'create' => trim((string) $match[0]),
                'columns' => $columns,
                'indexes' => $indexes,
            ];
        }

        return $schema;
    }

    /** @return array{missing_tables:list<string>,missing_columns:array<string,list<string>>,missing_indexes:array<string,list<string>>,table_count:int,column_count:int,index_count:int} */
    public function inspect(): array
    {
        $missingTables = [];
        $missingColumns = [];
        $missingIndexes = [];
        $tableCount = 0;
        $columnCount = 0;
        $indexCount = 0;

        foreach ($this->expectedSchema() as $table => $definition) {
            if (!$this->tableExists($table)) {
                $missingTables[] = $table;
                continue;
            }
            ++$tableCount;

            $actualColumns = [];
            foreach ($this->query('SHOW COLUMNS FROM `' . bqSQL($table) . '`') as $row) {
                $actualColumns[(string) ($row['Field'] ?? '')] = true;
            }
            foreach (array_keys($definition['columns']) as $column) {
                ++$columnCount;
                if (!isset($actualColumns[$column])) {
                    $missingColumns[$table][] = $column;
                }
            }

            $actualIndexes = [];
            foreach ($this->query('SHOW INDEX FROM `' . bqSQL($table) . '`') as $row) {
                $actualIndexes[(string) ($row['Key_name'] ?? '')] = true;
            }
            foreach (array_keys($definition['indexes']) as $index) {
                ++$indexCount;
                if (!isset($actualIndexes[$index])) {
                    $missingIndexes[$table][] = $index;
                }
            }
        }

        return [
            'missing_tables' => $missingTables,
            'missing_columns' => $missingColumns,
            'missing_indexes' => $missingIndexes,
            'table_count' => $tableCount,
            'column_count' => $columnCount,
            'index_count' => $indexCount,
        ];
    }

    /** @return array{created_tables:int,added_columns:int,added_indexes:int,errors:list<string>} */
    public function repair(): array
    {
        $createdTables = 0;
        $addedColumns = 0;
        $addedIndexes = 0;
        $errors = [];
        $db = \Db::getInstance();

        foreach ($this->expectedSchema() as $table => $definition) {
            if (!$this->tableExists($table)) {
                if ($db->execute($definition['create'])) {
                    ++$createdTables;
                } else {
                    $errors[] = 'No se pudo crear la tabla ' . $table . ': ' . $db->getMsgError();
                    continue;
                }
            }

            $actualColumns = [];
            foreach ($this->query('SHOW COLUMNS FROM `' . bqSQL($table) . '`') as $row) {
                $actualColumns[(string) ($row['Field'] ?? '')] = true;
            }
            foreach ($definition['columns'] as $column => $columnDefinition) {
                if (isset($actualColumns[$column])) {
                    continue;
                }
                $sql = 'ALTER TABLE `' . bqSQL($table) . '` ADD COLUMN `' . bqSQL($column) . '` ' . $columnDefinition;
                if ($db->execute($sql)) {
                    ++$addedColumns;
                } else {
                    $errors[] = 'No se pudo añadir ' . $table . '.' . $column . ': ' . $db->getMsgError();
                }
            }

            $actualIndexes = [];
            foreach ($this->query('SHOW INDEX FROM `' . bqSQL($table) . '`') as $row) {
                $actualIndexes[(string) ($row['Key_name'] ?? '')] = true;
            }
            foreach ($definition['indexes'] as $index => $indexDefinition) {
                if (isset($actualIndexes[$index])) {
                    continue;
                }
                $sql = 'ALTER TABLE `' . bqSQL($table) . '` ADD ' . $indexDefinition;
                if ($db->execute($sql)) {
                    ++$addedIndexes;
                } else {
                    $errors[] = 'No se pudo añadir el índice ' . $table . '.' . $index . ': ' . $db->getMsgError();
                }
            }
        }

        return [
            'created_tables' => $createdTables,
            'added_columns' => $addedColumns,
            'added_indexes' => $addedIndexes,
            'errors' => $errors,
        ];
    }

    public function tableExists(string $table): bool
    {
        $pattern = str_replace(['\\', '_', '%'], ['\\\\', '\_', '\%'], $table);
        return $this->query("SHOW TABLES LIKE '" . pSQL($pattern) . "'") !== [];
    }

    /** @return list<array<string,mixed>> */
    private function query(string $sql): array
    {
        $rows = \Db::getInstance()->executeS($sql);
        return is_array($rows) ? $rows : [];
    }
}
