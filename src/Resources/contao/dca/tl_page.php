<?php

$GLOBALS['TL_DCA']['tl_page']['palettes']['root'] ='{title_legend},title,type;{routing_legend},alias;{meta_legend},pageTitle;{url_legend},dns,useSSL,urlPrefix,urlSuffix,validAliasCharacters,useFolderUrl;{language_legend},language,fallback,disableLanguageRedirect;{website_legend:hide},maintenanceMode;{csp_legend},enableCsp;{global_legend:hide},mailerTransport,enableCanonical,adminEmail,dateFormat,timeFormat,datimFormat,staticFiles,staticPlugins;{captcha_eu_legend},captchaEuPublicKey,captchaEuPrivateKey;{protected_legend:hide},protected;{layout_legend},includeLayout;{twoFactor_legend:hide},enforceTwoFactor;{cache_legend:hide},includeCache;{chmod_legend:hide},includeChmod;{publish_legend},published,start,stop';
$GLOBALS['TL_DCA']['tl_page']['palettes']['rootfallback'] ='{title_legend},title,type;{routing_legend},alias;{meta_legend},pageTitle;{url_legend},dns,useSSL,urlPrefix,urlSuffix,validAliasCharacters,useFolderUrl;{language_legend},language,fallback,disableLanguageRedirect;{website_legend:hide},favicon,robotsTxt,maintenanceMode;{csp_legend},enableCsp;{global_legend:hide},mailerTransport,enableCanonical,adminEmail,dateFormat,timeFormat,datimFormat,staticFiles,staticPlugins;{captcha_eu_legend},captchaEuPublicKey,captchaEuPrivateKey;{protected_legend:hide},protected;{layout_legend},includeLayout;{twoFactor_legend:hide},enforceTwoFactor;{cache_legend:hide},includeCache;{chmod_legend:hide},includeChmod;{publish_legend},published,start,stop';

$GLOBALS['TL_DCA']['tl_page']['fields']['captchaEuPublicKey'] = [
        'label'             => &$GLOBALS['TL_LANG']['tl_settings']['captchaEuPublicKey'],
        'inputType'         => 'text',
        'eval'              => ['tl_class' => 'w50 clr'],
        'sql'        => "varchar(128) NULL default ''"
];
$GLOBALS['TL_DCA']['tl_page']['fields']['captchaEuPrivateKey'] = [
        'label'             => &$GLOBALS['TL_LANG']['tl_settings']['captchaEuPrivateKey'],
        'inputType'         => 'text',
        'eval'              => ['tl_class' => 'w50'],
        'sql'        => "varchar(128) NULL default ''"
];

