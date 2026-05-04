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
	'Wallee_Payment/js/model/checkout-handler'
], function(
	$,
	Component,
	fullScreenLoader,
	methodList,
	urlBuilder,
	quote,
	additionalValidators,
	checkoutHandler
){
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
		initialize: function(){
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
				if (methods !== null && methods.length >= 1) {
					let _this = this;
					let _super = this._super;
					let checkboxId = this.getCode();
					let checkoutHandler = this.checkoutHandler;
					var updateAddressesCallback = function() {
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

		getFormId: function(){
			return this.getCode() + '-payment-form';
		},

		getConfigurationId: function(){
			return window.checkoutConfig.payment[this.getCode()].configurationId;
		},

		isActive: function(){
			return quote.paymentMethod() ? quote.paymentMethod().method == this.getCode() : false;
		},

		isShowDescription: function(){
			return window.checkoutConfig.payment[this.getCode()].showDescription;
		},

		getDescription: function(){
			return window.checkoutConfig.payment[this.getCode()].description;
		},

		isShowImage: function(){
			return window.checkoutConfig.payment[this.getCode()].showImage;
		},

		getImageUrl: function(){
			return window.checkoutConfig.payment[this.getCode()].imageUrl;
		},

		createIframeHandler: function(){
			if (this.handler) {
				this.checkoutHandler.selectPaymentMethod();
			} else if (typeof window.IframeCheckoutHandler != 'undefined' && this.isActive() && this.checkoutHandler.validateAddresses()) {
				if (this.checkoutHandler.canReplacePrimaryAction()) {
					window.IframeCheckoutHandler.configure('replacePrimaryAction', true);
				}

				this.loadingIframe = true;
				fullScreenLoader.startLoader();
				this.handler = window.IframeCheckoutHandler(this.getConfigurationId());
				this.handler.setResetPrimaryActionCallback(function(){
					this.checkoutHandler.resetPrimaryAction();
				}.bind(this));
				this.handler.setReplacePrimaryActionCallback(function(label){
					this.checkoutHandler.replacePrimaryAction(label);
				}.bind(this));
				this.handler.create(this.getFormId(), (function(validationResult){
					if (validationResult.success) {
						this.placeOrder();
					} else {
						$('html, body').animate({ scrollTop: $('#' + this.getCode()).offset().top - 20 });
						if (validationResult.errors) {
							for (var i = 0; i < validationResult.errors.length; i++) {
								this.messageContainer.addErrorMessage({
									message: this.stripHtml(validationResult.errors[i])
								});
							}
						}
					}
				}).bind(this), (function(){
					fullScreenLoader.stopLoader();
					this.loadingIframe = false;
				}).bind(this));
			}
		},

		getSubmitButton: function(){
			return $('#' + this.getFormId()).parents('.payment-method-content').find('button.checkout');
		},

		selectPaymentMethod: function(){
			if (this.checkoutHandler) {
				this.checkoutHandler.updateAddresses(this._super.bind(this));
				return true;
			} else {
				return this._super();
			}
		},

		validateWhitelabelmachinename: function(){
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

		afterPlaceOrder: function(){
			var self = this;
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
				var forceRestore = function(){
					window.removeEventListener('pageshow', onPageShow);
					window.removeEventListener('popstate', onPopState);
					// Skip if the in-flight cookie is gone (payment finished or
					// cart-page observer already restored).
					if (document.cookie.indexOf('wallee_restore_pending=') === -1) {
						return;
					}
					window.location.replace(restoreCartUrl);
				};
				var onPageShow = function(event){
					if (event.persisted) {
						forceRestore();
					}
				};
				var onPopState = function(){
					forceRestore();
				};
				window.addEventListener('pageshow', onPageShow);
				window.addEventListener('popstate', onPopState);
			}

			fullScreenLoader.startLoader();

			if (window.checkoutConfig.wallee.integrationMethod == 'iframe' && this.handler) {
				this.handler.submit();
			} else if (window.checkoutConfig.wallee.integrationMethod == 'lightbox' && typeof window.LightboxCheckoutHandler != 'undefined') {
				window.LightboxCheckoutHandler.startPayment(this.getConfigurationId(), function(){
					self.fallbackToPaymentPage();
				});
			} else {
				this.fallbackToPaymentPage();
			}
		},

		fallbackToPaymentPage: function(){
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

		stripHtml: function(input){
			return $('<div>' + input + '</div>').text();
		}
	});
});
