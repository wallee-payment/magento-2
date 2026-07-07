/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)

 */
define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/method-list',
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Wallee_Payment/js/model/checkout-handler',
    'Wallee_Payment/js/wallee-sdk-loader',
    'mage/storage'
], function (
    $,
    Component,
    fullScreenLoader,
    methodList,
    urlBuilder,
    quote,
    additionalValidators,
    checkoutHandler,
    sdkLoader,
    storage
) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Wallee_Payment/payment/form'
        },
        redirectAfterPlaceOrder: false,
        loadingIframe: false,
        checkoutHandler: null,

        /**
         * @override
         */
        initialize: function () {
            this._super();

            if (window.checkoutConfig.wallee.integrationMethod == 'iframe') {
                this.checkoutHandler = checkoutHandler(this.getFormId(), this.isActive.bind(this), this.createIframeHandler.bind(this));
                var methods = methodList();

                //When there is only 1 active payment method magento's behaviour is not to display the iframe,
                //until the user selects the payment method by clicking on the icon.
                //These lines allow to trigger the iframe to show it by default if there is only one payment method active.
                if (methods !== null && methods.length === 1) {
                    this.checkoutHandler.updateAddresses(this._super.bind(this));
                }

                //Every time the checkout page is initialised/refreshed
                //here we are checking if there is at least one chekbox selected,
                //if the condition is met, then we will update the iframe with the user's billing address,
                //this will trigger the form to be displayed and the transaction to have a payment method selected before clicking on the place order button.
                //This will only run once, only if the checkbox is selected.
                //Note: the single-method case is already handled by the branch above, so this
                //only covers multiple methods. Using `> 1` (instead of `>= 1`) prevents both
                //branches from firing for a single method, which would trigger two concurrent
                //iframe creations and mount a duplicate iframe.
                if (methods !== null && methods.length > 1) {
                    let _this = this;
                    let _super = this._super;
                    let checkboxId = this.getCode();
                    let checkoutHandler = this.checkoutHandler;
                    var updateAddressesCallback = function () {
                        checkoutHandler.updateAddresses(_super.bind(_this));
                    };
                    let intervalId = setInterval(function () {
                        // stop loader when frame will be loaded
                        if ($('#' + checkboxId).length >= 1 && $('#' + checkboxId).is(':checked')) {
                            clearInterval(intervalId);
                            fullScreenLoader.startLoader();
                            updateAddressesCallback();
                            fullScreenLoader.stopLoader(true);
                        }
                    }, 100);
                }
            }
        },

        getFormId: function () {
            return this.getCode() + '-payment-form';
        },

        getConfigurationId: function () {
            return window.checkoutConfig.payment[this.getCode()].configurationId;
        },

        fetchMetadata: function () {
            var self = this;
            var isGuest = quote.getQuoteId() == null || !window.checkoutConfig.isCustomerLoggedIn;
            var restUrl = '';

            if (isGuest) {
                restUrl = '/V1/wallee/checkout/guest/' + quote.getQuoteId() + '/metadata/' + self.getCode();
            } else {
                restUrl = '/V1/wallee/checkout/mine/metadata/' + self.getCode();
            }

            // Add dynamic store code to the REST API route if available
            var storeCode = window.checkoutConfig.storeCode ? '/' + window.checkoutConfig.storeCode : '';
            var serviceUrl = window.BASE_URL + 'rest' + storeCode + restUrl;

            return storage.get(serviceUrl, true);
        },


        isActive: function () {
            return quote.paymentMethod() ? quote.paymentMethod().method == this.getCode() : false;
        },

        isShowDescription: function () {
            return window.checkoutConfig.payment[this.getCode()].showDescription;
        },

        getDescription: function () {
            return window.checkoutConfig.payment[this.getCode()].description;
        },

        isShowImage: function () {
            return window.checkoutConfig.payment[this.getCode()].showImage;
        },

        getImageUrl: function () {
            return window.checkoutConfig.payment[this.getCode()].imageUrl;
        },

        createIframeHandler: function () {
            var self = this;
            var registry = window.__walleeHandlers = window.__walleeHandlers || {};

            if (this.handler) {
                this.checkoutHandler.selectPaymentMethod();
            } else if (this.loadingIframe) {
                // Iframe creation is already in flight (e.g. triggered by both the
                // single-method auto-trigger and the checkbox interval, or by repeated
                // address updates). Without this guard each concurrent call would run
                // handler.create() and mount a duplicate iframe into the same container.
                return;
            } else if (this.isActive() && this.checkoutHandler.validateAddresses()) {
                // Mark creation as in-flight synchronously, before any async work, so a
                // second invocation is rejected by the guard above. this.handler is only
                // assigned much later (after the REST + SDK round-trips), so it cannot
                // serve as the guard here.
                this.loadingIframe = true;

                // We fetch metadata dynamically to ensure the latest configuration and SDK URL
                // are used for the transaction. This allows the plugin to react to dynamic changes.
                fullScreenLoader.startLoader();
                $('body').trigger('processStart');

                this.fetchMetadata().done(function (response) {
                    var data = JSON.parse(response);
                    if (data.error) {
                        self.loadingIframe = false;
                        $('body').trigger('processStop');
                        fullScreenLoader.stopLoader(true);
                        console.error("Wallee Metadata Error:", data.error);
                        return;
                    }

                    // Load the SDK dynamically from the URL provided in the metadata.
                    sdkLoader.load(data.javascriptUrl).then(function () {
                        var handlerFactory = window.IframeCheckoutHandler;
                        if (typeof handlerFactory !== 'function') {
                            self.loadingIframe = false;
                            $('body').trigger('processStop');
                            fullScreenLoader.stopLoader(true);
                            console.error("Wallee SDK Load failed: IframeCheckoutHandler is not a function");
                            return;
                        }

                        // Configure the SDK to replace the primary action if supported.
                        if (self.checkoutHandler.canReplacePrimaryAction()) {
                            handlerFactory.configure('replacePrimaryAction', true);
                        }

                        self.handler = handlerFactory(data.configurationId);
                        registry[data.configurationId] = self.handler; // Global registry for cross-component access.

                        // Setup interaction callbacks.
                        self.handler.setResetPrimaryActionCallback(function () {
                            this.checkoutHandler.resetPrimaryAction();
                        }.bind(self));
                        self.handler.setReplacePrimaryActionCallback(function (label) {
                            this.checkoutHandler.replacePrimaryAction(label);
                        }.bind(self));

                        // Create the payment iframe.
                        self.handler.create(self.getFormId(), (function (validationResult) {
                            if (validationResult.success) {
                                this.placeOrder();
                            } else {
                                // Scroll to payment method on validation failure.
                                $('html, body').animate({ scrollTop: $('#' + this.getCode()).offset().top - 20 });
                                if (validationResult.errors) {
                                    for (var i = 0; i < validationResult.errors.length; i++) {
                                        this.messageContainer.addErrorMessage({
                                            message: this.stripHtml(validationResult.errors[i])
                                        });
                                    }
                                }
                            }
                        }).bind(self), (function () {
                            $('body').trigger('processStop');
                            fullScreenLoader.stopLoader(true);
                            this.loadingIframe = false;
                        }).bind(self));
                    }).catch(function (error) {
                        self.loadingIframe = false;
                        $('body').trigger('processStop');
                        fullScreenLoader.stopLoader(true);
                        console.error("Wallee SDK Load failed", error);
                    });

                }).fail(function (error) {
                    self.loadingIframe = false;
                    $('body').trigger('processStop');
                    fullScreenLoader.stopLoader(true);
                    console.error("Wallee REST Metadata Call failed", error);
                });
            }
        },

        getSubmitButton: function () {
            return $('#' + this.getFormId()).parents('.payment-method-content').find('button.checkout');
        },

        selectPaymentMethod: function () {
            if (this.checkoutHandler) {
                this.checkoutHandler.updateAddresses(this._super.bind(this));
                return true;
            } else {
                return this._super();
            }
        },

        validateWhitelabelmachinename: function () {
            if (window.checkoutConfig.wallee.integrationMethod == 'iframe') {
                if (this.loadingIframe) {
                    return;
                }
                if (this.handler) {
                    if (this.checkoutHandler.isPrimaryActionReplaced()) {
                        this.handler.trigger();
                    } else {
                        this.handler.validate();
                    }
                } else {
                    this.placeOrder();
                }
            } else {
                this.placeOrder();
            }
        },

        placeOrder: function (data, event) {
            var self = this;

            if (event) {
                event.preventDefault();
            }

            if (this.validate() && additionalValidators.validate()) {
                this.isPlaceOrderActionAllowed(false);

                this.getPlaceOrderDeferredObject()
                    .fail(
                        function (response) {
                            var error = null;
                            try {
                                error = JSON.parse(response.responseText);
                            } catch (exception) {
                            }
                            if (typeof error == 'object' && error.message == 'wallee_checkout_failure') {
                                window.location.replace(urlBuilder.build("wallee_payment/checkout/failure"));
                            } else {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        }
                    ).done(
                        function () {
                            self.afterPlaceOrder();
                        }
                    );

                return true;
            }

            return false;
        },

        afterPlaceOrder: function () {
            var self = this;
            var registry = window.__walleeHandlers = window.__walleeHandlers || {};
            var restoreCartUrl = window.checkoutConfig.wallee.restoreCartUrl;

            // Mark the session as "payment in flight" via a cookie. If the
            // customer is bounced back to /checkout/cart by any browser path
            // (Firefox back-button, wallee fallback redirect, etc.),
            // the server-side RestoreCartOnCartPage observer reads this cookie
            // during cart predispatch and reactivates the quote inline — no
            // extra round-trip, no white-page flash.
            document.cookie = 'wallee_restore_pending=1; path=/; samesite=lax';

            window.history.pushState({}, document.title, restoreCartUrl);

            // When the user hits Back from the payment screen, the browser may
            // restore this checkout document from the bfcache (Firefox does this
            // aggressively; Chrome usually does not for cross-origin returns) and
            // skip the GET to restoreCartUrl, leaving the cart un-restored. Force
            // a real navigation in that case so the server-side restore runs.
            // Covers all integration types: iframe, lightbox, payment_page.
            // Guard so re-entry (lightbox cancel + retry) doesn't stack listeners.
            if (!window.walleeRestoreAttached) {
                window.walleeRestoreAttached = true;
                var forceRestore = function () {
                    window.removeEventListener('pageshow', onPageShow);
                    window.removeEventListener('popstate', onPopState);
                    // Skip if the in-flight cookie is gone (payment finished or
                    // cart-page observer already restored).
                    if (document.cookie.indexOf('wallee_restore_pending=') === -1) {
                        return;
                    }
                    window.location.replace(restoreCartUrl);
                };
                var onPageShow = function (event) {
                    if (event.persisted) {
                        forceRestore();
                    }
                };
                var onPopState = function () {
                    forceRestore();
                };
                window.addEventListener('pageshow', onPageShow);
                window.addEventListener('popstate', onPopState);
            }

            fullScreenLoader.startLoader();
            $('body').trigger('processStart');
            if (window.checkoutConfig.wallee.integrationMethod == 'iframe' && this.handler) {
                this.handler.submit();
            } else if (window.checkoutConfig.wallee.integrationMethod == 'lightbox') {
                this.fetchMetadata().done(function (response) {
                    var data = JSON.parse(response);
                    if (data.error) {
                        console.error("Wallee Lightbox Metadata Error:", data.error);
                        self.fallbackToPaymentPage();
                        return;
                    }

                    sdkLoader.load(data.javascriptUrl).then(function () {
                        var handlerFactory = window.LightboxCheckoutHandler;
                        if (typeof handlerFactory != 'undefined' && handlerFactory) {
                            registry[data.configurationId] = handlerFactory; // Utilize global registry
                            handlerFactory.startPayment(data.configurationId, function () {
                                self.fallbackToPaymentPage();
                            });
                        } else {
                            self.fallbackToPaymentPage();
                        }
                    }).catch(function () {
                        self.fallbackToPaymentPage();
                    });
                }).fail(function (error) {
                    console.error("Wallee REST Metadata Call failed for Lightbox", error);
                    self.fallbackToPaymentPage();
                });
            } else {
                this.fallbackToPaymentPage();
            }
        },

        fallbackToPaymentPage: function () {
            fullScreenLoader.startLoader();
            if (window.checkoutConfig.wallee.paymentPageUrl) {
                // Use assign (not replace) so the pushState entry with restoreCartUrl
                // from afterPlaceOrder survives in history — pressing Back from the
                // 3DS page then lands on RestoreCart, which reactivates the quote.
                window.location.assign(window.checkoutConfig.wallee.paymentPageUrl);
            } else {
                window.location.replace(urlBuilder.build("wallee_payment/checkout/failure"));
            }
        },

        stripHtml: function (input) {
            return $('<div>' + input + '</div>').text();
        }
    });
});
