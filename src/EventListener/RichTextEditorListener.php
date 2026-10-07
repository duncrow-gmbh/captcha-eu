<?php

declare(strict_types=1);

namespace DuncrowGmbh\CaptchaEu\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

/**
 * Switches the default TinyMCE configuration to be_tinyCaptchaEu, which extends be_tinyMCE
 * with the "Disguise e-mail (Captcha.eu)" link button.
 */
#[AsHook('loadDataContainer')]
final class RichTextEditorListener
{
    public function __invoke(string $table): void
    {
        foreach ($GLOBALS['TL_DCA'][$table]['fields'] ?? [] as $name => $field) {
            if ('tinyMCE' === ($field['eval']['rte'] ?? null)) {
                $GLOBALS['TL_DCA'][$table]['fields'][$name]['eval']['rte'] = 'tinyCaptchaEu';
            }
        }
    }
}
