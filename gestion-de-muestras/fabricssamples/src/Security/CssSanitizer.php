<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Security;

final class CssSanitizer
{
    private const MAX_LENGTH = 50000;
    private const SCOPE = '.fabric-samples-catalog';

    public static function sanitize(string $css): string
    {
        $css = trim(substr($css, 0, self::MAX_LENGTH));
        if ($css === '') {
            return '';
        }
        $css = preg_replace('~/\*.*?\*/~s', '', $css) ?: '';
        if (preg_match('/(?:<\/?style|<\/?script|@import|@charset|@namespace|@font-face|@keyframes|expression\s*\(|url\s*\(|javascript\s*:|behavior\s*:|-moz-binding)/i', $css)) {
            return '';
        }

        return trim(self::sanitizeBlocks($css));
    }

    private static function sanitizeBlocks(string $css): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($css);
        while ($offset < $length) {
            $open = strpos($css, '{', $offset);
            if ($open === false) {
                break;
            }
            $header = trim(substr($css, $offset, $open - $offset));
            $close = self::matchingBrace($css, $open);
            if ($close < 0) {
                return '';
            }
            $body = substr($css, $open + 1, $close - $open - 1);
            if (preg_match('/^@(media|supports)\b/i', $header)) {
                $condition = preg_replace('/[^a-zA-Z0-9@():,.\-\s%]/', '', $header) ?: '';
                $nested = self::sanitizeBlocks($body);
                if ($nested !== '') {
                    $output .= $condition . '{' . $nested . '}';
                }
            } elseif ($header !== '' && $header[0] !== '@') {
                $selectors = [];
                foreach (explode(',', $header) as $selector) {
                    $selector = trim($selector);
                    if ($selector === '' || preg_match('/(?:^|[\s>+~])(?:html|body|head|:root)(?:$|[\s>+~.:#\[])/i', $selector)) {
                        continue;
                    }
                    if (!preg_match('/^[a-zA-Z0-9_.*:#>+~\[\]="\'()\-\s]+$/', $selector)) {
                        continue;
                    }
                    $selectors[] = str_starts_with($selector, self::SCOPE)
                        ? $selector
                        : self::SCOPE . ' ' . $selector;
                }
                $declarations = self::sanitizeDeclarations($body);
                if ($selectors !== [] && $declarations !== '') {
                    $output .= implode(',', $selectors) . '{' . $declarations . '}';
                }
            }
            $offset = $close + 1;
        }
        return $output;
    }

    private static function sanitizeDeclarations(string $body): string
    {
        if (str_contains($body, '{') || str_contains($body, '}')) {
            return '';
        }
        $safe = [];
        foreach (explode(';', $body) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            if (!preg_match('/^--fs-[a-z0-9-]+$|^-?[a-z][a-z0-9-]*$/i', $property)) {
                continue;
            }
            if (preg_match('/(?:url\s*\(|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding|[<>`])/i', $value)) {
                continue;
            }
            $value = preg_replace('/[^a-zA-Z0-9#(),.%!_\-\s\/' . '"\']/', '', $value) ?: '';
            if ($value !== '') {
                $safe[] = strtolower($property) . ':' . trim($value);
            }
        }
        return implode(';', $safe) . ($safe !== [] ? ';' : '');
    }

    private static function matchingBrace(string $css, int $open): int
    {
        $depth = 0;
        for ($index = $open, $length = strlen($css); $index < $length; ++$index) {
            if ($css[$index] === '{') {
                ++$depth;
            } elseif ($css[$index] === '}' && --$depth === 0) {
                return $index;
            }
        }
        return -1;
    }
}
