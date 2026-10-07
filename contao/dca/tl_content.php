<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_content']['fields']['captchaEuDisguise'] = [
    'exclude'           => true,
    'inputType'         => 'checkbox',
    'eval'              => ['tl_class' => 'w50 clr'],
    'sql'               => "char(1) COLLATE ascii_bin NOT NULL default ''",
];

PaletteManipulator::create()
    ->addField('captchaEuDisguise', 'link_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('hyperlink', 'tl_content');

PaletteManipulator::create()
    ->addField('captchaEuDisguise', 'text_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('text', 'tl_content');
