/**
 * Settings for the Captcha.eu SDK. Must be loaded before https://www.captcha.eu/sdk.js, which
 * reads window.CaptchaEUSettings when it starts. Existing settings of the website take precedence.
 *
 *  - KROT_HOST: the SDK would otherwise derive its API host from the script that calls KROT.init()
 *  - cspNonce:  nonce for the SDK's inline styles if the website uses a Content Security Policy
 */
(() => {
    'use strict';

    const nonce = document.currentScript?.dataset.cspNonce;

    window.CaptchaEUSettings = {
        KROT_HOST: 'https://www.captcha.eu',
        ...(nonce ? { cspNonce: nonce } : {}),
        ...(window.CaptchaEUSettings || {}),
    };
})();
