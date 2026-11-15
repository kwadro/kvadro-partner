/**
 * Bring a Buyer — partner site tracker
 *
 * Embed on the partner website:
 * <script src="https://YOUR-TRACKER-HOST/js/partner-tracker.js" async></script>
 *
 * What it does:
 * 1) Reads referral from /{pageCode}/{linkKey} (pageCode replaces old fixed "pr",
 *    e.g. /pb/my-key, /wg/my-key) or query (?pr= / ?ref= / ?bb_ref=)
 * 2) Registers the visit via POST /api/views
 * 3) Stores a first-party cookie for later order attribution
 *
 * Read when placing an order:
 *   window.BringBuyer.getReferralKey()
 *   window.BringBuyer.parseReferralFromUrl(location.href)
 */
(function (window, document) {
    'use strict';

    var COOKIE_NAME = 'bb_ref';
    var COOKIE_ID_NAME = 'bb_ref_id';
    var COOKIE_DAYS = 30;
    // Combined key = 2-char page code + client link key (3–64)
    var KEY_PATTERN = /^[a-zA-Z0-9_-]{5,66}$/;
    var CODE_PATTERN = /^[a-z0-9]{2}$/i;
    var LINK_KEY_PATTERN = /^[a-zA-Z0-9_-]{3,64}$/;
    var PATH_TAIL_PATTERN = /\/([a-z0-9]{2})\/([a-zA-Z0-9_-]{3,64})\/?$/i;
    var PATH_ANY_PATTERN = /\/([a-z0-9]{2})\/([a-zA-Z0-9_-]{3,64})(?=\/|$)/gi;

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

    function parts(pageCode, linkKey) {
        var code = String(pageCode || '').toLowerCase();
        var key = String(linkKey || '');
        if (!CODE_PATTERN.test(code) || !LINK_KEY_PATTERN.test(key)) {
            return null;
        }
        return {
            pageCode: code,
            linkKey: key,
            referralKey: code + key,
        };
    }

    function parseReferralKey(referralKey) {
        var value = String(referralKey || '').trim();
        if (!KEY_PATTERN.test(value)) {
            return null;
        }
        return parts(value.slice(0, 2), value.slice(2));
    }

    function parsePath(path) {
        var value = String(path || '');
        var tail = value.match(PATH_TAIL_PATTERN);
        if (tail) {
            return parts(tail[1], tail[2]);
        }

        var match;
        var last = null;
        PATH_ANY_PATTERN.lastIndex = 0;
        while ((match = PATH_ANY_PATTERN.exec(value)) !== null) {
            last = match;
        }
        if (last) {
            return parts(last[1], last[2]);
        }

        return null;
    }

    function parseReferralFromUrl(url) {
        var href = String(url || '');
        if (!href) {
            return null;
        }

        try {
            var parsedUrl = new URL(href, window.location.origin);
            var fromPath = parsePath(parsedUrl.pathname || '');
            if (fromPath) {
                return fromPath;
            }

            var params = parsedUrl.searchParams;
            var fromQuery =
                params.get('pr') ||
                params.get('ref') ||
                params.get('bb_ref') ||
                params.get('key');
            if (fromQuery) {
                return parseReferralKey(fromQuery);
            }
        } catch (e) {
            var pathOnly = href.split('?')[0];
            var fromLoosePath = parsePath(pathOnly);
            if (fromLoosePath) {
                return fromLoosePath;
            }
        }

        return null;
    }

    function extractKeyFromLocation() {
        var parsed = parseReferralFromUrl(window.location.href);
        return parsed ? parsed.referralKey : null;
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
        var partsCookie = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < partsCookie.length; i++) {
            var part = partsCookie[i].trim();
            if (part.indexOf(prefix) === 0) {
                return decodeURIComponent(part.substring(prefix.length));
            }
        }
        return null;
    }

    function registerView(apiBase, key, pageUrl) {
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
                url: pageUrl || window.location.href,
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

        var parsed = parseReferralFromUrl(window.location.href);
        if (parsed) {
            setCookie(COOKIE_NAME, parsed.referralKey, COOKIE_DAYS);
            registerView(apiBase, parsed.referralKey, window.location.href);
        }
    }

    window.BringBuyer = {
        cookieName: COOKIE_NAME,
        cookieIdName: COOKIE_ID_NAME,
        parseReferralFromUrl: parseReferralFromUrl,
        parseReferralKey: parseReferralKey,
        getReferralKey: function () {
            return getCookie(COOKIE_NAME);
        },
        getReferralParts: function () {
            var key = getCookie(COOKIE_NAME);
            return key ? parseReferralKey(key) : null;
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
