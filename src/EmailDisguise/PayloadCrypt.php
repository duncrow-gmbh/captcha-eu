<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\EmailDisguise;

/**
 * Authenticated encryption (AES-256-GCM) of the disguised HTML fragments. The key is derived
 * from the kernel secret, so payloads cannot be decrypted or forged without server access.
 */
final class PayloadCrypt
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private readonly string $key;

    public function __construct(string $secret)
    {
        $this->key = hash_hmac('sha256', 'duncrow-captcha-eu-email-disguise', $secret, true);
    }

    public function encrypt(array $data): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $cipherText = openssl_encrypt(json_encode($data, JSON_THROW_ON_ERROR), self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if (false === $cipherText) {
            throw new \RuntimeException('Could not encrypt the Captcha.eu payload.');
        }

        return rtrim(strtr(base64_encode($iv . $tag . $cipherText), '+/', '-_'), '=');
    }

    public function decrypt(string $payload): array|null
    {
        $raw = base64_decode(strtr($payload, '-_', '+/'), true);

        if (false === $raw || \strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $json = openssl_decrypt(substr($raw, self::IV_LENGTH + self::TAG_LENGTH), self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if (false === $json) {
            return null;
        }

        $data = json_decode($json, true);

        return \is_array($data) ? $data : null;
    }
}
