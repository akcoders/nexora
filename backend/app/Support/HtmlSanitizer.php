<?php

namespace App\Support;

use App\Services\LibraryOptions;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allow-list HTML cleaner for rich text written in the editor (Quill). Keeps headings, text
 * formatting, lists, quotes, code, tables, safe links, images and YouTube embeds; drops scripts,
 * event handlers, styles other than colour/alignment and any unsafe URL. Quill 2 markup (flat
 * <ol data-list> lists, code-block containers, ql-ui spans) is turned into plain semantic HTML so
 * the same content renders in the web admin and in the mobile app.
 */
class HtmlSanitizer
{
    /** @var array<string, list<string>> element => attributes allowed besides class and style */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'div' => [], 'span' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'strike' => [], 'del' => [], 'mark' => [],
        'sub' => [], 'sup' => [], 'small' => [], 'code' => [], 'pre' => ['data-language'], 'blockquote' => [],
        'ul' => [], 'ol' => ['start'], 'li' => ['data-list', 'data-checked'],
        'a' => ['href', 'target', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'caption' => [],
        'figure' => [], 'figcaption' => [],
    ];

    /** Elements removed together with everything inside them. */
    private const DROP = ['script', 'style', 'object', 'embed', 'applet', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'base', 'head', 'title', 'frame', 'frameset', 'canvas', 'audio', 'video', 'source', 'track', 'iframe'];

