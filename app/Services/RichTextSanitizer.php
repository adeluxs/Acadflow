<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitizer for rich editor HTML.
 *
 * Quill uses a small set of ql-* classes and harmless formatting styles. We
 * retain only those formatting primitives while removing scripts, forms,
 * embedded content, event handlers and unsafe URL/CSS values.
 */
class RichTextSanitizer
{
    private const ALLOWED_TAGS = [
        'p','br','strong','b','em','i','u','s','h1','h2','h3','h4',
        'blockquote','pre','code','ul','ol','li','a','span','sub','sup',
    ];

    private const ALLOWED_ATTRIBUTES = ['href','target','rel','class','style','data-list'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        if (! class_exists(DOMDocument::class)) {
            return $this->sanitizeWithoutDom($html);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="acadflow-rich-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('acadflow-rich-root');
        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function sanitizeWithoutDom(string $html): string
    {
        // Some inexpensive/shared PHP deployments omit ext-dom. Rebuild every
        // allowed opening tag from a tiny attribute allow-list instead of relying
        // on strip_tags alone (strip_tags preserves unsafe onclick/javascript attrs).
        $html = preg_replace('#<!--.*?-->#s', '', $html) ?? '';
        $html = preg_replace('#<(script|style|iframe|object|embed|svg|math|form|input|button|textarea|select)[^>]*>.*?</\1\s*>#is', '', $html) ?? '';
        $html = preg_replace('#<(script|style|iframe|object|embed|svg|math|form|input|button|textarea|select)\b[^>]*/?>#is', '', $html) ?? '';
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><h1><h2><h3><h4><blockquote><pre><code><ul><ol><li><a><span><sub><sup>');

        $html = preg_replace_callback('/<([a-z0-9]+)\b([^>]*)>/i', function (array $match): string {
            $tag = strtolower($match[1]);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                return '';
            }

            $raw = $match[2] ?? '';
            $attributes = [];

            if ($tag === 'a' && preg_match('/\bhref\s*=\s*([\"\'])(.*?)\1/is', $raw, $hrefMatch)) {
                $href = html_entity_decode(trim($hrefMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($href !== '' && preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $href)) {
                    $attributes[] = 'href="'.htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
                }
            }

            if ($tag === 'a' && preg_match('/\btarget\s*=\s*([\"\'])(.*?)\1/is', $raw, $targetMatch)) {
                $target = strtolower(trim($targetMatch[2]));
                if (in_array($target, ['_blank', '_self'], true)) {
                    $attributes[] = 'target="'.$target.'"';
                    if ($target === '_blank') {
                        $attributes[] = 'rel="noopener noreferrer"';
                    }
                }
            }

            if (preg_match('/\bclass\s*=\s*([\"\'])(.*?)\1/is', $raw, $classMatch)) {
                $safeClasses = array_values(array_filter(
                    preg_split('/\s+/', trim($classMatch[2])) ?: [],
                    fn ($class) => preg_match('/^ql-[a-z0-9-]+$/i', $class)
                ));
                if ($safeClasses) {
                    $attributes[] = 'class="'.htmlspecialchars(implode(' ', $safeClasses), ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
                }
            }

            if (preg_match('/\bstyle\s*=\s*([\"\'])(.*?)\1/is', $raw, $styleMatch)) {
                $style = $this->sanitizeStyle(html_entity_decode($styleMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($style !== '') {
                    $attributes[] = 'style="'.htmlspecialchars($style, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
                }
            }

            if ($tag === 'li' && preg_match('/\bdata-list\s*=\s*([\"\'])(.*?)\1/is', $raw, $listMatch)) {
                $list = strtolower(trim($listMatch[2]));
                if (in_array($list, ['bullet', 'ordered'], true)) {
                    $attributes[] = 'data-list="'.$list.'"';
                }
            }

            return '<'.$tag.($attributes ? ' '.implode(' ', $attributes) : '').'>';
        }, $html) ?? '';

        return trim($html);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;

            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }

                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    if (! in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                        $node->removeAttribute($attribute->name);
                        continue;
                    }

                    if ($name === 'href') {
                        $href = trim($attribute->value);
                        if ($href !== '' && ! preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $href)) {
                            $node->removeAttribute('href');
                        }
                    }

                    if ($name === 'target' && ! in_array(strtolower($attribute->value), ['_blank', '_self'], true)) {
                        $node->removeAttribute('target');
                    }

                    if ($name === 'class') {
                        $safe = array_values(array_filter(
                            preg_split('/\s+/', $attribute->value) ?: [],
                            fn ($class) => preg_match('/^ql-[a-z0-9-]+$/i', $class)
                        ));
                        if ($safe) {
                            $node->setAttribute('class', implode(' ', $safe));
                        } else {
                            $node->removeAttribute('class');
                        }
                    }

                    if ($name === 'style') {
                        $safeStyle = $this->sanitizeStyle($attribute->value);
                        if ($safeStyle !== '') {
                            $node->setAttribute('style', $safeStyle);
                        } else {
                            $node->removeAttribute('style');
                        }
                    }

                    if ($name === 'data-list') {
                        if ($tag !== 'li' || ! in_array(strtolower($attribute->value), ['bullet', 'ordered'], true)) {
                            $node->removeAttribute('data-list');
                        }
                    }

                    if ($name === 'rel') {
                        // Rebuilt below for external-target links; never trust supplied rel tokens.
                        $node->removeAttribute('rel');
                    }
                }

                if ($tag === 'a' && strtolower($node->getAttribute('target')) === '_blank') {
                    $node->setAttribute('rel', 'noopener noreferrer');
                }

                $this->cleanChildren($node);
            }

            $node = $next;
        }
    }

    private function sanitizeStyle(string $style): string
    {
        $safe = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $value = strtolower($value);

            if (in_array($property, ['color', 'background-color'], true)
                && preg_match('/^(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)|[a-z]{3,20})$/i', $value)) {
                $safe[] = $property.': '.$value;
                continue;
            }

            if ($property === 'text-align' && in_array($value, ['left', 'center', 'right', 'justify'], true)) {
                $safe[] = $property.': '.$value;
            }
        }

        return implode('; ', $safe);
    }
}
