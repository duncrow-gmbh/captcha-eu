/**
 * Rich text editor plugin: toggles the "captcha-eu-disguise" class on the current link.
 * Marked links are encrypted in the front end and only revealed after a Captcha.eu check.
 *
 * Works with HugeRTE (Contao 6) and TinyMCE (Contao 5.7), which share the same plugin API.
 */
(() => {
    'use strict';

    const MARKER_CLASS = 'captcha-eu-disguise';
    const editorManager = window.hugerte ?? window.tinymce;

    editorManager?.PluginManager.add('captchaeu', (editor) => {
        editor.options.register('captchaeu_label', { processor: 'string', default: 'Disguise e-mail (Captcha.eu)' });

        const label = editor.options.get('captchaeu_label');
        const getLink = (node) => editor.dom.getParent(node || editor.selection.getNode(), 'a[href]');

        // Add the button next to the link buttons of the configured toolbar
        const toolbar = editor.options.get('toolbar');

        if (typeof toolbar === 'string' && !/\bcaptchaeu\b/.test(toolbar)) {
            if (/\bunlink\b/.test(toolbar)) {
                editor.options.set('toolbar', toolbar.replace(/\bunlink\b/, 'unlink captchaeu'));
            } else if (/\blink\b/.test(toolbar)) {
                editor.options.set('toolbar', toolbar.replace(/\blink\b/, 'link captchaeu'));
            } else {
                editor.options.set('toolbar', `captchaeu | ${toolbar}`);
            }
        }

        editor.ui.registry.addToggleButton('captchaeu', {
            icon: 'lock',
            tooltip: label,
            onAction: () => {
                const link = getLink();

                if (!link) {
                    return;
                }

                editor.undoManager.transact(() => editor.dom.toggleClass(link, MARKER_CLASS));
                editor.nodeChanged();
            },
            onSetup: (api) => {
                const update = () => {
                    const link = getLink();

                    api.setActive(!!link && editor.dom.hasClass(link, MARKER_CLASS));
                    api.setEnabled(!!link);
                };

                editor.on('NodeChange', update);
                update();

                return () => editor.off('NodeChange', update);
            },
        });

        // Show the toggle directly at the link when the cursor is placed inside a link
        editor.ui.registry.addContextToolbar('captchaeu', {
            predicate: (node) => !!getLink(node),
            items: 'captchaeu',
            position: 'node',
            scope: 'node',
        });

        // Highlight disguised links inside the editor
        editor.on('init', () => {
            const style = editor.getDoc().createElement('style');
            style.textContent = `a.${MARKER_CLASS}{outline:1px dashed #3390d6;outline-offset:2px}`
                + `a.${MARKER_CLASS}::after{content:"\\1F512";font-size:.75em;margin-left:.2em}`;
            editor.getDoc().head.appendChild(style);
        });
    });
})();
