<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class SafeHtmlService
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'a', 'img', 'figure', 'figcaption', 'sup', 'sub', 'span', 'div',
    ];

    private const GLOBAL_ATTRIBUTES = ['class', 'title', 'aria-label'];
    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'td' => ['colspan', 'rowspan'],
    ];

    public function sanitize(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        if (! class_exists(DOMDocument::class)) {
            return $this->fallback($html);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="acadflow-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('acadflow-root');
        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'], true)) {
                        $parent->removeChild($node);
                        continue;
                    }

                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    continue;
                }

                $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::TAG_ATTRIBUTES[$tag] ?? []);
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    if (! in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                        $node->removeAttribute($attribute->name);
                        continue;
                    }
                    if (in_array($name, ['href', 'src'], true) && ! $this->safeUrl($attribute->value)) {
                        $node->removeAttribute($attribute->name);
                    }
                }

                if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
                    $node->setAttribute('rel', 'noopener noreferrer');
                }
            }

            $this->cleanChildren($node);
        }
    }

    private function safeUrl(string $value): bool
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        if ($value === '' || str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    private function fallback(string $html): string
    {
        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $html = strip_tags($html, $allowed);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        return preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|data):.*?\2/i', '$1="#"', $html) ?? '';
    }
}
