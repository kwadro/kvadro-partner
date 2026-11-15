/**
 * Bring a Buyer — partner site tracker
 *
 * Embed on the partner website:
 * <script src="https://YOUR-TRACKER-HOST/js/partner-tracker.js" async></script>
 *
 * What it does:
 * 1) Reads referral from /{pageCode}/{linkKey} (e.g. /pb/my-key) or ?pr= / ?ref=
 * 2) Registers the visit via POST /api/views
 * 3) Stores a first-party cookie for later order attribution
 *
 * Read the key when placing an order:
 *   window.BringBuyer.getReferralKey()
 */
(function (window, document) {
    'use strict';

    var COOKIE_NAME = 'bb_ref';
    var COOKIE_ID_NAME = 'bb_ref_id';
    var COOKIE_DAYS = 30;
    // Full referral key = 2-char page code + client link key (3–64)
    var KEY_PATTERN = /^[a-zA-Z0-9_-]{5,66}$/;
    var PATH_PATTERN = /\/([a-z0-9]{2})\/([a-zA-Z0-9_-]{3,64})\/?$/i;

    function getScriptOrigin() {
        var script = document.currentScript;
        if (!script || !script.src) {
            var scripts = document.getElementsByTagName('script');
            for (var i = scripts.length - 1; i >= 0; i--) {
                if (scripts[i].src && scripts[i].src.indexOf('partner-tracker.js') !== -1) {
                    script = scripts[i];
                    break;
                }
            }
        }
        if (!script || !script.src) {
            return '';
        }
        try {
            return new URL(script.src).origin;
        } catch (e) {
            return '';
        }
    }

    function extractKeyFromLocation() {
        var path = window.location.pathname || '';
        var pathMatch = path.match(PATH_PATTERN);
        if (pathMatch && pathMatch[1] && pathMatch[2]) {
            return String(pathMatch[1]).toLowerCase() + pathMatch[2];
        }

        var params = new URLSearchParams(window.location.search || '');
        var fromQuery = params.get('pr') || params.get('ref') || params.get('bb_ref');
        if (fromQuery && KEY_PATTERN.test(fromQuery)) {
            return fromQuery;
        }

        return null;
    }

    function setCookie(name, value, days) {
        var maxAge = days * 24 * 60 * 60;
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie =
            name +
            '=' +
            encodeURIComponent(value) +
            '; Path=/' +
            '; Max-Age=' +
            maxAge +
            '; SameSite=Lax' +
            secure;
    }

    function getCookie(name) {
        var prefix = name + '=';
        var parts = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (part.indexOf(prefix) === 0) {
                return decodeURIComponent(part.substring(prefix.length));
            }
        }
        return null;
    }

    function registerView(apiBase, key) {
        var storageKey = 'bb_view_sent_' + key;
        try {
            if (window.sessionStorage && sessionStorage.getItem(storageKey)) {
                return Promise.resolve(null);
            }
        } catch (e) {
            // sessionStorage may be unavailable
        }

        return fetch(apiBase + '/api/views', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                key: key,
                url: window.location.href,
            }),
            credentials: 'omit',
            mode: 'cors',
            keepalive: true,
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data && result.data.ok) {
                    try {
                        if (window.sessionStorage) {
                            sessionStorage.setItem(storageKey, '1');
                        }
                    } catch (e) {}

                    if (result.data.view && result.data.view.advertisingLinkId) {
                        setCookie(COOKIE_ID_NAME, String(result.data.view.advertisingLinkId), COOKIE_DAYS);
                    }
                }
                return result;
            })
            .catch(function () {
                return null;
            });
    }

    function init() {
        var apiBase = getScriptOrigin();
        if (!apiBase) {
            return;
        }

        var keyFromUrl = extractKeyFromLocation();
        if (keyFromUrl) {
            setCookie(COOKIE_NAME, keyFromUrl, COOKIE_DAYS);
            registerView(apiBase, keyFromUrl);
        }
    }

    window.BringBuyer = {
        cookieName: COOKIE_NAME,
        cookieIdName: COOKIE_ID_NAME,
        getReferralKey: function () {
            return getCookie(COOKIE_NAME);
        },
        getAdvertisingLinkId: function () {
            return getCookie(COOKIE_ID_NAME);
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
