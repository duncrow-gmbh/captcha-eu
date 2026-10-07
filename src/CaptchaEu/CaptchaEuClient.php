<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\CaptchaEu;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CaptchaEuClient
{
    public const PLUGIN_VERSION = '2.0.0';

    private const VALIDATE_URL = 'https://www.captcha.eu/validate';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * Validates a captcha.eu solution (the JSON string produced by the KROT SDK) against the REST key.
     */
    public function validate(string $solution, string $restKey): bool
    {
        if ('' === $solution || '' === $restKey) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VALIDATE_URL, [
                'body' => $solution,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Rest-Key' => $restKey,
                    'X-Partner-ID' => 'duncrow',
                    'X-Platform' => 'contao',
                    'X-Plugin' => 'duncrow-gmbh-captcha-eu',
                    'X-Plugin-Version' => self::PLUGIN_VERSION,
                ],
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();

            // A wrong solution is answered with 200 and success=false; other statuses point to a problem
            // (e.g. 401/403 wrong REST key, 429 rate limit, 5xx captcha.eu unavailable)
            if ($status >= 300) {
                $this->logger?->warning(\sprintf('Captcha.eu validation returned HTTP %d: %s', $status, mb_substr($response->getContent(false), 0, 200)));

                return false;
            }

            $result = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            $this->logger?->error('Captcha.eu validation request failed: '.$e->getMessage());

            return false;
        }

        return !empty($result['success']);
    }
}
