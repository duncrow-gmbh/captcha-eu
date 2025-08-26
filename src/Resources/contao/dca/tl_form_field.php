<?php

$GLOBALS['TL_DCA']['tl_form_field']['palettes']['captcha'] = str_replace('{fconfig_legend},', '{fconfig_legend},useCaptchaEu,useCaptchaEuWidget,', $GLOBALS['TL_DCA']['tl_form_field']['palettes']['captcha']);
$GLOBALS['TL_DCA']['tl_form_field']['fields'] += [
    'useCaptchaEu' => [
        
        'inputType'         => 'checkbox',
        'sql'                => "char(1) COLLATE ascii_bin NOT NULL default ''",
        'eval'              => ['tl_class' => 'w50 '],
    ],
    'useCaptchaEuWidget' => [
        
        'inputType'         => 'checkbox',
        'sql'                => "char(1) COLLATE ascii_bin NOT NULL default ''",
        'eval'              => ['tl_class' => 'w50 '],
    ],
];

    
