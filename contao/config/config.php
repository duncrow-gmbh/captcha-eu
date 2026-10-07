<?php

declare(strict_types=1);

use DuncrowGmbh\CaptchaEu\Form\FormCaptchaEu;

// Replaces the core captcha form field (falls back to the core captcha if Captcha.eu is not used)
$GLOBALS['TL_FFL']['captcha'] = FormCaptchaEu::class;
