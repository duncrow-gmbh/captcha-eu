/**
 * Initializes the Captcha.eu form fields (see form_captcha_eu.html.twig):
 *  - "widget":    visible robot widget, rendered by KROT.init()
 *  - "invisible": the form submission is intercepted and solved in the background
 *
 * Fields that are added later (e.g. forms loaded via AJAX or in a modal) are initialized as well.
 */
(() => {
    'use strict';

    const SELECTOR = '[data-captcha-eu-mode]';
    const SDK_URL = 'https://www.captcha.eu/sdk.js';
    const setupKeys = new Set();
    let sdkPromise = null;
    let widgetInitScheduled = false;

    // Loads the SDK if it is not on the page yet (e.g. the first captcha field was added via AJAX)
    const loadSdk = () => {
        if (window.KROT) {
            return Promise.resolve(window.KROT);
        }

        sdkPromise ??= new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = SDK_URL;
            script.async = true;
            script.onload = () => resolve(window.KROT);
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return sdkPromise;
    };

    // KROT.init() derives the API host from the calling script (document.currentScript), which would be
    // this file on the own domain. Pin the Captcha.eu host unless it was configured (CaptchaEUSettings).
    const pinHost = (krot) => {
        if (krot.KROT_HOST === 'DEFAULT') {
            krot.KROT_HOST = 'https://www.captcha.eu';
        }
    };

    const initField = (krot, field) => {
        if (field.dataset.captchaEuInitialized) {
            return;
        }

        field.dataset.captchaEuInitialized = '1';

        if (field.dataset.captchaEuMode === 'widget') {
            // KROT.init() renders all widgets of the page, so it runs once for all fields added at the same time
            if (!widgetInitScheduled) {
                widgetInitScheduled = true;

                queueMicrotask(() => {
                    widgetInitScheduled = false;
                    krot.init();
                });
            }

            return;
        }

        const form = field.closest('form');

        if (!form || form.dataset.captchaEuIntercepted) {
            return;
        }

        if (!setupKeys.has(field.dataset.key)) {
            krot.setup(field.dataset.key);
            setupKeys.add(field.dataset.key);
        }

        form.dataset.captchaEuIntercepted = '1';
        krot.interceptForm(form);
    };

    const initFields = async (fields) => {
        if (!fields.length) {
            return;
        }

        try {
            const krot = await loadSdk();
            pinHost(krot);
            fields.forEach((field) => initField(krot, field));
        } catch (e) {
            console.error('Captcha.eu could not be loaded.', e);
        }
    };

    const findFields = (root) => [
        ...(root.matches?.(SELECTOR) ? [root] : []),
        ...(root.querySelectorAll?.(SELECTOR) ?? []),
    ];

    const start = () => {
        initFields(findFields(document));

        new MutationObserver((mutations) => {
            const fields = mutations.flatMap((mutation) => [...mutation.addedNodes].filter((node) => node instanceof Element).flatMap(findFields));
            initFields(fields.filter((field) => !field.dataset.captchaEuInitialized));
        }).observe(document.body, { childList: true, subtree: true });
    };

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