    /** @var list<string> CSS properties kept from style attributes */
    private const STYLE_PROPERTIES = ['color', 'background-color', 'text-align'];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);
        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->normalizeQuillMarkup($document, $root);
        $this->cleanChildren($document, $root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }
        $output = trim($output);

        return $this->isBlank($output) ? '' : $output;
    }

    /** Readable plain text of sanitised HTML, used for search and excerpts. */
    public function toPlainText(?string $html): string
    {
        $html = preg_replace('#<(br|hr)\b[^>]*>|</(p|h[1-6]|li|pre|blockquote|div|tr|figure|table)>#i', "$0\n", (string) $html) ?? '';
        $html = preg_replace('#</(td|th)>#i', '$0 ', $html) ?? '';
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s*\n\s*/u', "\n", $text) ?? '';

        return trim($text);
    }

    private function isBlank(string $html): bool
    {
        return trim(str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) === ''
            && ! preg_match('#<(img|iframe|hr)\b#i', $html);
    }

    /**
     * Quill 2 writes every list as <ol><li data-list="bullet|ordered|checked|unchecked">, code
     * as div.ql-code-block lines and adds ql-ui helper spans. Convert these to plain HTML.
     */
    private function normalizeQuillMarkup(DOMDocument $document, DOMElement $root): void
    {
        foreach ($this->elements($root, '*') as $element) {
            if ($this->hasClass($element, 'ql-ui') && $element->parentNode) {
                $element->parentNode->removeChild($element);
            }
        }

        foreach ($this->elements($root, 'div') as $container) {
            if (! $this->hasClass($container, 'ql-code-block-container') || ! $container->parentNode) {
                continue;
            }
            $lines = [];
            $language = null;
            foreach ($container->childNodes as $line) {
                if ($line instanceof DOMElement) {
                    $language ??= $line->getAttribute('data-language') ?: null;
                    $lines[] = $line->textContent;
                }
            }
            $pre = $document->createElement('pre');
            $pre->appendChild($document->createTextNode(implode("\n", $lines)));
            if ($language && $language !== 'plain') {
                $pre->setAttribute('data-language', $language);
            }
            $container->parentNode->replaceChild($pre, $container);
        }

        foreach ([...$this->elements($root, 'ol'), ...$this->elements($root, 'ul')] as $list) {
            $items = array_filter(iterator_to_array($list->childNodes), fn (DOMNode $node) => $node instanceof DOMElement && strtolower($node->nodeName) === 'li');
            if (! $list->parentNode || ! array_filter($items, fn (DOMElement $item) => $item->hasAttribute('data-list'))) {
                continue;
            }

            $current = null;
            $currentType = null;
            foreach ($items as $item) {
                $type = $item->getAttribute('data-list') ?: 'bullet';
                $group = match ($type) {
                    'ordered' => 'ordered',
                    'checked', 'unchecked' => 'check',
                    default => 'bullet',
                };
                if ($group !== $currentType) {
                    $current = $document->createElement($group === 'ordered' ? 'ol' : 'ul');
                    if ($group === 'check') {
                        $current->setAttribute('class', 'nx-checklist');
                    }
                    $list->parentNode->insertBefore($current, $list);
                    $currentType = $group;
                }
                $item->removeAttribute('data-list');
                if ($group === 'check') {
                    $item->setAttribute('data-checked', $type === 'checked' ? 'true' : 'false');
                }
                $current->appendChild($item);
            }
            $list->parentNode->removeChild($list);
        }
    }

    private function cleanChildren(DOMDocument $document, DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if (! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->nodeName);
            if ($tag === 'iframe') {
                $this->replaceVideoFrame($document, $child);

                continue;
            }
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            $this->cleanChildren($document, $child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            if (! $this->cleanAttributes($child, $tag)) {
                $node->removeChild($child);
            }
        }
    }

    /** Keep YouTube embeds (re-pointed at the privacy-enhanced player); drop every other frame. */
    private function replaceVideoFrame(DOMDocument $document, DOMElement $frame): void
    {
        $videoId = LibraryOptions::youtubeId($frame->getAttribute('src'));
        if (! $videoId) {
            $frame->parentNode?->removeChild($frame);

            return;
        }

        $clean = $document->createElement('iframe');
        $clean->setAttribute('class', 'ql-video');
        $clean->setAttribute('src', LibraryOptions::youtubeEmbedUrl($videoId));
        $clean->setAttribute('frameborder', '0');
        $clean->setAttribute('allowfullscreen', 'true');
        $clean->setAttribute('loading', 'lazy');
        $frame->parentNode?->replaceChild($clean, $frame);
    }

    /** Strip attributes that are not allowed. Returns false when the element must go (unsafe image). */
    private function cleanAttributes(DOMElement $element, string $tag): bool
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim($attribute->nodeValue ?? '');
            $keep = match (true) {
                $name === 'class' => $this->cleanClass($value),
                $name === 'style' => $this->cleanStyle($value),
                ! in_array($name, self::ALLOWED[$tag], true) => null,
                $name === 'href' => $this->safeUrl($value, ['http', 'https', 'mailto', 'tel']),
                $name === 'src' => $this->safeUrl($value, ['http', 'https']),
                $name === 'target' => $value === '_blank' ? '_blank' : null,
                in_array($name, ['width', 'height', 'colspan', 'rowspan', 'start'], true) => preg_match('/^\d{1,4}$/', $value) ? $value : null,
                $name === 'data-list' => in_array($value, ['bullet', 'ordered', 'checked', 'unchecked'], true) ? $value : null,
                $name === 'data-checked' => in_array($value, ['true', 'false'], true) ? $value : null,
                $name === 'data-language' => preg_match('/^[a-z0-9+#-]{1,30}$/i', $value) ? $value : null,
                default => mb_substr($value, 0, 300),
            };

            if ($keep === null || $keep === '') {
                // By node: removeAttribute() by name misses prefixed ones such as xmlns:x.
                $element->removeAttributeNode($attribute);
            } elseif ($keep !== $attribute->nodeValue) {
                $element->setAttribute($attribute->nodeName, $keep);
            }
        }

        if ($tag === 'img' && ! $element->hasAttribute('src')) {
            return false;
        }
        if ($tag === 'a' && preg_match('#^https?://#i', $element->getAttribute('href'))) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }

        return true;
    }

    /** Only Quill layout classes (alignment, indent, video) and the checklist marker survive. */
    private function cleanClass(string $value): ?string
    {
        $classes = array_filter(preg_split('/\s+/', $value) ?: [], fn (string $class) => preg_match('/^ql-(align|indent|direction|size|font|video)(-[a-z0-9-]+)?$/', $class) || $class === 'nx-checklist');

        return $classes ? implode(' ', $classes) : null;
    }

    private function cleanStyle(string $value): ?string
    {
        $declarations = [];
        foreach (explode(';', $value) as $declaration) {
            [$property, $propertyValue] = array_pad(array_map('trim', explode(':', $declaration, 2)), 2, '');
            $property = strtolower($property);
            if (! in_array($property, self::STYLE_PROPERTIES, true)) {
                continue;
            }
            $isSafe = $property === 'text-align'
                ? in_array(strtolower($propertyValue), ['left', 'right', 'center', 'justify'], true)
                : preg_match('/^(#[0-9a-f]{3,8}|rgba?\(\s*[\d.\s,%]+\)|[a-z]{3,20})$/i', $propertyValue) === 1;
            if ($isSafe) {
                $declarations[] = "{$property}: {$propertyValue}";
            }
        }

        return $declarations ? implode('; ', $declarations) : null;
    }

    /**
     * A URL with one of the allowed schemes, or a relative path / page anchor. Control characters
     * and spaces are ignored while checking so "java\tscript:" is caught.
     *
     * @param  list<string>  $schemes
     */
    private function safeUrl(string $url, array $schemes): ?string
    {
        $compact = preg_replace('/[\x00-\x20\x7F]+/', '', $url) ?? '';
        if ($compact === '') {
            return null;
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $compact, $match)) {
            return in_array(strtolower($match[1]), $schemes, true) ? $url : null;
        }

        if (str_starts_with($compact, '//')) {
            return 'https:'.$compact;
        }
        if (str_starts_with($compact, '/\\')) {
            return null;
        }

        return str_starts_with($compact, '/') || str_starts_with($compact, '#') ? $url : null;
    }

    private function hasClass(DOMElement $element, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', $element->getAttribute('class')) ?: [], true);
    }

    /** @return list<DOMElement> a static copy, safe to modify while looping */
    private function elements(DOMElement $root, string $tag): array
    {
        return iterator_to_array($root->getElementsByTagName($tag), false);
    }
}
