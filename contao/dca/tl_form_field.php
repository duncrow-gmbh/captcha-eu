<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addField(['useCaptchaEu', 'useCaptchaEuWidget'], 'fconfig_legend', PaletteManipulator::POSITION_PREPEND)
    ->applyToPalette('captcha', 'tl_form_field');

$GLOBALS['TL_DCA']['tl_form_field']['fields']['useCaptchaEu'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) COLLATE ascii_bin NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_form_field']['fields']['useCaptchaEuWidget'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) COLLATE ascii_bin NOT NULL default ''",
];
