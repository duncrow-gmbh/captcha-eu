<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\EmailDisguise;

/**
 * Signed token that remembers a successful captcha.eu check of a visitor (stored in the browser's
 * localStorage), so disguised e-mail addresses are revealed without a new check until it expires.
 *
 * Format: "<root page ID>.<expiry timestamp>.<HMAC>"
 */
final class VerificationToken
{
    public const LIFETIME = 30 * 86400;

    private readonly string $key;

    public function __construct(string $secret)
    {
        $this->key = hash_hmac('sha256', 'duncrow-captcha-eu-verification', $secret, true);
    }

    public function create(int $rootId, int|null $now = null): string
    {
        $data = $rootId.'.'.(($now ?? time()) + self::LIFETIME);

        return $data.'.'.$this->sign($data);
    }

    public function isValid(string $token, int $rootId, int|null $now = null): bool
    {
        $parts = explode('.', $token);

        if (3 !== \count($parts) || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
            return false;
        }

        [$tokenRootId, $expires, $signature] = $parts;

        return hash_equals($this->sign($tokenRootId.'.'.$expires), $signature)
            && (int) $tokenRootId === $rootId
            && (int) $expires > ($now ?? time());
    }

    private function sign(string $data): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, $this->key, true)), '+/', '-_'), '=');
    }
}
