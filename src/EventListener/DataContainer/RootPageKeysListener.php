<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use DuncrowGmbh\CaptchaEu\CaptchaEu\CaptchaEuKeys;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Shows in the root page settings if a Captcha.eu key is overridden by an environment variable.
 */
#[AsCallback(table: 'tl_page', target: 'config.onload')]
final class RootPageKeysListener
{
    private const FIELDS = [
        'captchaEuPublicKey' => CaptchaEuKeys::PUBLIC_KEY_ENV,
        'captchaEuPrivateKey' => CaptchaEuKeys::REST_KEY_ENV,
    ];

    public function __construct(
        private readonly CaptchaEuKeys $keys,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(DataContainer|null $dc = null): void
    {
        if (!$dc?->id) {
            return;
        }

        foreach (self::FIELDS as $field => $env) {
            if (null === ($name = $this->keys->getOverridingEnv($env, (int) $dc->id))) {
                continue;
            }

            $GLOBALS['TL_DCA']['tl_page']['fields'][$field]['label'] = [
                $this->translator->trans("tl_page.$field.0", [], 'contao_tl_page'),
                $this->translator->trans('tl_page.captchaEuEnvOverride', [$name], 'contao_tl_page'),
            ];
        }
    }
}
