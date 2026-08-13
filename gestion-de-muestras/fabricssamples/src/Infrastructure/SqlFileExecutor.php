<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Infrastructure;

final class SqlFileExecutor
{
    /** @var array{success:bool,executed:int,failed_query:int,error:string} */
    private array $lastReport = [
        'success' => true,
        'executed' => 0,
        'failed_query' => 0,
        'error' => '',
    ];

    public function execute(string $file, array $replacements = []): bool
    {
        return $this->executeDetailed($file, $replacements)['success'];
    }

    /** @return array{success:bool,executed:int,failed_query:int,error:string} */
    public function executeDetailed(string $file, array $replacements = []): array
    {
        $sql = @file_get_contents($file);
        if ($sql === false) {
            return $this->lastReport = [
                'success' => false,
                'executed' => 0,
                'failed_query' => 0,
                'error' => 'No se pudo leer el archivo SQL: ' . $file,
            ];
        }

        if ($replacements !== []) {
            $sql = str_replace(array_keys($replacements), array_values($replacements), $sql);
        }

        $queries = $this->splitStatements($sql);
        $executed = 0;
        foreach ($queries as $index => $query) {
            if (!\Db::getInstance()->execute($query)) {
                return $this->lastReport = [
                    'success' => false,
                    'executed' => $executed,
                    'failed_query' => $index + 1,
                    'error' => \Db::getInstance()->getMsgError(),
                ];
            }
            ++$executed;
        }

        return $this->lastReport = [
            'success' => true,
            'executed' => $executed,
            'failed_query' => 0,
            'error' => '',
        ];
    }

    /** @return array{success:bool,executed:int,failed_query:int,error:string} */
    public function getLastReport(): array
    {
        return $this->lastReport;
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $escaped = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; ++$i) {
            $char = $sql[$i];
            if ($escaped) {
                $buffer .= $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\' && $quote !== null) {
                $buffer .= $char;
                $escaped = true;
                continue;
            }
            if (($char === "'" || $char === '"' || $char === '`')) {
                if ($quote === null) {
                    $quote = $char;
                } elseif ($quote === $char) {
                    $quote = null;
                }
                $buffer .= $char;
                continue;
            }
            if ($char === ';' && $quote === null) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
