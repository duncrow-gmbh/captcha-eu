<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use DuncrowGmbh\CaptchaEu\CaptchaEu\CaptchaEuClient;
use DuncrowGmbh\CaptchaEu\CaptchaEu\CaptchaEuKeys;
use DuncrowGmbh\CaptchaEu\EmailDisguise\PayloadCrypt;
use DuncrowGmbh\CaptchaEu\EmailDisguise\VerificationToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Returns the decrypted content of all disguised placeholders of a page, either after a
 * successful captcha.eu check or if the visitor has already been verified (valid token).
 * After a check, a signed verification token is returned, which the browser keeps in its
 * localStorage and sends with later requests (no cookie is used).
 *
 * Request:  {"payloads": ["…", "…"], "solution": "…", "token": "…"}   (solution or a valid token)
 * Response: {"status": "OK", "html": ["…", null], "token": "…", "expires": 1234567890}
 *           (null for payloads that could not be decrypted, token/expires only after a new check)
 */
#[AsController]
#[Route('/_captcha-eu/reveal', name: 'duncrow_captcha_eu_reveal', methods: ['POST'])]
final class RevealController
{
    private const MAX_PAYLOADS = 200;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly PayloadCrypt $crypt,
        private readonly CaptchaEuClient $client,
        private readonly VerificationToken $verificationToken,
        private readonly CaptchaEuKeys $keys,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $payloads = $data['payloads'] ?? null;
        $solution = $data['solution'] ?? '';
        $token = $data['token'] ?? '';

        if (!\is_array($payloads) || !array_is_list($payloads) || [] === $payloads || \count($payloads) > self::MAX_PAYLOADS || !\is_string($solution) || !\is_string($token)) {
            return $this->failed(400);
        }

        $rootId = null;
        $html = [];

        // All placeholders of a page belong to the same website root
        foreach ($payloads as $payload) {
            $decrypted = \is_string($payload) ? $this->crypt->decrypt($payload) : null;

            if (null === $decrypted || !isset($decrypted['root'], $decrypted['html']) || ($rootId ?? $decrypted['root']) !== $decrypted['root']) {
                $html[] = null;
                continue;
            }

            $rootId = (int) $decrypted['root'];
            $html[] = (string) $decrypted['html'];
        }

        if (null === $rootId) {
            return $this->failed(400);
        }

        $this->framework->initialize();

        $rootPage = $this->framework->getAdapter(PageModel::class)->findById($rootId);

        $restKey = null === $rootPage ? null : $this->keys->getRestKey($rootPage);

        if (null === $restKey) {
            return $this->failed(400);
        }

        // Already verified: no new captcha check needed
        if ('' !== $token && $this->verificationToken->isValid($token, $rootId)) {
            return $this->json(['status' => 'OK', 'html' => $html], 200);
        }

        // An invalid or expired token is answered with 403 as well, so the browser removes it
        if ('' === $solution || !$this->client->validate($solution, $restKey)) {
            return $this->failed(403);
        }

        $now = time();

        return $this->json([
            'status' => 'OK',
            'html' => $html,
            'token' => $this->verificationToken->create($rootId, $now),
            'expires' => $now + VerificationToken::LIFETIME,
        ], 200);
    }

    private function failed(int $status): JsonResponse
    {
        return $this->json(['status' => 'FAILED'], $status);
    }

    private function json(array $data, int $status): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
