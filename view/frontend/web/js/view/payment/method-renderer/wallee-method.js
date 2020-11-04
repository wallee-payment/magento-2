/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
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
		
		validateWallee: function(){
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
			
			window.history.pushState({}, document.title, window.checkoutConfig.wallee.restoreCartUrl);
			
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
				window.location.replace(window.checkoutConfig.wallee.paymentPageUrl + "&paymentMethodConfigurationId=" + this.getConfigurationId());
			} else {
				window.location.replace(urlBuilder.build("wallee_payment/checkout/failure"));
			}
		},
		
		stripHtml: function(input){
			return $('<div>' + input + '</div>').text();
		}
	});
});