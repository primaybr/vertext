<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * Shared rich-text HTML sanitizer (0.1.1).
 *
 * Extracted after a regex-based sanitizer (strip_tags() + preg_replace() against
 * on\w+= and href="javascript:...") was independently written twice - once in
 * Blog\Controllers\Admin\PostsController, once copied from it into
 * ThemeCustomizer\LandingBlocksHelper - and both copies had the same two bypasses:
 * an unquoted attribute value (`onerror=alert(1)`, no quotes at all) and a
 * single-quoted javascript: href (`href='javascript:alert(1)'`), since strip_tags()
 * never touches attributes and the regexes only matched one specific quote style.
 * A single shared implementation means a future fix only has to happen once.
 *
 * Parses into a real DOM tree and rebuilds every surviving tag's attributes from
 * an explicit allowlist, rather than regex-stripping the raw string - this closes
 * the whole bypass class (unusual quoting/whitespace/casing) rather than patching
 * one instance of it.
 */
class HtmlSanitizer
{
    private const MAX_LENGTH = 20000;

    /** Tags allowed to survive; everything else is unwrapped (children kept, wrapper dropped). */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li',
        'blockquote', 'pre', 'code', 'a', 'img', 'span',
    ];

    /** Attributes allowed per tag, always rebuilt from the parsed DOM node - never
     *  carried over from the raw string verbatim. */
    private const ALLOWED_ATTRS = [
        'a'   => ['href'],
        'img' => ['src', 'alt'],
    ];

    /** Sanitize a rich-text HTML fragment (e.g. Quill output) against the allowlist above. */
    public static function clean(string $html): string
    {
        $html = mb_substr(trim($html), 0, self::MAX_LENGTH);
        if ($html === '') {
            return '';
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $html . '</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }

        self::sanitizeNode($dom, $body);

        $out = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return mb_substr($out, 0, self::MAX_LENGTH);
    }

    /**
     * Validate a single URL attribute value (href/src/etc.) - only an http(s):// or
     * a leading-/ relative path survives; anything else (javascript:, data:, vbscript:,
     * bare "//host" protocol-relative, or malformed input) is rejected outright.
     * Shared so "what counts as a safe URL" has one definition, not one per caller.
     */
    public static function isSafeUrl(mixed $val, int $maxLen = 500): string
    {
        $val = trim((string) $val);
        if ($val !== '' && !preg_match('/^(https?:\/\/|\/(?!\/))/i', $val)) {
            return '';
        }
        return mb_substr($val, 0, $maxLen);
    }

    /** Recursively rebuild $node's children in place against the allowlist. */
    private static function sanitizeNode(\DOMDocument $dom, \DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                continue; // text content is always safe as-is
            }

            if (!$child instanceof \DOMElement) {
                $node->removeChild($child); // comments, processing instructions, etc.
                continue;
            }

            $tag = strtolower($child->tagName);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap: keep children (recursively sanitized first), drop the wrapper tag.
                self::sanitizeNode($dom, $child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Capture allowed attribute values BEFORE wiping - the tag survives, but every
            // attribute is rebuilt from this explicit list, never carried over verbatim.
            $allowedAttrs = self::ALLOWED_ATTRS[$tag] ?? [];
            $kept = [];
            foreach ($allowedAttrs as $attrName) {
                if ($child->hasAttribute($attrName)) {
                    $kept[$attrName] = $child->getAttribute($attrName);
                }
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attr) {
                $child->removeAttribute($attr->name);
            }

            foreach ($kept as $attrName => $value) {
                if ($attrName === 'href' || $attrName === 'src') {
                    $value = self::isSafeUrl($value);
                } elseif ($attrName === 'alt') {
                    $value = mb_substr(trim(strip_tags($value)), 0, 300);
                }
                if ($value !== '') {
                    $child->setAttribute($attrName, $value);
                }
            }

            self::sanitizeNode($dom, $child);
        }
    }
}
