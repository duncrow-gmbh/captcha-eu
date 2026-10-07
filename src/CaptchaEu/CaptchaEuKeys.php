<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\CaptchaEu;

use Contao\PageModel;

/**
 * Returns the Captcha.eu keys of a website root. Environment variables (e.g. in .env.local) take
 * precedence over the root page settings, so every environment (local, staging, production) can
 * use its own keys without them being overwritten when the database is synced:
 *
 *  1. CAPTCHA_EU_PUBLIC_KEY_<root page ID> / CAPTCHA_EU_REST_KEY_<root page ID>  (one website)
 *  2. CAPTCHA_EU_PUBLIC_KEY / CAPTCHA_EU_REST_KEY                                  (all websites)
 *  3. The keys entered in the root page settings
 */
final class CaptchaEuKeys
{
    public const PUBLIC_KEY_ENV = 'CAPTCHA_EU_PUBLIC_KEY';

    public const REST_KEY_ENV = 'CAPTCHA_EU_REST_KEY';

    public function getPublicKey(PageModel $rootPage): string|null
    {
        return $this->getEnv(self::PUBLIC_KEY_ENV, (int) $rootPage->id) ?? ($rootPage->captchaEuPublicKey ?: null);
    }

    public function getRestKey(PageModel $rootPage): string|null
    {
        return $this->getEnv(self::REST_KEY_ENV, (int) $rootPage->id) ?? ($rootPage->captchaEuPrivateKey ?: null);
    }

    /**
     * Returns the name of the environment variable that overrides the key, if there is one.
     */
    public function getOverridingEnv(string $name, int $rootId): string|null
    {
        foreach ([$name.'_'.$rootId, $name] as $candidate) {
            if (null !== $this->readEnv($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function getEnv(string $name, int $rootId): string|null
    {
        $env = $this->getOverridingEnv($name, $rootId);

        return null === $env ? null : $this->readEnv($env);
    }

    private function readEnv(string $name): string|null
    {
        // Symfony's Dotenv populates $_SERVER and $_ENV with the values of the .env files
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
