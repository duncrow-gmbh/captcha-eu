<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\EmailDisguise;

/**
 * Replaces links (and plain text e-mail addresses wrapped in a span) marked with the CSS class
 * "captcha-eu-disguise" by an encrypted placeholder. The original content is only handed out
 * again after a successful captcha.eu check.
 */
final class EmailDisguiser
{
    public const MARKER_CLASS = 'captcha-eu-disguise';

    private const OPENING_TAG_REGEX = '~<a\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~i';
    private const CLASS_REGEX = '~\sclass\s*=\s*(["\'])(.*?)\1~is';
    private const HREF_REGEX = '~\shref\s*=\s*(["\'])(.*?)\1~is';

    // Marked links, and plain text e-mail addresses wrapped by markEmails()
    private const PROCESS_REGEX = '~<a\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>.*?</a\s*>|<span class="' . self::MARKER_CLASS . '">([^<]*)</span>~is';

    // Links, already marked addresses, insert tags (replaced later, e.g. {{email::…}}), raw text elements,
    // comments and tags; everything in between is text
    private const SPLIT_REGEX = '~(<a\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>.*?</a\s*>|<span class="' . self::MARKER_CLASS . '">[^<]*</span>|\{\{[^{}]*\}\}|<script\b.*?</script\s*>|<style\b.*?</style\s*>|<textarea\b.*?</textarea\s*>|<select\b.*?</select\s*>|<title\b.*?</title\s*>|<!--.*?-->|<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)~is';

    // A run of e-mail characters, which may be encoded as HTML entities by Contao (see StringUtil::encodeEmail()).
    // Unicode letters and digits are allowed for internationalized addresses (e.g. müller@bäckerei.at).
    private const ENCODED_RUN_REGEX = '~(?:&#\d+;|&#x[0-9a-f]+;|[\p{L}\p{N}._%+@-])+~iu';
    private const EMAIL_REGEX = '~[\p{L}\p{N}._%+-]+@[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)*\.\p{L}{2,63}~iu';

    public function __construct(private readonly PayloadCrypt $crypt)
    {
    }

    public function hasMarkers(string $html): bool
    {
        return str_contains($html, self::MARKER_CLASS);
    }

    /**
     * Adds the marker class to every link in the given HTML.
     */
    public function addMarker(string $html): string
    {
        return preg_replace_callback(
            self::OPENING_TAG_REGEX,
            fn (array $matches): string => $this->addMarkerToTag($matches[0]),
            $html
        ) ?? $html;
    }

