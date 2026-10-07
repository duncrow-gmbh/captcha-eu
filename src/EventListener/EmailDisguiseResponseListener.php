<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\PageModel;
use DuncrowGmbh\CaptchaEu\CaptchaEu\CaptchaEuKeys;
use DuncrowGmbh\CaptchaEu\EmailDisguise\EmailDisguiser;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Replaces all content marked for disguising in the front end output by encrypted placeholders.
 */
#[AsEventListener(priority: -16)]
final class EmailDisguiseResponseListener
{
    /**
     * Request attribute with the CSP nonces, set while the page is rendered (see ContentDisguiseListener).
     */
    public const CSP_NONCES_ATTRIBUTE = '_captcha_eu_csp_nonces';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly EmailDisguiser $disguiser,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly CaptchaEuKeys $keys,
        private readonly Packages $packages,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$this->scopeMatcher->isFrontendMainRequest($event)) {
            return;
        }

        $response = $event->getResponse();

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if ('' !== $contentType && !str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();

        if (!\is_string($content) || !$this->disguiser->hasMarkers($content)) {
            return;
        }

        $request = $event->getRequest();
        $page = $request->attributes->get('pageModel');

        if (!$page instanceof PageModel) {
            return;
        }

        $this->framework->initialize();

        $rootPage = $this->framework->getAdapter(PageModel::class)->findById($page->rootId);

        // Without keys the content cannot be revealed again, so it is left untouched
        $publicKey = null === $rootPage ? null : $this->keys->getPublicKey($rootPage);

        if (null === $publicKey || null === $this->keys->getRestKey($rootPage)) {
            return;
        }

        $locale = $page->language ?: null;

        $content = $this->disguiser->process($content, [
            'rootId' => (int) $rootPage->id,
            'publicKey' => $publicKey,
            'endpoint' => $this->urlGenerator->generate('duncrow_captcha_eu_reveal'),
            'title' => $this->translator->trans('MSC.captchaEuDisguiseTitle', [], 'contao_default', $locale),
            'linkLabel' => $this->translator->trans('MSC.captchaEuDisguiseLink', [], 'contao_default', $locale),
            'error' => $this->translator->trans('MSC.captchaEuDisguiseError', [], 'contao_default', $locale),
        ], $replaced);

        if (!$replaced) {
            return;
        }

        $nonces = $request->attributes->get(self::CSP_NONCES_ATTRIBUTE, []);

        $response->setContent($this->injectAssets($content, $nonces['script'] ?? null, $nonces['style'] ?? null));
        $response->headers->remove('Content-Length');
    }

    private function injectAssets(string $content, string|null $scriptNonce, string|null $styleNonce): string
    {
        $nonce = $scriptNonce ? ' nonce="'.htmlspecialchars($scriptNonce).'"' : '';
        $css = '<link rel="stylesheet" href="'.$this->packages->getUrl('email-disguise.css', 'duncrow_gmbh_captcha_eu').'">';
        $js = '';

        // The settings must be loaded before the SDK, which reads them when it starts (deferred scripts keep their order)
        if (!str_contains($content, 'captcha.eu/sdk.js')) {
            $js .= '<script src="'.$this->packages->getUrl('captcha-eu-settings.js', 'duncrow_gmbh_captcha_eu').'"'.$nonce.($styleNonce ? ' data-csp-nonce="'.htmlspecialchars($styleNonce).'"' : '').' defer></script>';
            $js .= '<script src="https://www.captcha.eu/sdk.js"'.$nonce.' defer></script>';
        }

        $js .= '<script src="'.$this->packages->getUrl('email-disguise.js', 'duncrow_gmbh_captcha_eu').'"'.$nonce.' defer></script>';

        // The first </head> is the end of the document head, the last </body> the end of the body
        $content = $this->insertBefore($content, '</head>', $css, false);

        return $this->insertBefore($content, '</body>', $js, true);
    }

    private function insertBefore(string $content, string $needle, string $insert, bool $last): string
    {
        $pos = $last ? strripos($content, $needle) : stripos($content, $needle);

        if (false === $pos) {
            return $content.$insert;
        }

        return substr_replace($content, $insert, $pos, 0);
    }
}
