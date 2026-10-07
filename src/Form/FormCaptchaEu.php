<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\Form;

use Contao\FormCaptcha;
use Contao\Input;
use Contao\PageModel;
use Contao\System;
use DuncrowGmbh\CaptchaEu\CaptchaEu\CaptchaEuClient;
use DuncrowGmbh\CaptchaEu\CaptchaEu\CaptchaEuCsp;
use DuncrowGmbh\CaptchaEu\CaptchaEu\CaptchaEuKeys;

/**
 * Replaces the core captcha form field. If "Use Captcha.eu" is enabled and the website root has
 * Captcha.eu keys, the captcha.eu check is used, otherwise the default Contao captcha.
 *
 * The template (form_captcha_eu.html.twig) gets all non-private properties of this class.
 */
class FormCaptchaEu extends FormCaptcha
{
    protected $strTemplate = 'form_captcha_eu';

    protected string $recaptchaType = 'invisible';

    protected string|null $publicKey = null;

    protected string|null $privateKey = null;

    // Nonces of the Content Security Policy (null if the website does not use the Contao CSP)
    protected string|null $cspScriptNonce = null;

    protected string|null $cspStyleNonce = null;

    public function __construct($arrAttributes = null)
    {
        parent::__construct($arrAttributes);

        $request = System::getContainer()->get('request_stack')->getCurrentRequest();
        $page = $request?->attributes->get('pageModel');
        $isFrontend = $page instanceof PageModel;

        if ($isFrontend) {
            $rootPage = PageModel::findById($page->rootId);
            $this->recaptchaType = $this->useCaptchaEuWidget ? 'widget' : 'invisible';
            $keys = System::getContainer()->get(CaptchaEuKeys::class);
            $this->publicKey = $rootPage ? $keys->getPublicKey($rootPage) : null;
            $this->privateKey = $rootPage ? $keys->getRestKey($rootPage) : null;
        }

        // In the front end, the default captcha must be rendered whenever it is also validated (e.g. missing keys).
        // In the back end, the Captcha.eu template shows the configuration hint instead.
        if (!$this->useCaptchaEu || ($isFrontend && $this->useFallback())) {
            $this->strTemplate = 'form_captcha';
        }
    }

    public function parse($arrAttributes = null)
    {
        // Allow the Captcha.eu SDK in the Content Security Policy of the page
        if (!$this->useFallback()) {
            ['script' => $this->cspScriptNonce, 'style' => $this->cspStyleNonce] = System::getContainer()->get(CaptchaEuCsp::class)->allow();
        }

        return parent::parse($arrAttributes);
    }

    public function validate(): void
    {
        if ($this->useFallback()) {
            parent::validate();

            return;
        }

        // The solution is only forwarded to captcha.eu (never output), so the unfiltered value is used
        $solution = Input::postUnsafeRaw('captcha_at_solution') ?? Input::postUnsafeRaw('captcha_at_hidden_field');
        $solution = \is_string($solution) ? $solution : '';

        if (!$this->getClient()->validate($solution, (string) $this->privateKey)) {
            $this->class = 'error';
            $this->addError($GLOBALS['TL_LANG']['ERR']['catpchaEu']);
        }
    }

    protected function useFallback(): bool
    {
        return !$this->publicKey || !$this->privateKey || !$this->useCaptchaEu;
    }

    private function getClient(): CaptchaEuClient
    {
        return System::getContainer()->get(CaptchaEuClient::class);
    }
}
