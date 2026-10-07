<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Extend the core palettes instead of replacing them, so fields of the installed Contao version and other extensions are kept
PaletteManipulator::create()
        ->addLegend('captcha_eu_legend', 'global_legend', PaletteManipulator::POSITION_AFTER)
        ->addField(['captchaEuPublicKey', 'captchaEuPrivateKey', 'captchaEuDisguiseAll'], 'captcha_eu_legend', PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('root', 'tl_page')
        ->applyToPalette('rootfallback', 'tl_page');

$GLOBALS['TL_DCA']['tl_page']['fields']['captchaEuPublicKey'] = [
        'inputType'         => 'text',
        'eval'              => ['tl_class' => 'w50 clr'],
        'sql'        => "varchar(128) NULL default ''"
];
$GLOBALS['TL_DCA']['tl_page']['fields']['captchaEuPrivateKey'] = [
        'inputType'         => 'text',
        'eval'              => ['tl_class' => 'w50'],
        'sql'        => "varchar(128) NULL default ''"
];
$GLOBALS['TL_DCA']['tl_page']['fields']['captchaEuDisguiseAll'] = [
        'inputType'         => 'checkbox',
        'eval'              => ['tl_class' => 'w50 clr'],
        'sql'               => "char(1) COLLATE ascii_bin NOT NULL default ''"
];
