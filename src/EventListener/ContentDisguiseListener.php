<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\EventListener;

use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use DuncrowGmbh\CaptchaEu\CaptchaEu\CaptchaEuCsp;
use DuncrowGmbh\CaptchaEu\EmailDisguise\EmailDisguiser;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Marks the e-mail addresses of content elements for disguising:
 *  - "Disguise e-mail (Captcha.eu)" on a hyperlink element: its link (whatever the target)
 *  - "Disguise e-mail (Captcha.eu)" on other elements (e.g. text): all e-mail addresses
 *  - "Disguise all e-mail addresses" on the website root: all e-mail addresses of every content element
 */
#[AsHook('getContentElement')]
final class ContentDisguiseListener
{
    public function __construct(
        private readonly EmailDisguiser $disguiser,
        private readonly RequestStack $requestStack,
        private readonly ContaoFramework $framework,
        private readonly CaptchaEuCsp $csp,
    ) {
    }

    public function __invoke(ContentModel $model, string $buffer, object $element): string
    {
        if ($model->captchaEuDisguise) {
            $buffer = 'hyperlink' === $model->type ? $this->disguiser->addMarker($buffer) : $this->disguiser->markEmails($buffer);
        } elseif ($this->isDisguiseAllEnabled()) {
            $buffer = $this->disguiser->markEmails($buffer);
        }

        // Also covers links marked in the rich text editor
        if ($this->disguiser->hasMarkers($buffer)) {
            $this->allowInContentSecurityPolicy();
        }

        return $buffer;
    }

    /**
     * The CSP header is written before the response listener replaces the marked content, so the
     * Captcha.eu sources are added now and the nonces are passed on via the request.
     */
    private function allowInContentSecurityPolicy(): void
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request || $request->attributes->has(EmailDisguiseResponseListener::CSP_NONCES_ATTRIBUTE)) {
            return;
        }

        $request->attributes->set(EmailDisguiseResponseListener::CSP_NONCES_ATTRIBUTE, $this->csp->allow());
    }

    private function isDisguiseAllEnabled(): bool
    {
        $page = $this->requestStack->getMainRequest()?->attributes->get('pageModel');

        if (!$page instanceof PageModel || !$page->rootId) {
            return false;
        }

        // No own cache (the service may live across requests); the model registry caches the root page per request
        $rootPage = $this->framework->getAdapter(PageModel::class)->findById((int) $page->rootId);

        return null !== $rootPage && (bool) $rootPage->captchaEuDisguiseAll;
    }
}
