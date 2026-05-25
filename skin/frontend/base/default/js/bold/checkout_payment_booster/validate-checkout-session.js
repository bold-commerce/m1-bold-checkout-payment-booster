/**
 * Bold Checkout Payment Booster — session / public order ID validator.
 *
 * Usage (browser console on checkout):
 *   BoldCheckoutSessionValidator.validate()
 *   BoldCheckoutSessionValidator.validate({ verbose: true })
 *   BoldCheckoutSessionValidator.watch({ intervalMs: 2000 })
 *   BoldCheckoutSessionValidator.stopWatch()
 *
 * Load from skin:
 *   /skin/frontend/base/default/js/bold/checkout_payment_booster/validate-checkout-session.js
 */
(function (global) {
    'use strict';

    var ENDPOINTS = {
        checkoutSession: '/checkoutpaymentbooster/index/getCheckoutSession',
        cartData: '/checkoutpaymentbooster/index/getCartData'
    };

    function getFormKey() {
        var input = document.querySelector('input[name="form_key"]');
        if (input && input.value) {
            return input.value;
        }
        if (global.FORM_KEY) {
            return global.FORM_KEY;
        }
        if (global.checkout && checkout.formKey) {
            return checkout.formKey;
        }
        return null;
    }

    function getTraceIdFromSdk() {
        if (global.boldPayments && global.boldPayments.traceId) {
            return global.boldPayments.traceId;
        }
        if (global.bold && global.bold.baseInstance && global.bold.baseInstance.boldPaymentsInstance) {
            var instance = global.bold.baseInstance.boldPaymentsInstance;
            if (instance.traceId) {
                return instance.traceId;
            }
        }
        return null;
    }

    function collectClientState() {
        var bold = global.bold || {};
        var base = bold.baseInstance || null;

        return {
            'window.bold.publicOrderId': bold.publicOrderId || null,
            'window.bold.jwtToken': bold.jwtToken ? '(set, ' + bold.jwtToken.length + ' chars)' : null,
            'baseInstance.publicOrderId': base ? (base.publicOrderId || null) : null,
            'baseInstance.jwtToken': base && base.jwtToken ? '(set, ' + base.jwtToken.length + ' chars)' : null,
            'boldPayments.traceId': getTraceIdFromSdk(),
            'checkoutUiRenderState': bold.checkoutUiRenderState || null,
            'hasBoldPaymentUiRendered': !!bold.hasBoldPaymentUiRendered
        };
    }

    function getQuoteEmail() {
        var emailField = document.getElementById('billing:email');
        return emailField && emailField.value ? emailField.value.trim() : null;
    }

    function requestJson(url, formKey) {
        var query = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'form_key=' + encodeURIComponent(formKey);

        if (typeof global.fetch === 'function') {
            return fetch(query, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ' for ' + url);
                }
                return response.json();
            });
        }

        return new Promise(function (resolve, reject) {
            if (typeof Ajax === 'undefined' || !Ajax.Request) {
                reject(new Error('fetch and Prototype Ajax are unavailable'));
                return;
            }
            new Ajax.Request(query, {
                method: 'get',
                onSuccess: function (transport) {
                    try {
                        resolve(JSON.parse(transport.responseText));
                    } catch (e) {
                        reject(e);
                    }
                },
                onFailure: function () {
                    reject(new Error('Request failed for ' + url));
                }
            });
        });
    }

    function uniqueValues(values) {
        var seen = {};
        var unique = [];
        values.forEach(function (value) {
            if (value && !seen[value]) {
                seen[value] = true;
                unique.push(value);
            }
        });
        return unique;
    }

    function buildReport(client, sessionPayload, cartPayload, options) {
        options = options || {};
        var session = sessionPayload || {};
        var cartCheckout = cartPayload && cartPayload.bold_checkout ? cartPayload.bold_checkout : {};
        var cartEmail = cartPayload && cartPayload.customer ? cartPayload.customer.email_address : null;

        var ids = {
            'window.bold.publicOrderId': client['window.bold.publicOrderId'],
            'baseInstance.publicOrderId': client['baseInstance.publicOrderId'],
            'boldPayments.traceId': client['boldPayments.traceId'],
            'server.getCheckoutSession': session.public_order_id || null,
            'server.getCartData': cartCheckout.public_order_id || null
        };

        var idValues = uniqueValues(Object.keys(ids).map(function (key) {
            return ids[key];
        }));

        var jwtMatches = true;
        if (global.bold && global.bold.baseInstance && session.jwt_token) {
            jwtMatches = global.bold.baseInstance.jwtToken === session.jwt_token;
        }

        var issues = [];
        if (!getFormKey()) {
            issues.push('form_key not found on page');
        }
        if (!global.bold) {
            issues.push('window.bold is not defined (Bold payment JS may not have loaded)');
        }
        if (!ids['boldPayments.traceId']) {
            issues.push('boldPayments.traceId is missing (EPS SDK not initialized yet)');
        }
        if (idValues.length > 1) {
            issues.push('public order ID mismatch across client/server sources');
        }
        if (idValues.length === 0) {
            issues.push('no public order ID found anywhere');
        }
        if (session.jwt_token && global.bold && global.bold.baseInstance && !jwtMatches) {
            issues.push('JWT on baseInstance does not match server session');
        }

        var domEmail = getQuoteEmail();
        if (domEmail && cartEmail && domEmail.toLowerCase() !== String(cartEmail).toLowerCase()) {
            issues.push('quote email in DOM differs from getCartData customer email');
        }

        return {
            ok: issues.length === 0,
            timestamp: new Date().toISOString(),
            ids: ids,
            uniquePublicOrderIds: idValues,
            client: client,
            serverSession: {
                public_order_id: session.public_order_id || null,
                jwt_token: session.jwt_token ? '(set, ' + session.jwt_token.length + ' chars)' : null,
                eps_gateway_id: session.eps_gateway_id || null
            },
            serverCart: {
                public_order_id: cartCheckout.public_order_id || null,
                customer_email: cartEmail
            },
            quoteEmailDom: domEmail,
            jwtMatchesServer: jwtMatches,
            issues: issues
        };
    }

    function printReport(report, options) {
        options = options || {};
        var title = report.ok ? 'PASS — Bold checkout session is aligned' : 'FAIL — Bold checkout session issues found';

        if (typeof console.groupCollapsed === 'function') {
            console.groupCollapsed('[Bold] ' + title);
        } else {
            console.log('[Bold] ' + title);
        }

        console.log('Timestamp:', report.timestamp);
        console.log('Unique public order IDs:', report.uniquePublicOrderIds);
        console.table(report.ids);

        if (options.verbose) {
            console.log('Client state:', report.client);
            console.log('Server session:', report.serverSession);
            console.log('Server cart:', report.serverCart);
            console.log('DOM email:', report.quoteEmailDom);
        }

        if (report.issues.length) {
            console.warn('Issues:');
            report.issues.forEach(function (issue) {
                console.warn(' - ' + issue);
            });
        } else {
            console.log('All checks passed. traceId === publicOrderId === server session.');
        }

        if (typeof console.groupEnd === 'function') {
            console.groupEnd();
        }

        return report;
    }

    var watchTimer = null;
    var lastSnapshot = null;

    function snapshotKey(report) {
        return [
            report.uniquePublicOrderIds.join('|'),
            report.quoteEmailDom || '',
            report.serverCart.customer_email || ''
        ].join('::');
    }

    var validator = {
        getFormKey: getFormKey,
        collectClientState: collectClientState,

        validate: function (options) {
            options = options || {};
            var formKey = getFormKey();
            if (!formKey) {
                var failReport = {
                    ok: false,
                    timestamp: new Date().toISOString(),
                    ids: {},
                    uniquePublicOrderIds: [],
                    issues: ['form_key not found — run on checkout page while logged in / guest checkout']
                };
                printReport(failReport, options);
                return Promise.resolve(failReport);
            }

            var client = collectClientState();

            return Promise.all([
                requestJson(ENDPOINTS.checkoutSession, formKey),
                requestJson(ENDPOINTS.cartData, formKey)
            ]).then(function (results) {
                if (global.bold && global.bold.baseInstance && typeof global.bold.baseInstance.applyBoldCheckoutSession === 'function') {
                    global.bold.baseInstance.applyBoldCheckoutSession(results[0]);
                    client = collectClientState();
                }

                var report = buildReport(client, results[0], results[1], options);
                printReport(report, options);
                return report;
            }).catch(function (error) {
                console.error('[Bold] Validation request failed:', error);
                return {
                    ok: false,
                    timestamp: new Date().toISOString(),
                    issues: [error.message || String(error)]
                };
            });
        },

        watch: function (options) {
            options = options || {};
            var intervalMs = options.intervalMs || 2000;

            validator.stopWatch();
            console.log('[Bold] Watching checkout session every ' + intervalMs + 'ms. Change email/shipping to test rotation.');

            watchTimer = global.setInterval(function () {
                validator.validate({ verbose: false }).then(function (report) {
                    var key = snapshotKey(report);
                    if (lastSnapshot !== null && lastSnapshot !== key) {
                        console.log('[Bold] Session changed:');
                        printReport(report, { verbose: true });
                    }
                    lastSnapshot = key;
                });
            }, intervalMs);

            return validator.validate({ verbose: true });
        },

        stopWatch: function () {
            if (watchTimer) {
                global.clearInterval(watchTimer);
                watchTimer = null;
                lastSnapshot = null;
                console.log('[Bold] Stopped session watch.');
            }
        },

        compare: function () {
            var client = collectClientState();
            console.table(client);
            return client;
        }
    };

    global.BoldCheckoutSessionValidator = validator;

    console.log('[Bold] BoldCheckoutSessionValidator loaded. Run BoldCheckoutSessionValidator.validate()');
}(window));