    /**
     * Marks all e-mail addresses in the given HTML: mailto links get the marker class and
     * plain text addresses are wrapped in a marked span. Other links are left untouched.
     */
    public function markEmails(string $html): string
    {
        $parts = preg_split(self::SPLIT_REGEX, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (false === $parts) {
            return $html;
        }

        $result = '';

        // Even indexes are text, odd indexes are the captured links, tags, comments and raw text elements
        foreach ($parts as $i => $part) {
            if (0 === $i % 2) {
                $result .= $this->wrapPlainEmails($part);
            } else {
                $result .= str_starts_with(strtolower($part), '<a') && $this->isMailtoLink($part) ? $this->addMarker($part) : $part;
            }
        }

        return $result;
    }

    /**
     * @param array{rootId: int, publicKey: string, endpoint: string, title: string, linkLabel: string, error: string} $options
     */
    public function process(string $html, array $options, bool|null &$replaced = null): string
    {
        $replaced = false;

        $result = preg_replace_callback(
            self::PROCESS_REGEX,
            function (array $matches) use ($options, &$replaced): string {
                // Plain text e-mail address
                if (isset($matches[1])) {
                    $replaced = true;
                    $email = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    return $this->buildPlaceholder(
                        $matches[1],
                        preg_match(self::EMAIL_REGEX, $email) ? $this->maskEmail($email) : $options['linkLabel'],
                        $options
                    );
                }

                $link = $matches[0];

                if (!preg_match(self::OPENING_TAG_REGEX, $link, $tag) || !preg_match(self::CLASS_REGEX, $tag[0], $class)) {
                    return $link;
                }

                $classes = preg_split('/\s+/', trim($class[2]));

                if (!\in_array(self::MARKER_CLASS, $classes, true)) {
                    return $link;
                }

                // Remove the marker, so the revealed link is not processed again
                $classes = array_diff($classes, [self::MARKER_CLASS]);
                $cleanTag = str_replace($class[0], $classes ? ' class=' . $class[1] . implode(' ', $classes) . $class[1] : '', $tag[0]);
                $replaced = true;

                return $this->buildPlaceholder(
                    $cleanTag . substr($link, \strlen($tag[0])),
                    $this->getDisplayText($cleanTag, $options['linkLabel']),
                    $options
                );
            },
            $html
        );

        if (null === $result || !$replaced) {
            $replaced = false;

            return $html;
        }

        return $result;
    }

    private function addMarkerToTag(string $tag): string
    {
        if (!preg_match(self::CLASS_REGEX, $tag, $class)) {
            return substr($tag, 0, 2) . ' class="' . self::MARKER_CLASS . '"' . substr($tag, 2);
        }

        if (\in_array(self::MARKER_CLASS, preg_split('/\s+/', $class[2]), true)) {
            return $tag;
        }

        return str_replace($class[0], ' class=' . $class[1] . trim($class[2] . ' ' . self::MARKER_CLASS) . $class[1], $tag);
    }

    private function isMailtoLink(string $html): bool
    {
        if (!preg_match(self::OPENING_TAG_REGEX, $html, $tag) || !preg_match(self::HREF_REGEX, $tag[0], $href)) {
            return false;
        }

        return 0 === stripos(html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'mailto:');
    }

    private function wrapPlainEmails(string $text): string
    {
        if ('' === $text) {
            return $text;
        }

        return preg_replace_callback(
            self::ENCODED_RUN_REGEX,
            function (array $matches): string {
                $decoded = html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if (!preg_match(self::EMAIL_REGEX, $decoded, $email, PREG_OFFSET_CAPTURE)) {
                    return $matches[0];
                }

                [$address, $offset] = $email[0];

                // The address stays entity encoded, in case the placeholder is not rendered (e.g. missing keys)
                return $this->escape(substr($decoded, 0, $offset))
                    . '<span class="' . self::MARKER_CLASS . '">' . $this->encodeEntities($address) . '</span>'
                    . $this->escape(substr($decoded, $offset + \strlen($address)));
            },
            $text
        ) ?? $text;
    }

    private function encodeEntities(string $value): string
    {
        $encoded = '';

        foreach (mb_str_split($value) as $char) {
            $encoded .= '&#' . mb_ord($char) . ';';
        }

        return $encoded;
    }

    private function buildPlaceholder(string $content, string $displayText, array $options): string
    {
        $payload = $this->crypt->encrypt(['root' => $options['rootId'], 'html' => $content]);

        return sprintf(
            '<span class="captcha-eu-mailhide" role="button" tabindex="0" title="%s" data-payload="%s" data-key="%s" data-endpoint="%s" data-error="%s" data-root="%d">'
            . '<svg class="captcha-eu-mailhide__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="1em" height="1em"><path fill="currentColor" d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4Zm0 6a3 3 0 0 1 3 3v1h1v6H8v-6h1v-1a3 3 0 0 1 3-3Zm0 1.8c-.66 0-1.2.54-1.2 1.2v1h2.4v-1c0-.66-.54-1.2-1.2-1.2Z"/></svg>'
            . '<span class="captcha-eu-mailhide__text">%s</span>'
            . '<span class="captcha-eu-mailhide__status" aria-live="polite"></span>'
            . '</span>',
            $this->escape($options['title']),
            $payload,
            $this->escape($options['publicKey']),
            $this->escape($options['endpoint']),
            $this->escape($options['error']),
            $options['rootId'],
            $this->escape($displayText)
        );
    }

    private function getDisplayText(string $openingTag, string $fallback): string
    {
        if (!preg_match(self::HREF_REGEX, $openingTag, $href)) {
            return $fallback;
        }

        // Contao encodes e-mail addresses as HTML entities
        $url = html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (0 !== stripos($url, 'mailto:')) {
            return $fallback;
        }

        $email = rawurldecode(explode('?', substr($url, 7), 2)[0]);

        return str_contains($email, '@') ? $this->maskEmail($email) : $fallback;
    }

    /**
     * Only the first character is shown (e.g. "g…@…"), so the address cannot be guessed.
     */
    private function maskEmail(string $email): string
    {
        return mb_substr($email, 0, 1) . '…@…';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
