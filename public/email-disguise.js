/**
 * Reveals the disguised e-mail addresses (see EmailDisguiser):
 *  - A click on any placeholder runs the Captcha.eu check once and reveals all placeholders of the page.
 *  - After the check, the server returns a signed verification token, which is kept in the localStorage
 *    (no cookie), so later page loads reveal everything automatically until the token expires.
 *  - Tokens are stored per website root (data-root), e.g. for /de/ and /en/ on the same domain.
 */
(() => {
    'use strict';

    const SELECTOR = '.captcha-eu-mailhide';
    const STORAGE_KEY = 'captchaEuVerification';
    const SOLUTION_TIMEOUT = 30000;
    const initializedKeys = new Set();
    let busy = false;

    const storage = {
        read: () => {
            try {
                const data = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');

                return data && typeof data === 'object' ? data : {};
            } catch (e) {
                return {};
            }
        },
        write: (data) => {
            try {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            } catch (e) {
                // Not available (e.g. private mode): the visitor is asked again on the next page
            }
        },
        // Returns the stored token of the website root if it has not expired yet
        getToken: (root) => {
            const { token, expires } = storage.read()[root] || {};

            return typeof token === 'string' && Number(expires) * 1000 > Date.now() ? token : null;
        },
        set: (root, token, expires) => {
            const data = storage.read();

            // Remove expired tokens of other websites
            Object.keys(data).forEach((key) => {
                if (!(Number(data[key]?.expires) * 1000 > Date.now())) {
                    delete data[key];
                }
            });

            data[root] = { token, expires };
            storage.write(data);
        },
        clear: (root) => {
            const data = storage.read();
            delete data[root];
            storage.write(data);
        },
    };

    const getPlaceholders = (root) => [...document.querySelectorAll(SELECTOR)].filter((el) => el.dataset.root === root);

    const setState = (placeholders, state, showError = false) => {
        placeholders.forEach((el) => {
            el.dataset.state = state;
            el.toggleAttribute('aria-busy', 'loading' === state);

            const statusElement = el.querySelector('.captcha-eu-mailhide__status');

            if (statusElement) {
                statusElement.textContent = showError && el.dataset.error ? el.dataset.error : '';
            }
        });
    };

    const getSolution = (key) => {
        if (!window.KROT || typeof window.KROT.getSolution !== 'function') {
            return Promise.reject(new Error('Captcha.eu SDK not loaded'));
        }

        // Without an explicit host, the SDK may use the domain of the script that called KROT.init()
        if (window.KROT.KROT_HOST === 'DEFAULT') {
            window.KROT.KROT_HOST = 'https://www.captcha.eu';
        }

        if (!initializedKeys.has(key)) {
            window.KROT.setup(key);
            initializedKeys.add(key);
        }

        // Do not wait forever if the SDK does not answer (e.g. blocked or rate limited)
        return Promise.race([
            Promise.resolve(window.KROT.getSolution()),
            new Promise((resolve, reject) => setTimeout(() => reject(new Error('Captcha.eu timeout')), SOLUTION_TIMEOUT)),
        ]);
    };

    /**
     * Sends all placeholders of a website root to the server and replaces them with the revealed
     * content. Either a captcha solution or the stored verification token is sent.
     *
     * @returns {Promise<boolean>} false if the server rejected the solution or token
     */
    const revealAll = async (root, placeholders, { solution = null, token = null } = {}) => {
        const response = await fetch(placeholders[0].dataset.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                payloads: placeholders.map((el) => el.dataset.payload),
                solution: null === solution ? '' : JSON.stringify(solution),
                token: token ?? '',
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.status !== 'OK' || !Array.isArray(data.html)) {
            return false;
        }

        // A new check was successful: remember it
        if (typeof data.token === 'string' && data.expires) {
            storage.set(root, data.token, data.expires);
        }

        placeholders.forEach((el, i) => {
            if (typeof data.html[i] !== 'string') {
                setState([el], 'error', true);
                return;
            }

            const template = document.createElement('template');
            template.innerHTML = data.html[i];
            el.replaceWith(template.content);
        });

        return true;
    };

    // Reveals with the stored token; an invalid token is removed (e.g. new secret or expired)
    const revealWithToken = async (root, placeholders) => {
        const token = storage.getToken(root);

        if (null === token) {
            return false;
        }

        if (await revealAll(root, placeholders, { token })) {
            return true;
        }

        storage.clear(root);

        return false;
    };

    const revealByClick = async (clicked) => {
        const root = clicked.dataset.root;
        const placeholders = getPlaceholders(root);

        if (busy || !placeholders.length) {
            return;
        }

        busy = true;
        setState(placeholders, 'loading');

        try {
            // Content added after the page load: a stored token is enough
            if (await revealWithToken(root, placeholders)) {
                return;
            }

            const solution = await getSolution(clicked.dataset.key);

            if (!(await revealAll(root, placeholders, { solution }))) {
                throw new Error('Captcha.eu verification failed');
            }
        } catch (e) {
            setState(getPlaceholders(root), 'error', true);
        } finally {
            busy = false;
        }
    };

    // Already verified: reveal everything without a new check
    const revealIfVerified = async () => {
        const roots = new Set([...document.querySelectorAll(SELECTOR)].map((el) => el.dataset.root));

        for (const root of roots) {
            const placeholders = getPlaceholders(root);

            if (null === storage.getToken(root)) {
                continue;
            }

            setState(placeholders, 'loading');

            try {
                if (await revealWithToken(root, placeholders)) {
                    continue;
                }
            } catch (e) {
                // Network error: keep the token, the placeholders can still be clicked
            }

            setState(getPlaceholders(root), '');
        }
    };

    document.addEventListener('click', (event) => {
        const el = event.target.closest(SELECTOR);

        if (el) {
            event.preventDefault();
            revealByClick(el);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const el = event.target.closest(SELECTOR);

        if (el) {
            event.preventDefault();
            revealByClick(el);
        }
    });

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', revealIfVerified);
    } else {
        revealIfVerified();
    }
})();
