/* Vertext CMS - Error pages (App/Views/error/*.php)
 *
 * The "Go Back" button can't use onclick="history.back()" - CSP's script-src
 * blocks inline event handlers the same way style-src blocks inline style
 * attributes, and hashes/nonces don't cover event handler attributes at all
 * (only 'unsafe-hashes' would, which isn't set). Delegated so it works
 * regardless of which error template includes this file.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-history-back]');
        if (btn) history.back();
    });
}());
