<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Security;

final class HtmlSanitizer
{
    /** @var array<string,list<string>> */
    private const ALLOWED = [
        'p' => ['class'], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => ['class'], 'ol' => ['class'], 'li' => ['class'], 'blockquote' => ['class'],
        'h2' => ['class'], 'h3' => ['class'], 'h4' => ['class'], 'span' => ['class'],
        'a' => ['href', 'title', 'target', 'rel', 'class'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading', 'class'],
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (!class_exists(\DOMDocument::class)) {
            return self::fallback($html);
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div data-fs-root="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return self::fallback($html);
        }

        $root = null;
        foreach ($document->getElementsByTagName('div') as $div) {
            if ($div->getAttribute('data-fs-root') === '1') {
                $root = $div;
                break;
            }
        }
        if (!$root instanceof \DOMElement) {
            return self::fallback($html);
        }

        self::cleanChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= (string) $document->saveHTML($child);
        }

        return trim($result);
    }

    private static function cleanChildren(\DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;
            if ($node instanceof \DOMComment || $node instanceof \DOMProcessingInstruction) {
                $parent->removeChild($node);
                $node = $next;
                continue;
            }
            if ($node instanceof \DOMElement) {
                $tag = strtolower($node->tagName);
                if (!isset(self::ALLOWED[$tag])) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'], true)) {
                        $parent->removeChild($node);
                    } else {
                        while ($node->firstChild !== null) {
                            $parent->insertBefore($node->firstChild, $node);
                        }
                        $parent->removeChild($node);
                    }
                    $node = $next;
                    continue;
                }
                self::cleanAttributes($node, $tag);
                self::cleanChildren($node);
            }
            $node = $next;
        }
    }

    private static function cleanAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (!in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($element->hasAttribute('class')) {
            $classes = preg_replace('/[^a-zA-Z0-9 _-]/', '', $element->getAttribute('class')) ?: '';
            $element->setAttribute('class', trim(substr($classes, 0, 255)));
        }
        foreach (['href', 'src'] as $urlAttribute) {
            if ($element->hasAttribute($urlAttribute)
                && !self::isSafeUrl($element->getAttribute($urlAttribute), $urlAttribute === 'href')) {
                $element->removeAttribute($urlAttribute);
            }
        }
        foreach (['width', 'height'] as $dimension) {
            if ($element->hasAttribute($dimension)) {
                $value = min(4096, max(1, (int) $element->getAttribute($dimension)));
                $element->setAttribute($dimension, (string) $value);
            }
        }
        if ($tag === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
        if ($tag === 'img') {
            $element->setAttribute('loading', 'lazy');
        }
    }

    private static function isSafeUrl(string $url, bool $allowMailto): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '../')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, $allowMailto ? ['http', 'https', 'mailto'] : ['http', 'https'], true);
    }

    private static function fallback(string $html): string
    {
        // PrestaShop requires ext-dom, but fail closed if a non-standard host omits it.
        $plainText = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return nl2br(htmlspecialchars(trim($plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
