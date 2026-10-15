<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Conservative allowlist HTML sanitizer for user-authored rich content.
 *
 * Lecturers can author course material directly in AcadFlow with a rich editor.
 * That HTML is untrusted and must never be persisted verbatim. This sanitizer
 * is deliberately restrictive:
 *
 *  - keeps a small set of safe formatting tags,
 *  - removes dangerous elements (script/style/iframe/object/svg/form) entirely,
 *  - unwraps unknown tags while preserving their text,
 *  - strips event handlers and javascript: / data: URLs,
 *  - filters inline styles to a safe property + value allowlist.
 *
 * It is platform-independent: it uses DOMDocument when available and falls back
 * to a conservative regex pass otherwise.
 */
class HtmlSanitizer
{
    /** Tags kept as-is in authored content. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'small', 'mark', 'sup', 'sub',
        'blockquote', 'pre', 'code', 'hr',
        'ul', 'ol', 'li',
        'a', 'img',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    ];

    /** Tags removed with their entire content. */
    private const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'link', 'meta', 'svg', 'math', 'template', 'noscript', 'base', 'title',
        'head', 'html', 'body', 'frame', 'frameset', 'applet',
    ];

    /** Per-tag attribute allowlist. */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'ol' => ['start', 'type', 'reversed'],
        'li' => ['value'],
    ];

    /** Attributes allowed on every element (values are validated separately). */
    private const GLOBAL_ATTRS = ['class', 'style'];

    /** Inline-style properties that survive sanitization. */
    private const ALLOWED_STYLE_PROPERTIES = [
        'color', 'background-color', 'text-align', 'font-size', 'font-weight', 'font-style',
        'text-decoration', 'line-height',
        'margin', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom',
        'padding', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom',
        'text-indent', 'white-space', 'letter-spacing',
    ];

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        if (! class_exists(\DOMDocument::class)) {
            return self::cleanWithRegex($html);
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="utf-8" ?><div data-acadflow-root="1">'.$html.'</div>';
        $loaded = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return self::cleanWithRegex($html);
        }

        $root = $dom->getElementsByTagName('div')->item(0);
        if (! $root) {
            return self::cleanWithRegex($html);
        }

        $children = [];
        foreach ($root->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            self::sanitizeNode($child);
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    /** Extract plain text from an HTML fragment (for previews and search). */
    public static function toPlainText(?string $html): string
    {
        $text = (string) $html;
        if ($text === '') {
            return '';
        }
        // Drop the contents of non-content elements before flattening so preview
        // text never leaks inline scripts/styles.
        $text = preg_replace('/<(script|style|iframe|object|embed|template|noscript)\b[^>]*>.*?<\/\1\s*>/is', '', $text) ?? $text;
        $text = preg_replace('/<(br|hr)\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/(p|div|li|h[1-6]|blockquote)>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            $node->parentNode?->removeChild($node);
            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::DROP_TAGS, true)) {
            $node->parentNode?->removeChild($node);
            return;
        }

        if ($node instanceof \DOMElement) {
            self::sanitizeAttributes($node, $tag);
        }

        // Sanitize children before possibly unwrapping so nested disallowed
        // elements are handled even after the parent is replaced.
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            self::sanitizeNode($child);
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            self::unwrap($node);
        }
    }

    private static function unwrap(\DOMNode $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }

    private static function sanitizeAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRS, self::ALLOWED_ATTRS[$tag] ?? []);

        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[] = [$attribute->nodeName, (string) $attribute->nodeValue];
        }

        $remove = [];
        foreach ($attributes as [$name, $value]) {
            $name = strtolower($name);

            if (str_starts_with($name, 'on') || str_starts_with($name, 'data-') || ! in_array($name, $allowed, true)) {
                $remove[] = $name;
                continue;
            }

            if ($name === 'style') {
                $clean = self::sanitizeStyle($value);
                if ($clean === '') {
                    $remove[] = 'style';
                } else {
                    $element->setAttribute('style', $clean);
                }
                continue;
            }

            if ($name === 'class') {
                $clean = self::sanitizeClass($value);
                if ($clean === '') {
                    $remove[] = 'class';
                } else {
                    $element->setAttribute('class', $clean);
                }
                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! self::isSafeUrl($value)) {
                $remove[] = $name;
                continue;
            }

            if ($name === 'target' && ! in_array($value, ['_blank', '_self'], true)) {
                $remove[] = 'target';
            }
        }

        foreach (array_unique($remove) as $name) {
            $element->removeAttribute($name);
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function sanitizeClass(string $value): string
    {
        $tokens = preg_split('/\s+/', trim($value)) ?: [];
        $tokens = array_filter(
            $tokens,
            static fn (string $token): bool => preg_match('/^[a-z][a-z0-9_-]{0,63}$/i', $token) === 1
        );

        return implode(' ', array_values(array_unique($tokens)));
    }

    private static function sanitizeStyle(string $style): string
    {
        $out = [];
        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '' || ! str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            if (! in_array($property, self::ALLOWED_STYLE_PROPERTIES, true)) {
                continue;
            }
            if (! self::isSafeStyleValue($value)) {
                continue;
            }
            $out[] = $property.': '.$value;
        }

        return implode('; ', $out);
    }

    private static function isSafeStyleValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        if (preg_match('/[<>\\\\{}]/', $value) === 1) {
            return false;
        }
        $lower = strtolower($value);
        if (str_contains($lower, 'javascript:') || str_contains($lower, 'expression') || str_contains($lower, 'url(')) {
            return false;
        }

        return preg_match('/^[a-z0-9#%.,\s\-\/()]+$/i', $value) === 1;
    }

    private static function isSafeUrl(string $url): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }
        if (str_starts_with($url, '//')) {
            return true;
        }

        return preg_match('~^(https?:|mailto:|tel:)~i', $url) === 1;
    }

    private static function cleanWithRegex(string $html): string
    {
        $html = preg_replace(
            '/<(script|style|iframe|object|embed|form|svg|math|template|noscript|base|title|head|body)\b[^>]*>.*?<\/\1\s*>/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace('/<(script|style|iframe|object|embed|form|svg|math|template|noscript|base|title|head|body)\b[^>]*\/?>/is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript:/i', '', $html) ?? $html;

        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';

        return trim(strip_tags($html, $allowed));
    }
}
