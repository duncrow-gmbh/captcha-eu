<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\CaptchaEu;

use Contao\CoreBundle\Routing\ResponseContext\Csp\CspHandler;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;

/**
 * Adds the sources the Captcha.eu SDK needs to the Content Security Policy of the current page
 * (if the website uses the Contao CSP) and returns the nonces for the scripts and the SDK's styles.
 *
 * Must be called while the page is rendered (the CSP header is written when the page is finalized).
 */
final class CaptchaEuCsp
{
    private const SOURCES = [
        'script-src' => 'https://www.captcha.eu',
        'connect-src' => 'https://www.captcha.eu https://b.captcha.eu',
        'worker-src' => 'blob:',
        'img-src' => 'data:',
    ];

    public function __construct(private readonly ResponseContextAccessor $responseContextAccessor)
    {
    }

    /**
     * @return array{script: string|null, style: string|null} The nonces (null if the CSP is not enabled)
     */
    public function allow(): array
    {
        $responseContext = $this->responseContextAccessor->getResponseContext();

        if (!$responseContext?->has(CspHandler::class)) {
            return ['script' => null, 'style' => null];
        }

        $csp = $responseContext->get(CspHandler::class);

        foreach (self::SOURCES as $directive => $source) {
            $csp->addSource($directive, $source);
        }

        return [
            'script' => $csp->getNonce('script-src'),
            'style' => $csp->getNonce('style-src'),
        ];
    }
}
