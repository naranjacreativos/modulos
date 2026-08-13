<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Security;

final class CsvSafeWriter
{
    /** @param resource $stream @param list<mixed> $row */
    public static function write($stream, array $row, string $separator = ';'): void
    {
        $safe = array_map([self::class, 'cell'], $row);
        if (fputcsv($stream, $safe, $separator) === false) {
            throw new \RuntimeException('No se pudo escribir la exportación CSV.');
        }
    }

    public static function cell(mixed $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string) ($value ?? ''));
        $trimmed = ltrim($value);
        if (preg_match('/^-?\d+(?:[.,]\d+)?$/', $trimmed)) {
            return $value;
        }
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
